<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/** Deterministic calculation for two nonnegative POS quantities/amounts. */
final class JsaBusinessCalculator
{
    /**
     * @return array{previous:float,current:float,difference:float,direction:string,percentage:?float,percentage_defined:bool}
     */
    public function change(int|float $previous, int|float $current): array
    {
        $previous = (float) $previous;
        $current = (float) $current;

        if (!is_finite($previous) || !is_finite($current) || $previous < 0 || $current < 0) {
            throw new InvalidArgumentException('Nilai harus angka nonnegatif yang berhingga.');
        }

        $difference = $current - $previous;
        $direction = $difference > 0 ? 'naik' : ($difference < 0 ? 'turun' : 'tetap');
        $percentage = $previous == 0.0 ? null : round(($difference / $previous) * 100, 2);

        return [
            'previous' => $previous,
            'current' => $current,
            'difference' => $difference,
            'direction' => $direction,
            'percentage' => $percentage,
            'percentage_defined' => $percentage !== null,
        ];
    }

    /** Read-only, predictable explanation; does not claim provenance of input data. */
    public function explain(int|float $previous, int|float $current, string $unit = 'porsi'): string
    {
        $result = $this->change($previous, $current);
        if (!in_array($unit, ['porsi', 'transaksi', 'rupiah'], true)) {
            throw new InvalidArgumentException('Satuan tidak diizinkan.');
        }

        $old = $this->format($result['previous']);
        $new = $this->format($result['current']);
        $diff = $this->format(abs($result['difference']));

        if (!$result['percentage_defined']) {
            return "Nilai berubah dari {$old} menjadi {$new} {$unit} (selisih {$diff} {$unit}). "
                . 'Persentase perubahan tidak dapat dihitung karena nilai awal nol.';
        }
        if ($result['direction'] === 'tetap') {
            return "Nilai tetap {$new} {$unit}; selisih 0 {$unit} dan perubahan 0%.";
        }
        $pct = $this->format(abs($result['percentage']));
        return "Nilai {$result['direction']} {$pct}% dari {$old} menjadi {$new} {$unit} "
            . "(selisih {$diff} {$unit}).";
    }

    private function format(float $number): string
    {
        $rounded = round($number, 2);
        return rtrim(rtrim(number_format($rounded, 2, ',', ''), '0'), ',');
    }
}
