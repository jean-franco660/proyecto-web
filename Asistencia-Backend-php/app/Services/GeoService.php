<?php

declare(strict_types=1);

namespace App\Services;

class GeoService
{
    /**
     * Calcula la distancia en metros entre dos puntos GPS (fórmula Haversine)
     */
    public function calcularDistanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000; // Radio de la Tierra en metros
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);

        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Determina si la distancia calculada está dentro del radio de tolerancia de la sede.
     */
    public function estaDentroDelRango(float $lat1, float $lng1, float $lat2, float $lng2, float $radioMetros): bool
    {
        return $this->calcularDistanciaMetros($lat1, $lng1, $lat2, $lng2) <= $radioMetros;
    }
}
