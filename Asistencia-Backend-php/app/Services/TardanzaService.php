<?php
declare(strict_types=1);

namespace App\Services;

use DateTime;

class TardanzaService
{
    /**
     * Calcula los minutos de tardanza y determina si corresponde al estado de TARDANZA.
     * 
     * @param string $tipo Tipo de marcación (ENTRADA o SALIDA)
     * @param DateTime $marcadaEn Fecha y hora de la marcación
     * @param string $horaEntradaStr Hora de entrada asignada (ej. '08:00:00')
     * @param int $toleranciaMinutos Tolerancia de entrada en minutos (ej. 15)
     * @return array{esTardanza: bool, minutosTarde: int}
     */
    public function calcularTardanza(string $tipo, DateTime $marcadaEn, string $horaEntradaStr, int $toleranciaMinutos): array
    {
        if ($tipo !== 'ENTRADA') {
            return ['esTardanza' => false, 'minutosTarde' => 0];
        }

        $fechaDia = $marcadaEn->format('Y-m-d');
        $horaEntrada = new DateTime("$fechaDia $horaEntradaStr");
        $limiteConTolerancia = (clone $horaEntrada)->modify("+{$toleranciaMinutos} minutes");

        if ($marcadaEn > $limiteConTolerancia) {
            $minutosTarde = (int) (($marcadaEn->getTimestamp() - $horaEntrada->getTimestamp()) / 60);
            return [
                'esTardanza' => true,
                'minutosTarde' => $minutosTarde
            ];
        }

        return ['esTardanza' => false, 'minutosTarde' => 0];
    }
}
