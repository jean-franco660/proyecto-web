<?php

declare(strict_types=1);

namespace App\Enums;

enum Rol: int
{
    case ADMIN = 1;
    case SUPERVISOR = 2;
    case TRABAJADOR = 3;

    /**
     * Devuelve la etiqueta en string compatible con el frontend y lógica antigua.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'administrador',
            self::SUPERVISOR => 'supervisor',
            self::TRABAJADOR => 'trabajador',
        };
    }

    /**
     * Mapea un string de rol a una instancia del Enum.
     */
    public static function fromLabel(string $label): ?self
    {
        return match (strtolower(trim($label))) {
            'administrador', 'admin' => self::ADMIN,
            'supervisor' => self::SUPERVISOR,
            'trabajador' => self::TRABAJADOR,
            default => null,
        };
    }
}
