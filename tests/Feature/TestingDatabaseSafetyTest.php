<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingDatabaseSafetyTest extends TestCase
{
    public function test_tests_run_in_the_expected_isolated_database()
    {
        $connection = config('database.default');

        $this->assertSame('testing', app()->environment());
        $this->assertSame(
            'A4gamesDH_testing',
            config('database.connections.'.$connection.'.database')
        );
    }
}
