<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Services\GeoService;

class GeoServiceTest extends TestCase
{
    private GeoService $geoService;

    protected function setUp(): void
    {
        $this->geoService = new GeoService();
    }

    public function testDistanceBetweenSamePointsIsZero(): void
    {
        $lat = -12.046374;
        $lng = -77.042793;

        $distance = $this->geoService->calcularDistanciaMetros($lat, $lng, $lat, $lng);
        $this->assertEqualsWithDelta(0.0, $distance, 0.001);
    }

    public function testDistanceBetweenDifferentPointsIsCorrect(): void
    {
        // Puntos conocidos en Lima, Perú
        // Plaza de Armas
        $lat1 = -12.0453;
        $lng1 = -77.0311;
        
        // Plaza San Martín (aprox. 850 metros de distancia)
        $lat2 = -12.0516;
        $lng2 = -77.0346;

        $distance = $this->geoService->calcularDistanciaMetros($lat1, $lng1, $lat2, $lng2);
        
        // Verificamos que la distancia esté en el rango esperado (~800 a 900 metros)
        $this->assertGreaterThan(750, $distance);
        $this->assertLessThan(950, $distance);
    }

    public function testEstaDentroDelRango(): void
    {
        $lat1 = -12.0453;
        $lng1 = -77.0311;
        $lat2 = -12.0516;
        $lng2 = -77.0346;

        // Fuera de un radio de 500 metros
        $this->assertFalse($this->geoService->estaDentroDelRango($lat1, $lng1, $lat2, $lng2, 500));

        // Dentro de un radio de 1000 metros
        $this->assertTrue($this->geoService->estaDentroDelRango($lat1, $lng1, $lat2, $lng2, 1000));
    }
}
