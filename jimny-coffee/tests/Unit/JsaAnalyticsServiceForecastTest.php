<?php

namespace Tests\Unit;

use App\Services\JsaAnalyticsService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class JsaAnalyticsServiceForecastTest extends TestCase
{
    private function evaluateWma(array $trend, int $activeDays): array
    {
        $service = new JsaAnalyticsService();

        $method = new ReflectionMethod(
            JsaAnalyticsService::class,
            'evaluateWma'
        );

        return $method->invoke(
            $service,
            $trend,
            $activeDays
        );
    }

    private function makeTrend(
        int $days,
        string $start = '2026-01-01'
    ): array {
        $startDate = new DateTimeImmutable($start);
        $trend = [];

        for ($index = 0; $index < $days; $index++) {
            $trend[] = [
                'date' => $startDate
                    ->modify("+{$index} days")
                    ->format('Y-m-d'),

                'qty' => 10 + ($index % 7),
            ];
        }

        return $trend;
    }

    public function test_wma_returns_insufficient_data_when_history_is_too_short(): void
    {
        $trend = $this->makeTrend(20);

        $result = $this->evaluateWma(
            $trend,
            20
        );

        $this->assertSame(
            'insufficient_data',
            $result['forecast']['status']
        );

        $this->assertSame(
            21,
            $result['forecast']['minimum_calendar_days']
        );

        $this->assertNull(
            $result['model_evaluation']
        );
    }

    public function test_wma_generates_next_day_forecast_from_last_history_date(): void
    {
        $trend = $this->makeTrend(35);

        $result = $this->evaluateWma(
            $trend,
            35
        );

        $lastDate = new DateTimeImmutable(
            $trend[array_key_last($trend)]['date']
        );

        $expectedDate = $lastDate
            ->modify('+1 day')
            ->format('Y-m-d');

        $this->assertSame(
            'experimental',
            $result['forecast']['status']
        );

        $this->assertSame(
            'WMA-7',
            $result['forecast']['method']
        );

        $this->assertSame(
            $expectedDate,
            $result['forecast']['values'][0]['date']
        );
    }

    public function test_wma_uses_fourteen_day_backtest_and_compares_naive_baseline(): void
    {
        $trend = $this->makeTrend(35);

        $result = $this->evaluateWma(
            $trend,
            35
        );

        $this->assertCount(
            14,
            $result['forecast']['backtest']
        );

        $this->assertSame(
            14,
            $result['model_evaluation']['days']
        );

        $this->assertArrayHasKey(
            'wma_mae',
            $result['model_evaluation']
        );

        $this->assertArrayHasKey(
            'naive_mae',
            $result['model_evaluation']
        );

        $this->assertIsBool(
            $result['model_evaluation']['wma_better_than_naive']
        );
    }
}
