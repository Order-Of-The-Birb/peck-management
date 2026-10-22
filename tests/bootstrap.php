<?php

/*
|--------------------------------------------------------------------------
| PHPUnit Bootstrap
|--------------------------------------------------------------------------
|
| Laravel loads the project's `.env` file (through `php artisan test`)
| before PHPUnit reads phpunit.xml, so `.env` values win over the
| phpunit.xml `<env>` entries. We force the testing values in every
| source Dotenv reads from (`$_SERVER` takes priority) before the app is
| re-booted for each test.
|
*/

$testingEnv = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($testingEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
