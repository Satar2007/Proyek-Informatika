<?php

return [
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    'server_key' => env('MIDTRANS_SERVER_KEY'),

    // Dedicated notification signing secret. No fallback to Server Key:
    // production webhook must be explicitly configured with this credential.
    'signature_secret_key' => env('MIDTRANS_SIGNATURE_SECRET_KEY'),

    // Snap transaction expiry must be between 5 minutes and 7 days.
    'snap_expiry_minutes' => (int) env('MIDTRANS_SNAP_EXPIRY_MINUTES', 5),

    // All Snap-token creation, cancellation and expiry flows must use the
    // same shared lock store. Use database or Redis in multi-worker deployments.
    'lock_store' => env('MIDTRANS_LOCK_STORE', env('CACHE_STORE', 'database')),
    'lock_seconds' => max(60, min(300, (int) env('MIDTRANS_LOCK_SECONDS', 180))),

    'client_key' => env('MIDTRANS_CLIENT_KEY'),

    'snap_js_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js',
];
