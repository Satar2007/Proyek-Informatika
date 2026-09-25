<?php

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => 'satar_integrated_test',

    // Manual test tidak boleh menggunakan Midtrans Production.
    'MIDTRANS_IS_PRODUCTION' => 'false',

    // Dummy key khusus simulasi signature webhook.
    // Bukan credential Midtrans asli.
    'MIDTRANS_SERVER_KEY' => 'SATAR-TEST-SERVER-KEY-NOT-REAL',
    'MIDTRANS_CLIENT_KEY' => 'SATAR-TEST-CLIENT-KEY-NOT-REAL',
];

foreach ($testingEnvironment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}