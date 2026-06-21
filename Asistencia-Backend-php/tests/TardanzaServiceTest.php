<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Services\TardanzaService;
use DateTime;

class TardanzaServiceTest extends TestCase
{
    private TardanzaService $tardanzaService;

    protected function setUp(): void
    {
        $this->tardanzaService = new TardanzaService();
    }

    public function testNoTardanzaBeforeScheduledTime(): void
    {
        $tipo = 'ENTRADA';
        $marcadaEn = new DateTime('2026-06-21 07:55:00');
        $horaEntradaStr = '08:00:00';
        $toleranciaMinutos = 10;

        $res = $this->tardanzaService->calcularTardanza($tipo, $marcadaEn, $horaEntradaStr, $toleranciaMinutos);

        $this->assertFalse($res['esTardanza']);
        $this->assertEquals(0, $res['minutosTarde']);
    }

    public function testNoTardanzaExactlyAtScheduledTime(): void
    {
        $tipo = 'ENTRADA';
        $marcadaEn = new DateTime('2026-06-21 08:00:00');
        $horaEntradaStr = '08:00:00';
        $toleranciaMinutos = 10;

        $res = $this->tardanzaService->calcularTardanza($tipo, $marcadaEn, $horaEntradaStr, $toleranciaMinutos);

        $this->assertFalse($res['esTardanza']);
        $this->assertEquals(0, $res['minutosTarde']);
    }

    public function testNoTardanzaWithinTolerance(): void
    {
        $tipo = 'ENTRADA';
        $marcadaEn = new DateTime('2026-06-21 08:09:59');
        $horaEntradaStr = '08:00:00';
        $toleranciaMinutos = 10;

        $res = $this->tardanzaService->calcularTardanza($tipo, $marcadaEn, $horaEntradaStr, $toleranciaMinutos);

        $this->assertFalse($res['esTardanza']);
        $this->assertEquals(0, $res['minutosTarde']);
    }

    public function testTardanzaAfterTolerance(): void
    {
        $tipo = 'ENTRADA';
        $marcadaEn = new DateTime('2026-06-21 08:11:00'); // 11 mins late (tolerance is 10)
        $horaEntradaStr = '08:00:00';
        $toleranciaMinutos = 10;

        $res = $this->tardanzaService->calcularTardanza($tipo, $marcadaEn, $horaEntradaStr, $toleranciaMinutos);

        $this->assertTrue($res['esTardanza']);
        $this->assertEquals(11, $res['minutosTarde']);
    }

    public function testNoTardanzaOnSalida(): void
    {
        $tipo = 'SALIDA';
        $marcadaEn = new DateTime('2026-06-21 17:05:00');
        $horaEntradaStr = '08:00:00';
        $toleranciaMinutos = 10;

        $res = $this->tardanzaService->calcularTardanza($tipo, $marcadaEn, $horaEntradaStr, $toleranciaMinutos);

        $this->assertFalse($res['esTardanza']);
        $this->assertEquals(0, $res['minutosTarde']);
    }
}
