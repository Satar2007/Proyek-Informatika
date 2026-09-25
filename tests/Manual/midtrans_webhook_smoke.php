<?php

require getcwd() . '/tests/Manual/testing_env.php';
require getcwd() . '/vendor/autoload.php';

$app = require getcwd() . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Menu;
use App\Models\Payment;
use App\Models\StockLog;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\TransactionService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$db = DB::connection();

if (
    $db->getDriverName() !== 'mysql'
    || $db->getDatabaseName() !== 'satar_integrated_test'
    || (bool) config('midtrans.is_production')
) {
    fwrite(
        STDERR,
        "STOP: tes hanya boleh memakai satar_integrated_test + Midtrans Sandbox.\n"
    );
    exit(1);
}

if (blank(config('midtrans.server_key'))) {
    fwrite(STDERR, "STOP: MIDTRANS_SERVER_KEY tidak tersedia.\n");
    exit(1);
}

foreach (
    [
        'users',
        'menus',
        'transactions',
        'transaction_details',
        'payments',
        'stock_logs',
    ] as $table
) {
    $info = $db->selectOne(
        'SELECT ENGINE AS engine
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = ?',
        [
            $db->getDatabaseName(),
            $db->getTablePrefix() . $table
        ]
    );

    if (
        !$info
        || strtolower((string) $info->engine) !== 'innodb'
    ) {
        fwrite(
            STDERR,
            "STOP: tabel {$table} harus InnoDB.\n"
        );
        exit(1);
    }
}

$failed = false;
$passed = 0;

$db->beginTransaction();

