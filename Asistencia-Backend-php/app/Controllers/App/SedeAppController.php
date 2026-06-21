<?php
namespace App\Controllers\App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * SedeAppController — Sedes del trabajador desde la app.
 * Esquema v2:
 *   - usuario_sede (antes: usuario_app_sede)
 *   - horarios_sede.nombre (antes: nombre_turno)
 *   - horarios_sede.tolerancia_entrada/salida (antes: tolerancia_*_minutos)
 */
class SedeAppController extends BaseAppController
{
    /**
     * GET /v1/app/sedes
     * Devuelve solo las sedes a las que el trabajador está asignado (activo y vigente).
     */
    public function index(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);

        $stmt = $this->db->prepare("
            SELECT s.id, s.nombre, s.direccion, s.latitud, s.longitud, s.radio_metros,
                   hs.nombre AS nombre_turno, hs.hora_entrada, hs.hora_salida
            FROM usuario_sede us
            INNER JOIN sedes s          ON s.id = us.sede_id
            LEFT JOIN  horarios_sede hs ON hs.id = us.horario_id
            WHERE us.usuario_id = :uid AND us.estado = 1
              AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
            ORDER BY s.nombre
        ");
        $stmt->execute([':uid' => $userId]);
        Response::success($stmt->fetchAll());
    }

    /**
     * GET /v1/app/sedes/{id}
     * Detalle de una sede (solo si el trabajador está asignado).
     */
    public function show(Request $req): void
    {
        $userId = (int) ($_REQUEST['auth_user']['sub'] ?? 0);
        $sedeId = (int) $req->param('id');

        $stmt = $this->db->prepare("
            SELECT s.*, hs.nombre AS nombre_turno, hs.hora_entrada, hs.hora_salida,
                   hs.tolerancia_entrada, hs.tolerancia_salida
            FROM usuario_sede us
            INNER JOIN sedes s          ON s.id = us.sede_id
            LEFT JOIN  horarios_sede hs ON hs.id = us.horario_id
            WHERE us.usuario_id = :uid
              AND us.sede_id = :sid
              AND us.estado = 1
              AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sedeId]);
        $sede = $stmt->fetch();
        if (!$sede) Response::notFound('No estás asignado a esta sede');
        Response::success($sede);
    }
}