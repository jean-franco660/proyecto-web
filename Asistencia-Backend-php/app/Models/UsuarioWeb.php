<?php

namespace App\Models;

class UsuarioWeb extends BaseModel
{
    protected string $table = 'usuarios';

    /**
     * Busca un usuario staff (ADMIN o SUPERVISOR) por email.
     * Esquema v2: usuarios + usuario_roles + roles + usuarios_staff + estados_usuario
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.password, u.estado_id,
                   eu.nombre AS estado,
                   us.nombre AS nombre_staff,
                   r.nombre AS rol
            FROM usuarios u
            INNER JOIN usuario_roles ur  ON ur.usuario_id = u.id
            INNER JOIN roles r           ON r.id = ur.rol_id
            INNER JOIN estados_usuario eu ON eu.id = u.estado_id
            LEFT JOIN usuarios_staff us  ON us.usuario_id = u.id
            WHERE u.email = :email
              AND eu.nombre = 'ACTIVO'
              AND r.nombre IN ('ADMIN', 'SUPERVISOR')
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM `{$this->table}` WHERE email = ?";
        $params = [$email];
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Busca un usuario staff por ID.
     * Esquema v2: usuarios + usuario_roles + roles + usuarios_staff + estados_usuario
     */
    public function find(int|string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.password, u.estado_id,
                   eu.nombre AS estado,
                   us.nombre AS nombre_staff,
                   r.nombre AS rol,
                   u.created_at, u.updated_at
            FROM usuarios u
            INNER JOIN usuario_roles ur  ON ur.usuario_id = u.id
            INNER JOIN roles r           ON r.id = ur.rol_id
            INNER JOIN estados_usuario eu ON eu.id = u.estado_id
            LEFT JOIN usuarios_staff us  ON us.usuario_id = u.id
            WHERE u.id = :id
              AND eu.nombre != 'BLOQUEADO'
              AND r.nombre IN ('ADMIN', 'SUPERVISOR')
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function saveVerificationCode(int $id, string $code): void
    {
        // El código expira en 10 minutos
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $stmt = $this->db->prepare("UPDATE usuarios SET verification_code = ?, verification_expires_at = ? WHERE id = ?");
        $stmt->execute([$code, $expiresAt, $id]);
    }

    public function getVerificationCode(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT verification_code, verification_expires_at FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function clearVerificationCode(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET verification_code = NULL, verification_expires_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
    }
}