<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRollbackTest extends TestCase
{
    public function test_all_migrations_can_be_rolled_back_on_sqlite()
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        $this->artisan('migrate:fresh', ['--force' => true])
            ->assertExitCode(0);

        $this->artisan('migrate:rollback', ['--force' => true])
            ->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('filelists'));
        $this->assertFalse(Schema::hasTable('alih_media_statuses'));
    }
}
