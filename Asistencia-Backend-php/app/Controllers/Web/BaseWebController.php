<?php

namespace App\Controllers\Web;

use App\Core\Database;
use PDO;

abstract class BaseWebController
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function userId(): int
    {
        return (int) ($_REQUEST['auth_user']['sub'] ?? 0);
    }

    protected function rol(): string
    {
        return $_REQUEST['auth_user']['rol'] ?? '';
    }

    protected function esAdmin(): bool
    {
        return $this->rol() === 'administrador';
    }

    protected function esSupervisor(): bool
    {
        return $this->rol() === 'supervisor';
    }
}