try {
    $menu = Menu::where('is_active', true)
        ->orderBy('id')
        ->lockForUpdate()
        ->first();

    $user = User::where('role', 'kasir')
        ->orderBy('id')
        ->first();

    if (!$menu || !$user) {
        throw new RuntimeException(
            'Perlu minimal satu menu aktif dan satu akun kasir.'
        );
    }

    /*
     * Seluruh perubahan berikut berada dalam outer transaction.
     * Pada akhir tes semuanya di-rollback.
     */
    $menu->harga = 10000;
    $menu->stok = 20;
    $menu->save();

    $transactionService = app(TransactionService::class);
    $paymentService = app(PaymentService::class);

    $kernel = app(Kernel::class);

    $stock = function () use ($menu): int {
        return (int) Menu::withTrashed()
            ->findOrFail($menu->id)
            ->stok;
    };

    $createPayment = function (
        int $qty = 1
    ) use (
        $transactionService,
        $paymentService,
        $menu,
        $user
    ): Payment {
        $transaction = $transactionService->buatTransaksi(
            [
                [
                    'menu_id' => $menu->id,
                    'qty' => $qty,
                ],
            ],
            (int) $user->id,
            0,
            'qris',
            'UJI WEBHOOK ROLLBACK'
        );

        $payment = $paymentService->buatPayment(
            $transaction,
            'qris'
        );

        /*
         * Simulasikan pembayaran sudah dimulai.
         * Tidak ada request ke Midtrans.
         */
        $payment->update([
            'midtrans_snap_token' => 'TEST-TOKEN-NOT-REAL',
        ]);

        return $payment->fresh([
            'transaction',
        ]);
    };

    $sign = function (
        string $orderId,
        string $statusCode,
        string $grossAmount
    ): string {
        return hash(
            'sha512',
            $orderId
            . $statusCode
            . $grossAmount
            . (string) config('midtrans.server_key')
        );
    };

    $postWebhook = function (
        array $payload
    ) use ($kernel): array {
        $request = Request::create(
            '/midtrans/notification',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            )
        );

        $response = $kernel->handle($request);

        $status = $response->getStatusCode();

        $body = json_decode(
            $response->getContent(),
            true
        );

        $kernel->terminate(
            $request,
            $response
        );

        return [$status, $body];
    };

    $run = function (
        string $name,
        callable $test
    ) use ($db, &$passed): void {
        /*
         * Tiap skenario diberi nested transaction.
         * Setelah skenario selesai, fixture skenario
         * dikembalikan ke kondisi sebelumnya.
         */
        $db->beginTransaction();

        try {
            $test();

            $passed++;

            echo "PASS {$passed}: {$name}\n";
        } finally {
            $db->rollBack();
        }
    };

    // ============================================================
    // TEST 1
    // Signature palsu harus ditolak oleh controller.
    // Jika CSRF masih aktif, biasanya request berhenti sebelum sini.
    // ============================================================

    $run(
        'signature salah ditolak dan route lolos CSRF',
        function () use ($postWebhook) {
            [$status, $body] = $postWebhook([
                'order_id' => 'PAY-FAKE-SIGNATURE',
                'status_code' => '200',
                'gross_amount' => '10000.00',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'signature_key' => str_repeat('0', 128),
            ]);

            if ($status !== 403) {
                throw new RuntimeException(
                    "Signature palsu menghasilkan HTTP {$status}, seharusnya 403."
                );
            }

            if (($body['received'] ?? null) !== false) {
                throw new RuntimeException(
                    'Payload signature palsu tidak ditolak.'
                );
            }
        }
    );

    // ============================================================
    // TEST 2
    // Order ID tidak dikenal tetapi signature valid -> 404.
    // ============================================================

    $run(
        'order ID tidak dikenal tidak mengubah database',
        function () use (
            $postWebhook,
            $sign,
            $stock
        ) {
            $beforeStock = $stock();

            $orderId = 'PAY-NOT-EXISTS';
            $gross = '10000.00';
            $code = '200';

            [$status] = $postWebhook([
                'order_id' => $orderId,
                'status_code' => $code,
                'gross_amount' => $gross,
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'signature_key' => $sign(
                    $orderId,
                    $code,
                    $gross
                ),
            ]);

            if ($status !== 404) {
                throw new RuntimeException(
                    "Order ID asing menghasilkan HTTP {$status}, seharusnya 404."
                );
            }

            if ($stock() !== $beforeStock) {
                throw new RuntimeException(
                    'Order ID asing mengubah stok.'
                );
            }
        }
    );

    // ============================================================
    // TEST 3
    // Nominal salah tetapi signature valid.
    // Reservasi harus tetap ditahan.
    // ============================================================

    $run(
        'nominal salah ditolak tanpa melepas reservasi',
        function () use (
            $createPayment,
            $postWebhook,
            $sign,
            $stock
        ) {
            $before = $stock();

            $payment = $createPayment();

            $afterReserve = $stock();

            if ($afterReserve !== $before - 1) {
                throw new RuntimeException(
                    'Fixture QRIS tidak mencadangkan stok tepat satu.'
                );
            }

            $wrongGross = number_format(
                (float) $payment->transaction->grand_total + 1000,
                2,
                '.',
                ''
            );

            $code = '200';

            [$status] = $postWebhook([
                'order_id' => $payment->external_id,
                'status_code' => $code,
                'gross_amount' => $wrongGross,
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'signature_key' => $sign(
                    $payment->external_id,
                    $code,
                    $wrongGross
                ),
            ]);

            if ($status !== 422) {
                throw new RuntimeException(
                    "Nominal salah menghasilkan HTTP {$status}, seharusnya 422."
                );
            }

            $payment->refresh();
            $payment->transaction->refresh();

            if (
                $payment->payment_status !== 'waiting'
                || $payment->transaction->status !== 'pending'
                || $payment->transaction
                    ->stock_reservation_status !== 'reserved'
            ) {
                throw new RuntimeException(
                    'Nominal salah mengubah status lokal.'
                );
            }

            if ($stock() !== $afterReserve) {
                throw new RuntimeException(
                    'Nominal salah mengubah stok.'
                );
            }
        }
    );

    // ============================================================
    // TEST 4
    // Pending valid tidak boleh menyelesaikan transaksi.
    // ============================================================

    $run(
        'status pending mempertahankan reservasi',
        function () use (
            $createPayment,
            $postWebhook,
            $sign,
            $stock
        ) {
            $before = $stock();

            $payment = $createPayment();

            $reserved = $stock();

            if ($reserved !== $before - 1) {
                throw new RuntimeException(
                    'Reservasi fixture pending gagal.'
                );
            }

            $gross = number_format(
                (float) $payment->transaction->grand_total,
                2,
                '.',
                ''
            );

            $code = '201';

            [$status, $body] = $postWebhook([
                'order_id' => $payment->external_id,
                'status_code' => $code,
                'gross_amount' => $gross,
                'transaction_status' => 'pending',
                'fraud_status' => 'accept',
                'signature_key' => $sign(
                    $payment->external_id,
                    $code,
                    $gross
                ),
            ]);

            if (
                $status !== 200
                || ($body['status'] ?? null) !== 'waiting'
            ) {
                throw new RuntimeException(
                    'Status pending tidak menghasilkan waiting.'
                );
            }

            $payment->refresh();
            $payment->transaction->refresh();

            if (
                $payment->payment_status !== 'waiting'
                || $payment->transaction
                    ->stock_reservation_status !== 'reserved'
                || $stock() !== $reserved
            ) {
                throw new RuntimeException(
                    'Pending mengubah reservasi atau stok.'
                );
            }
        }
    );

    // ============================================================
    // TEST 5
    // Settlement valid.
    // Stok sudah dikurangi saat reserve, jadi settlement
    // hanya mengubah reserved -> consumed.
    // ============================================================

    $run(
        'settlement mengonsumsi reservasi tepat sekali',
        function () use (
            $createPayment,
            $postWebhook,
            $sign,
            $stock
        ) {
            $before = $stock();

            $payment = $createPayment();

            $afterReserve = $stock();

            if ($afterReserve !== $before - 1) {
                throw new RuntimeException(
                    'Reservasi settlement tidak sesuai.'
                );
            }

            $gross = number_format(
                (float) $payment->transaction->grand_total,
                2,
                '.',
                ''
            );

            $code = '200';

            $payload = [
                'order_id' => $payment->external_id,
                'status_code' => $code,
                'gross_amount' => $gross,
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'signature_key' => $sign(
                    $payment->external_id,
                    $code,
                    $gross
                ),
            ];

            [$status, $body] = $postWebhook($payload);

            if (
                $status !== 200
                || ($body['status'] ?? null) !== 'paid'
            ) {
                throw new RuntimeException(
                    'Settlement tidak menghasilkan status paid.'
                );
            }

            $payment->refresh();
            $payment->transaction->refresh();

            if (
                $payment->payment_status !== 'paid'
                || $payment->transaction->status !== 'success'
                || $payment->transaction->payment_status !== 'paid'
                || $payment->transaction
                    ->stock_reservation_status !== 'consumed'
            ) {
                throw new RuntimeException(
                    'Status lokal settlement tidak konsisten.'
                );
            }

            if ($stock() !== $afterReserve) {
                throw new RuntimeException(
                    'Settlement mengurangi stok untuk kedua kali.'
                );
            }

            /*
             * Kirim webhook settlement yang sama lagi.
             * Tidak boleh memproses stok atau status lagi.
             */
            $stockBeforeDuplicate = $stock();
            $logsBeforeDuplicate = StockLog::count();

            [$status2, $body2] = $postWebhook($payload);

            if (
                $status2 !== 200
                || ($body2['status'] ?? null) !== 'paid'
            ) {
                throw new RuntimeException(
                    'Webhook settlement duplikat tidak idempotent.'
                );
            }

            if (
                $stock() !== $stockBeforeDuplicate
                || StockLog::count() !== $logsBeforeDuplicate
            ) {
                throw new RuntimeException(
                    'Webhook settlement duplikat memproses stok lagi.'
                );
            }
        }
    );

    // ============================================================
    // TEST 6
    // Expire valid harus mengembalikan reservasi.
    // ============================================================

    $run(
        'expire melepas reservasi tepat sekali',
        function () use (
            $createPayment,
            $postWebhook,
            $sign,
            $stock
        ) {
            $before = $stock();

            $payment = $createPayment();

            if ($stock() !== $before - 1) {
                throw new RuntimeException(
                    'Fixture expire tidak mencadangkan stok.'
                );
            }

            $gross = number_format(
                (float) $payment->transaction->grand_total,
                2,
                '.',
                ''
            );

            $code = '407';

            $payload = [
                'order_id' => $payment->external_id,
                'status_code' => $code,
                'gross_amount' => $gross,
                'transaction_status' => 'expire',
                'fraud_status' => 'accept',
                'signature_key' => $sign(
                    $payment->external_id,
                    $code,
                    $gross
                ),
            ];

            [$status, $body] = $postWebhook($payload);

            if (
                $status !== 200
                || ($body['status'] ?? null) !== 'expired'
            ) {
                throw new RuntimeException(
                    'Expire tidak menghasilkan expired.'
                );
            }

            $payment->refresh();
            $payment->transaction->refresh();

            if (
                $payment->payment_status !== 'expired'
                || $payment->transaction->status !== 'expired'
                || $payment->transaction->payment_status !== 'expired'
                || $payment->transaction
                    ->stock_reservation_status !== 'released'
            ) {
                throw new RuntimeException(
                    'Status lokal expire tidak konsisten.'
                );
            }

            if ($stock() !== $before) {
                throw new RuntimeException(
                    'Expire tidak mengembalikan stok.'
                );
            }

            /*
             * Duplikat expire tidak boleh mengembalikan stok kedua kali.
             */
            $stockBeforeDuplicate = $stock();
            $logsBeforeDuplicate = StockLog::count();

            [$status2, $body2] = $postWebhook($payload);

            if (
                $status2 !== 200
                || ($body2['status'] ?? null) !== 'expired'
            ) {
                throw new RuntimeException(
                    'Webhook expire duplikat tidak idempotent.'
                );
            }

            if (
                $stock() !== $stockBeforeDuplicate
                || StockLog::count() !== $logsBeforeDuplicate
            ) {
                throw new RuntimeException(
                    'Expire duplikat mengembalikan stok lagi.'
                );
            }
        }
    );

    echo "SEMUA {$passed} SKENARIO WEBHOOK LULUS.\n";
} catch (Throwable $e) {
    $failed = true;

    fwrite(
        STDERR,
        'FAIL: '
        . get_class($e)
        . ' - '
        . $e->getMessage()
        . "\n"
    );
} finally {
    while ($db->transactionLevel() > 0) {
        $db->rollBack();
    }

    echo "ROLLBACK: seluruh perubahan data webhook test dibatalkan.\n";
}

exit($failed ? 1 : 0);