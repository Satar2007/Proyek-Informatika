<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local development seed accounts
    |--------------------------------------------------------------------------
    |
    | Disabled by default. UserSeeder also checks APP_ENV=local, so these
    | credentials cannot be created by a production seeding command.
    |
    */
    'seed_default_users' => env('JIMNY_SEED_DEFAULT_USERS', false),
    'seed_default_password' => env('JIMNY_SEED_DEFAULT_PASSWORD'),
];
