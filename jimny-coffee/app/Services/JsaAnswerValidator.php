<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A narrow, fail-closed guard for explicit structured numeric claims.
 * Does not claim to understand or verify arbitrary LLM prose.
 */
final class JsaAnswerValidator
{
    public function __construct(private readonly JsaBusinessCalculator $calculator)
    {
    }

    /**
     * @param array<string,mixed> $claim
     * @return array{accepted:bool,reason:string,verified:array<string,mixed>}
     */
    public function validateChangeClaim(int|float $previous, int|float $current, array $claim): array
    {
        $expected = $this->calculator->change($previous, $current);
        if (!array_key_exists('direction', $claim) || !array_key_exists('percentage', $claim)) {
            return ['accepted' => false, 'reason' => 'missing_fields', 'verified' => $expected];
        }
        if (!is_string($claim['direction']) || !in_array($claim['direction'], ['naik', 'turun', 'tetap'], true)) {
            return ['accepted' => false, 'reason' => 'invalid_direction', 'verified' => $expected];
        }
        if ($claim['direction'] !== $expected['direction']) {
            return ['accepted' => false, 'reason' => 'direction_mismatch', 'verified' => $expected];
        }
        if ($expected['percentage'] === null) {
            return [
                'accepted' => $claim['percentage'] === null,
                'reason' => $claim['percentage'] === null ? 'ok' : 'undefined_percentage',
                'verified' => $expected,
            ];
        }
        if (!is_int($claim['percentage']) && !is_float($claim['percentage'])) {
            return ['accepted' => false, 'reason' => 'invalid_percentage', 'verified' => $expected];
        }
        if (!is_finite((float) $claim['percentage']) || abs((float) $claim['percentage'] - $expected['percentage']) > 0.01) {
            return ['accepted' => false, 'reason' => 'percentage_mismatch', 'verified' => $expected];
        }
        return ['accepted' => true, 'reason' => 'ok', 'verified' => $expected];
    }

    /** Never expose an unvalidated numeric claim as an authoritative answer. */
    public function safeExplanation(int|float $previous, int|float $current, array $claim, string $unit = 'porsi'): array
    {
        $validation = $this->validateChangeClaim($previous, $current, $claim);
        return [
            'answer' => $this->calculator->explain($previous, $current, $unit),
            'calculation_verified' => true,
            'ai_claim_accepted' => $validation['accepted'],
            'ai_claim_reason' => $validation['reason'],
            'data_source_verified' => false,
        ];
    }
}
