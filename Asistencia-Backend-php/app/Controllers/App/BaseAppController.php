<?php

namespace App\Controllers\App;

use App\Core\Database;
use PDO;

abstract class BaseAppController
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
}
