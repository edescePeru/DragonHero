<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        $environment = isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : getenv('APP_ENV');
        $database = isset($_SERVER['DB_DATABASE']) ? $_SERVER['DB_DATABASE'] : getenv('DB_DATABASE');

        if ($environment !== 'testing') {
            throw new \RuntimeException('Pruebas bloqueadas: APP_ENV debe ser testing.');
        }

        if (! is_string($database) || substr($database, -8) !== '_testing') {
            throw new \RuntimeException('Pruebas bloqueadas: la base debe terminar en _testing.');
        }

        parent::setUp();

        $configuredDatabase = config('database.connections.'.config('database.default').'.database');

        if (! is_string($configuredDatabase) || substr($configuredDatabase, -8) !== '_testing') {
            throw new \RuntimeException('Pruebas bloqueadas: Laravel no resolvió una base aislada de testing.');
        }
    }
}
