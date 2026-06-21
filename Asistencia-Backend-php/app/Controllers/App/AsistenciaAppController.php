<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Exceptions\SedeNoEncontradaException;
use App\Exceptions\MarcacionDuplicadaException;
use App\Exceptions\FueraDeRangoException;
use App\Services\GeoService;
use App\Services\TardanzaService;

/**
 * AsistenciaAppController — Marcación de asistencia desde la app móvil.
 * Esquema v2:
 *   - usuario_sede (antes: usuario_app_sede)
 *   - asistencias.usuario_sede_id (antes: usuario_app_id + sede_id + horario_sede_id)
 *   - asistencias.estado_id FK → estados_asistencia (antes: estado_diario ENUM)
 *   - marcaciones (antes: asistencias_diarias) con activo BOOLEAN
 *   - tipos_marcacion: 1=ENTRADA, 2=SALIDA
 *   - sedes.radio_metros (antes: radio)
 */
class AsistenciaAppController extends BaseAppController
{
    /*
     * POST /v1/app/asistencia
     */
    public function store(Request $req): void
    {
        $userId    = $this->userId();
        $sedeId    = (int) $req->input('sede_id');
        $tipo      = (string) $req->input('tipo'); // ENTRADA | SALIDA
        $latitud   = (float)  $req->input('latitud');
        $longitud  = (float)  $req->input('longitud');

        if (!$sedeId || !in_array($tipo, ['ENTRADA','SALIDA']) || !$latitud || !$longitud) {
            Response::unprocessable('Faltan campos requeridos');
        }

        // Hora oficial del servidor
        $fechaHora = date('Y-m-d H:i:s');
        $dispositivoHora = (string) $req->input('fecha_hora');
        $observacion = $dispositivoHora ? "Hora disp: " . $dispositivoHora : null;

        try {
            $resultado = $this->registrarMarcacion($userId, $sedeId, $tipo, $fechaHora, $latitud, $longitud, $observacion);
            Response::success($resultado, 'Asistencia registrada correctamente', 201);
        } catch (FueraDeRangoException $e) {
            Response::error($e->getMessage(), $e->getCode(), $e->getDetails());
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            Response::error($e->getMessage(), $code === 500 ? 500 : $code);
        }
    }

    /**
     * Registra una marcación de forma centralizada tanto para store directo como para syncMovil.
     */
    private function registrarMarcacion(
        int $userId,
        int $sedeId,
        string $tipo,
        string $fechaHora,
        float $latitud,
        float $longitud,
        ?string $observacion = null
    ): array {
        // Mapear tipo a tipo_id
        $tipoId = $tipo === 'ENTRADA' ? 1 : 2;

        // Verificar asignación a sede via usuario_sede
        $stmt = $this->db->prepare("
            SELECT us.*, hs.hora_entrada, hs.hora_salida,
                hs.tolerancia_entrada, hs.tolerancia_salida
            FROM usuario_sede us
            INNER JOIN horarios_sede hs ON us.horario_id = hs.id
            WHERE us.usuario_id = :uid AND us.sede_id = :sid
              AND us.estado = 1
              AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId]);
        $asignacion = $stmt->fetch();

        if (!$asignacion) {
            throw new SedeNoEncontradaException('No estás asignado a esta sede o no tienes horario asignado.');
        }

        $usuarioSedeId = (int) $asignacion['id'];

        // Validar GPS
        $stmt = $this->db->prepare("SELECT latitud, longitud, radio_metros FROM sedes WHERE id = :sid");
        $stmt->execute([':sid' => $sedeId]);
        $sede = $stmt->fetch();

        $geoService = new GeoService();
        $distancia = $geoService->calcularDistanciaMetros($latitud, $longitud, (float)$sede['latitud'], (float)$sede['longitud']);
        if ($distancia > $sede['radio_metros']) {
            throw new FueraDeRangoException(
                "Debes estar dentro de la sede para registrar asistencia.",
                403,
                ['distancia_metros' => (int) $distancia, 'radio_sede' => $sede['radio_metros']]
            );
        }

        // Evaluar ventana horaria
        $marcadaEn   = new \DateTime($fechaHora);
        $fechaDia    = $marcadaEn->format('Y-m-d');
        $estadoId    = 2; // PRESENTE por defecto
        
        $tardanzaService = new TardanzaService();
        $calc = $tardanzaService->calcularTardanza($tipo, $marcadaEn, $asignacion['hora_entrada'], (int)$asignacion['tolerancia_entrada']);
        $minutosTarde = $calc['minutosTarde'];
        if ($calc['esTardanza']) {
            $estadoId = 3; // TARDANZA
        }

