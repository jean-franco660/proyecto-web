<?php
namespace App\Models;

class UsuarioApp extends BaseModel
{
    protected string $table = 'usuarios';

    /**
     * Busca un trabajador por código de empleado.
     * Esquema v2: usuarios + usuario_roles (rol TRABAJADOR)
     */
    public function findByCodigo(string $codigo): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, eu.nombre AS estado,
                   ut.nombres, ut.apellidos, ut.dni, ut.telefono, ut.foto, ut.fecha_nacimiento
            FROM usuarios u
            INNER JOIN usuario_roles ur   ON ur.usuario_id = u.id
            INNER JOIN roles r            ON r.id = ur.rol_id
            INNER JOIN estados_usuario eu ON eu.id = u.estado_id
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            WHERE u.codigo_empleado = :codigo
              AND r.nombre = 'TRABAJADOR'
            LIMIT 1
        ");
        $stmt->execute([':codigo' => $codigo]);
        return $stmt->fetch() ?: null;
    }

    /** Devuelve el trabajador con su asignación activa (sede + horario) */
    public function findConAsignacion(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.codigo_empleado, u.estado_id,
                   eu.nombre AS estado,
                   ut.nombres, ut.apellidos, ut.dni, ut.telefono, ut.foto,
                   us.sede_id, us.horario_id, us.id AS usuario_sede_id,
                   s.nombre AS sede_nombre,
                   hs.nombre AS nombre_turno, hs.hora_entrada, hs.hora_salida
            FROM usuarios u
            INNER JOIN usuario_roles ur   ON ur.usuario_id = u.id
            INNER JOIN roles r            ON r.id = ur.rol_id
            INNER JOIN estados_usuario eu ON eu.id = u.estado_id
            LEFT JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            LEFT JOIN usuario_sede us     ON us.usuario_id = u.id
                                          AND us.estado = 1
                                          AND (us.fecha_fin IS NULL OR us.fecha_fin >= CURDATE())
            LEFT JOIN sedes s             ON s.id = us.sede_id
            LEFT JOIN horarios_sede hs    ON hs.id = us.horario_id
            WHERE u.id = ?
              AND r.nombre = 'TRABAJADOR'
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function codigoExists(string $codigo, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM `{$this->table}` WHERE codigo_empleado = ?";
        $params = [$codigo];
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Busca trabajador por DNI y Código para reseteo de contraseña */
    public function findByDniAndCodigo(string $dni, string $codigo): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.* 
            FROM usuarios u
            INNER JOIN usuarios_trabajador ut ON ut.usuario_id = u.id
            WHERE u.codigo_empleado = :codigo AND ut.dni = :dni
            LIMIT 1
        ");
        $stmt->execute([':codigo' => $codigo, ':dni' => $dni]);
        return $stmt->fetch() ?: null;
    }

    /** Crea una solicitud pendiente de reseteo de contraseña */
    public function createPasswordResetRequest(int $usuarioId): void
    {
        // Cancelar pendientes anteriores
        $this->db->prepare("UPDATE password_resets_app SET estado = 'RECHAZADA' WHERE usuario_id = ? AND estado = 'PENDIENTE'")->execute([$usuarioId]);
        
        // Crear nueva solicitud
        $this->db->prepare("INSERT INTO password_resets_app (usuario_id, estado) VALUES (?, 'PENDIENTE')")->execute([$usuarioId]);
    }

    /** Actualiza la contraseña y quita el flag de obligatoriedad */
    public function updatePassword(int $usuarioId, string $newPasswordHash): void
    {
        $this->db->prepare("
            UPDATE usuarios 
            SET password = ?, debe_cambiar_password = 0 
            WHERE id = ?
        ")->execute([$newPasswordHash, $usuarioId]);
    }
}