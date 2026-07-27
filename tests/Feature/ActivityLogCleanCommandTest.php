<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

pest()->use(RefreshDatabase::class);

test('it only deletes activity logs older than 12 months', function () {
    Date::setTestNow('2026-07-28 12:00:00');

    $table = config('activitylog.table_name');

    DB::connection(config('activitylog.database_connection'))->table($table)->insert([
        [
            'description' => 'Lebih lama dari 12 bulan',
            'created_at' => now()->subMonthsNoOverflow(12)->subSecond(),
            'updated_at' => now(),
        ],
        [
            'description' => 'Tepat 12 bulan',
            'created_at' => now()->subMonthsNoOverflow(12),
            'updated_at' => now(),
        ],
        [
            'description' => 'Kurang dari 12 bulan',
            'created_at' => now()->subMonthsNoOverflow(11),
            'updated_at' => now(),
        ],
    ]);

    $this->artisan('activitylog:clean')
        ->expectsOutput('1 log aktivitas lebih dari 12 bulan telah dihapus.')
        ->assertSuccessful();

    expect(DB::table($table)->pluck('description')->all())->toBe([
        'Tepat 12 bulan',
        'Kurang dari 12 bulan',
    ]);

    Date::setTestNow();
});