        $this->db->beginTransaction();
        try {
            // Obtener o crear cabecera de asistencia
            $stmt = $this->db->prepare("
                SELECT id, estado_id FROM asistencias
                WHERE usuario_sede_id = :usid AND fecha = :fecha
            ");
            $stmt->execute([':usid' => $usuarioSedeId, ':fecha' => $fechaDia]);
            $asistencia = $stmt->fetch();

            if (!$asistencia) {
                $stmt = $this->db->prepare("
                    INSERT INTO asistencias (usuario_sede_id, fecha, estado_id, minutos_tarde)
                    VALUES (:usid, :fecha, 1, 0)
                ");
                $stmt->execute([
                    ':usid'  => $usuarioSedeId,
                    ':fecha' => $fechaDia,
                ]);
                $asistenciaId = (int) $this->db->lastInsertId();
            } else {
                $asistenciaId = (int) $asistencia['id'];
            }

            // Verificar si ya existe marcación activa de este tipo
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM marcaciones
                WHERE asistencia_id = :aid AND tipo_id = :tid AND activo = 1
            ");
            $stmt->execute([':aid' => $asistenciaId, ':tid' => $tipoId]);
            
            if ((int)$stmt->fetchColumn() > 0) {
                throw new MarcacionDuplicadaException(
                    "Ya has marcado {$tipo} hoy en esta sede.",
                    400
                );
            }

            // Guardar marcación
            $stmt = $this->db->prepare("
                INSERT INTO marcaciones
                    (asistencia_id, tipo_id, fecha_hora, latitud, longitud,
                    distancia, activo, observacion)
                VALUES
                    (:aid, :tid, :fh, :lat, :lng,
                    :dist, 1, :obs)
            ");
            $stmt->execute([
                ':aid'  => $asistenciaId,
                ':tid'  => $tipoId,
                ':fh'   => $fechaHora,
                ':lat'  => $latitud,
                ':lng'  => $longitud,
                ':dist' => (int) $distancia,
                ':obs'  => $observacion,
            ]);

            // Actualizar estado de la cabecera
            if ($tipo === 'ENTRADA' && $estadoId !== 1) {
                $this->db->prepare("
                    UPDATE asistencias
                    SET estado_id = :eid, minutos_tarde = :mt
                    WHERE id = :id
                ")->execute([
                    ':eid' => $estadoId,
                    ':mt'  => $minutosTarde,
                    ':id'  => $asistenciaId,
                ]);
            } elseif ($tipo === 'SALIDA') {
                // En salida, si la entrada fue TARDANZA mantenerla; sino marcar PRESENTE
                $stmtEstado = $this->db->prepare("SELECT estado_id FROM asistencias WHERE id = ?");
                $stmtEstado->execute([$asistenciaId]);
                $estadoActual = (int) $stmtEstado->fetchColumn();
                if ($estadoActual !== 3) { // Si no es TARDANZA
                    $this->db->prepare("UPDATE asistencias SET estado_id = 2 WHERE id = ?")
                        ->execute([$asistenciaId]); // Marcar como PRESENTE
                }
            }

            $this->db->commit();

            $estadoNombres = [1 => 'PENDIENTE', 2 => 'PRESENTE', 3 => 'TARDANZA', 4 => 'FALTA', 5 => 'JUSTIFICADO'];
            return [
                'tipo'              => $tipo,
                'dentro_rango'      => true,
                'distancia_metros'  => (int) $distancia,
                'estado_asistencia' => $estadoNombres[$estadoId] ?? 'PRESENTE',
            ];

        } catch (MarcacionException $e) {
            $this->db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[AsistenciaAppController::registrarMarcacion] Error: ' . $e->getMessage());
            throw new \Exception('Error registrando marcación. Intente nuevamente.', 500);
        }
    }



    /** GET /v1/app/asistencia/{id} — historial del trabajador */
    public function historial(Request $req): void
    {
        $userId = $this->userId();
        $solicitadoId = (int) $req->param('usuarioId');

        // Solo puede ver su propio historial
        if ($solicitadoId !== $userId)
            Response::forbidden('Solo puedes ver tu propio historial');

        $stmt = $this->db->prepare("
            SELECT a.*, ea.nombre AS estado,
                   s.nombre AS sede_nombre,
                   hs.nombre AS nombre_turno, hs.hora_entrada, hs.hora_salida
            FROM asistencias a
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            INNER JOIN usuario_sede us ON us.id = a.usuario_sede_id
            LEFT JOIN sedes s          ON s.id = us.sede_id
            LEFT JOIN horarios_sede hs ON hs.id = us.horario_id
            WHERE us.usuario_id = :uid
            ORDER BY a.fecha DESC
            LIMIT 60
        ");
        $stmt->execute([':uid' => $userId]);
        Response::success($stmt->fetchAll());
    }

    /** GET /v1/app/estado-dia?sede_id=1 — estado actual del trabajador hoy */
    public function estadoDia(Request $req): void
    {
        $userId = $this->userId();
        $sedeId = (int) $req->query('sede_id');
        $hoy    = date('Y-m-d');

        // Buscar usuario_sede activo
        $stmtUs = $this->db->prepare("
            SELECT us.id AS usuario_sede_id, hs.hora_entrada, hs.hora_salida,
                   hs.nombre AS nombre_turno, hs.tolerancia_entrada
            FROM usuario_sede us
            INNER JOIN horarios_sede hs ON hs.id = us.horario_id
            WHERE us.usuario_id = :uid AND us.sede_id = :sid
              AND us.estado = 1
              AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
            LIMIT 1
        ");
        $stmtUs->execute([':uid' => $userId, ':sid' => $sedeId]);
        $asignacion = $stmtUs->fetch();

        if (!$asignacion) {
            Response::success([
                'server_now'     => date('c'),
                'tiene_entrada'  => false,
                'tiene_salida'   => false,
                'next_action'    => null,
                'estado'         => 'SIN_ASIGNACION',
                'horario'        => null,
                'marcaciones'    => [],
            ]);
            return;
        }

        // Buscar asistencia de hoy
        $stmt = $this->db->prepare("
            SELECT a.*, ea.nombre AS estado
            FROM asistencias a
            INNER JOIN estados_asistencia ea ON ea.id = a.estado_id
            WHERE a.usuario_sede_id = :usid AND a.fecha = :fecha
        ");
        $stmt->execute([':usid' => $asignacion['usuario_sede_id'], ':fecha' => $hoy]);
        $asistencia = $stmt->fetch();

        // Marcaciones de hoy
        $marcaciones = [];
        if ($asistencia) {
            $stmt2 = $this->db->prepare("
                SELECT tm.nombre AS tipo, m.fecha_hora, m.activo
                FROM marcaciones m
                INNER JOIN tipos_marcacion tm ON tm.id = m.tipo_id
                WHERE m.asistencia_id = :aid AND m.activo = 1
                ORDER BY m.fecha_hora ASC
            ");
            $stmt2->execute([':aid' => $asistencia['id']]);
            $marcaciones = $stmt2->fetchAll();
        }

        $tieneEntrada = in_array('ENTRADA', array_column($marcaciones, 'tipo'));
        $tieneSalida  = in_array('SALIDA', array_column($marcaciones, 'tipo'));

        Response::success([
            'server_now'     => date('c'),
            'tiene_entrada'  => $tieneEntrada,
            'tiene_salida'   => $tieneSalida,
            'next_action'    => !$tieneEntrada ? 'ENTRADA' : (!$tieneSalida ? 'SALIDA' : null),
            'estado'         => $asistencia['estado'] ?? 'SIN_REGISTRO',
            'horario'        => [
                'nombre_turno'  => $asignacion['nombre_turno'],
                'hora_entrada'  => $asignacion['hora_entrada'],
                'hora_salida'   => $asignacion['hora_salida'],
            ],
            'marcaciones'    => $marcaciones,
        ]);
    }

    /**
     * POST /v1/app/asistencias/sincronizar — sincronizar marcaciones offline
     */
    public function syncMovil(Request $req): void
    {
        $marcaciones = $req->input('marcaciones', []);
        if (!is_array($marcaciones) || empty($marcaciones)) {
            Response::unprocessable('No hay marcaciones para sincronizar');
        }

        $userId = $this->userId();
        $resultados = [];

        foreach ($marcaciones as $m) {
            $uuid      = $m['offline_uuid'] ?? null;
            $sedeId    = (int) ($m['sede_id'] ?? 0);
            $tipo      = (string) ($m['tipo'] ?? '');
            $latitud   = (float) ($m['latitud'] ?? 0);
            $longitud  = (float) ($m['longitud'] ?? 0);
            $fechaHora = (string) ($m['fecha_hora'] ?? '');

            if (!$sedeId || !in_array($tipo, ['ENTRADA','SALIDA']) || !$latitud || !$longitud || !$fechaHora) {
                $resultados[] = [
                    'uuid'   => $uuid,
                    'status' => 'rechazado',
                    'error'  => 'Datos de marcación incompletos'
                ];
                continue;
            }

            try {
                // Al sincronizar offline, guardamos en observacion que se sincronizó offline
                $obs = "Sincronizado offline. Hora disp: " . $fechaHora;
                $this->registrarMarcacion($userId, $sedeId, $tipo, $fechaHora, $latitud, $longitud, $obs);
                $resultados[] = [
                    'uuid'   => $uuid,
                    'status' => 'aceptado'
                ];
            } catch (FueraDeRangoException $e) {
                $resultados[] = [
                    'uuid'   => $uuid,
                    'status' => 'rechazado',
                    'error'  => $e->getMessage(),
                    'details'=> $e->getDetails()
                ];
            } catch (\Exception $e) {
                $resultados[] = [
                    'uuid'   => $uuid,
                    'status' => 'rechazado',
                    'error'  => $e->getMessage()
                ];
            }
        }

        Response::success($resultados, 'Sincronización completada');
    }
}