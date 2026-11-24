<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Strictly opt-in, read-only arithmetic for explicitly hypothetical user inputs.
 * It intentionally does NOT interpret real POS queries, forecasts or follow-ups.
 */
final class JsaExplicitChangeResponder
{
    public function __construct(private readonly JsaBusinessCalculator $calculator = new JsaBusinessCalculator())
    {
    }

    /** @return array<string, bool|string>|null */
    public function respond(string $message): ?array
    {
        $message = trim($message);
        if (strlen($message) > 500 || $message === '') {
            return null;
        }
        if (!preg_match('/^(?:jika|misal(?:nya)?|contoh|simulasi|hipotetis)\b/iu', $message)) {
            return null;
        }
        if (!preg_match('/\b(?:persen|persentase|%)\b|%/iu', $message)) {
            return null;
        }
        // No mixed requests or POS operations: allow the existing role-scoped path.
        if (preg_match('/\b(?:stok|restock|laba|hpp|profit|rekomendasi|forecast|prediksi|database|aktual|nyata|total\s+bisnis)\b/iu', $message)) {
            return null;
        }
        if (preg_match_all('/\d+/u', $message) !== 2) {
            return null;
        }
        $old = null;
        $new = null;
        $unit = null;
        if (preg_match('/\bkemarin\s+(\d+)\s+(porsi|transaksi)\s+dan\s+hari\s+ini\s+(\d+)\s+\2\b/iu', $message, $m)) {
            $old = $m[1];
            $new = $m[3];
            $unit = strtolower($m[2]);
        } elseif (preg_match('/\bdari\s+(\d+)\s*(porsi|transaksi)?\s+(?:menjadi|ke)\s+(\d+)\s*(porsi|transaksi)?\b/iu', $message, $m)) {
            $old = $m[1];
            $new = $m[3];
            $firstUnit = strtolower($m[2] ?? '');
            $lastUnit = strtolower($m[4] ?? '');
            if ($firstUnit !== '' && $lastUnit !== '' && $firstUnit !== $lastUnit) {
                return null;
            }
            $unit = $firstUnit ?: $lastUnit;
        }
        if ($old === null || $new === null || !in_array($unit, ['porsi', 'transaksi'], true)) {
            return null;
        }
        if (preg_match('/(?<!\w)-\s*\d+/u', $message)) {
            return null;
        }
        if (strlen($old) > 8 || strlen($new) > 8) {
            return null;
        }
        $answer = $this->calculator->explain((int) $old, (int) $new, $unit);
        return [
            'answer' => $answer . ' Ini perhitungan dari angka hipotetis yang Anda berikan, bukan bukti transaksi POS nyata.',
            'source' => 'user_hypothetical',
            'engine' => 'calculator:explicit_change',
            'fallback' => false,
            'calculation_verified' => true,
            'data_source_verified' => false,
        ];
    }
}
