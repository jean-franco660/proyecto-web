<?php

namespace App\Models;

class Feriado extends BaseModel
{
    protected string $table = 'feriados';

    public function getAll(): array
    {
        return $this->query("SELECT * FROM `{$this->table}` ORDER BY fecha ASC");
    }
}
