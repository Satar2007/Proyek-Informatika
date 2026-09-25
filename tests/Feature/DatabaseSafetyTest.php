<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    public function test_phpunit_uses_only_dedicated_test_database(): void
    {
        $connection = DB::connection();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertSame('satar_integrated_test', $connection->getDatabaseName());
    }
}
