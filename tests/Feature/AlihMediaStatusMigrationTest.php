<?php

use App\Models\AlihMediaStatus;
use Database\Seeders\AlihMediaStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('migration creates alih media statuses table without reference rows', function () {
    expect(AlihMediaStatus::query()->count())->toBe(0);
});

test('seeder creates alih media statuses idempotently', function () {
    $this->seed(AlihMediaStatusSeeder::class);
    $this->seed(AlihMediaStatusSeeder::class);

    expect(
        AlihMediaStatus::query()
            ->orderBy('id')
            ->pluck('nama_status', 'id')
            ->all()
    )->toBe([
        1 => 'Diproses',
        2 => 'Selesai',
        3 => 'Gagal',
        4 => 'Ditutup',
    ]);
});
