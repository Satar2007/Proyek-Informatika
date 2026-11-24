<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\JsaAnswerValidator;
use App\Services\JsaBusinessCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JsaBusinessAccuracyTest extends TestCase
{
    private JsaBusinessCalculator $calculator;
    private JsaAnswerValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new JsaBusinessCalculator();
        $this->validator = new JsaAnswerValidator($this->calculator);
    }

    public function test_increase(): void
    {
        $this->assertSame('naik', $this->calculator->change(120, 150)['direction']);
        $this->assertSame(25.0, $this->calculator->change(120, 150)['percentage']);
        $this->assertStringContainsString('naik 25%', $this->calculator->explain(120, 150));
    }

    public function test_decrease(): void
    {
        $this->assertSame(-20.0, $this->calculator->change(150, 120)['percentage']);
        $this->assertStringContainsString('turun 20%', $this->calculator->explain(150, 120));
    }

    public function test_unchanged(): void
    {
        $this->assertSame('tetap', $this->calculator->change(80, 80)['direction']);
        $this->assertSame(0.0, $this->calculator->change(80, 80)['percentage']);
    }

    public function test_one_third_decrease(): void
    {
        $this->assertSame(-33.33, $this->calculator->change(90, 60)['percentage']);
    }

    public function test_zero_denominator(): void
    {
        $this->assertNull($this->calculator->change(0, 12)['percentage']);
        $this->assertNull($this->calculator->change(0, 0)['percentage']);
        $this->assertStringContainsString('tidak dapat dihitung', $this->calculator->explain(0, 12));
    }

    public function test_rejects_negative_and_nonfinite(): void
    {
        foreach ([[-1, 3], [INF, 3], [3, NAN]] as [$a, $b]) {
            try {
                $this->calculator->change($a, $b);
                $this->fail('Invalid number accepted');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_validates_structured_claim(): void
    {
        $this->assertTrue($this->validator->validateChangeClaim(120, 150, ['direction' => 'naik', 'percentage' => 25])['accepted']);
        $this->assertFalse($this->validator->validateChangeClaim(120, 150, ['direction' => 'turun', 'percentage' => 25])['accepted']);
        $this->assertFalse($this->validator->validateChangeClaim(80, 80, ['direction' => 'naik', 'percentage' => 100])['accepted']);
        $this->assertTrue($this->validator->validateChangeClaim(0, 12, ['direction' => 'naik', 'percentage' => null])['accepted']);
        $this->assertFalse($this->validator->validateChangeClaim(0, 12, ['direction' => 'naik', 'percentage' => 100])['accepted']);
    }

    public function test_rejects_missing_and_untyped_claims(): void
    {
        $this->assertFalse($this->validator->validateChangeClaim(120, 150, [])['accepted']);
        $this->assertFalse($this->validator->validateChangeClaim(120, 150, ['direction' => 'naik', 'percentage' => '25%'])['accepted']);
    }

    public function test_safe_explanation_never_repeats_wrong_ai_claim(): void
    {
        $result = $this->validator->safeExplanation(80, 80, ['direction' => 'naik', 'percentage' => 100]);
        $this->assertFalse($result['ai_claim_accepted']);
        $this->assertSame('Nilai tetap 80 porsi; selisih 0 porsi dan perubahan 0%.', $result['answer']);
        $this->assertFalse($result['data_source_verified']);
    }
}
