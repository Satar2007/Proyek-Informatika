<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\JsaExplicitChangeResponder;
use PHPUnit\Framework\TestCase;

final class JsaExplicitChangeResponderTest extends TestCase
{
    private JsaExplicitChangeResponder $responder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->responder = new JsaExplicitChangeResponder();
    }

    public function test_hypothetical_increase(): void
    {
        $r = $this->responder->respond('Jika penjualan kemarin 120 porsi dan hari ini 150 porsi, berapa persen kenaikannya?');
        self::assertNotNull($r);
        self::assertStringContainsString('naik 25%', $r['answer']);
        self::assertSame('user_hypothetical', $r['source']);
        self::assertSame('calculator:explicit_change', $r['engine']);
    }

    public function test_hypothetical_decrease(): void
    {
        $r = $this->responder->respond('Misalnya penjualan berubah dari 150 porsi menjadi 120 porsi. Berapa persen perubahan?');
        self::assertStringContainsString('turun 20%', $r['answer']);
    }

    public function test_unchanged_and_zero_base(): void
    {
        self::assertStringContainsString('0%', $this->responder->respond('Contoh: dari 80 porsi menjadi 80 porsi, berapa persen perubahan?')['answer']);
        self::assertStringContainsString('tidak dapat dihitung', $this->responder->respond('Misal dari 0 porsi menjadi 12 porsi, berapa persen perubahan?')['answer']);
    }

    public function test_decimal_percent(): void
    {
        self::assertStringContainsString('turun 33,33%', $this->responder->respond('Simulasi dari 90 ke 60 porsi, berapa persen perubahan?')['answer']);
    }

    public function test_actual_pos_queries_do_not_get_intercepted(): void
    {
        self::assertNull($this->responder->respond('Berapa omzet hari ini?'));
        self::assertNull($this->responder->respond('Penjualan kemarin 120 porsi hari ini 150 porsi berapa persen?'));
        self::assertNull($this->responder->respond('Berapa perubahan penjualan dari 120 menjadi 150 porsi?'));
        self::assertNull($this->responder->respond('Misalnya stok menu kopi 120 dan transaksi 150, berapa persen?'));
    }

    public function test_ambiguous_or_extra_numbers_rejected(): void
    {
        self::assertNull($this->responder->respond('Jika dari 120 porsi menjadi 150 porsi lalu 160 porsi, berapa persen?'));
        self::assertNull($this->responder->respond('Misal dari -120 porsi menjadi 150 porsi, berapa persen?'));
        self::assertNull($this->responder->respond('Jika dari 120 porsi menjadi 150 porsi, bagaimana rekomendasi stok?'));
        self::assertNull($this->responder->respond('Jika dari 120 porsi menjadi 150 porsi, berapa persen dan apakah laba meningkat?'));
    }

    public function test_no_mutation_metadata_and_source_caution(): void
    {
        $r = $this->responder->respond('Misal dari 10 transaksi menjadi 15 transaksi, berapa persen perubahan?');
        self::assertSame(false, $r['fallback']);
        self::assertSame(true, $r['calculation_verified']);
        self::assertSame(false, $r['data_source_verified']);
        self::assertStringContainsString('hipotetis', $r['answer']);
        self::assertStringContainsString('naik 50%', $r['answer']);
    }
}
