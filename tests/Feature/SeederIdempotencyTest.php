<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\AlihMediaStatus;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_can_run_repeatedly_without_duplicates_or_reset_admin()
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('username', 'admin')->firstOrFail();
        $admin->update([
            'name' => 'Admin Production',
            'password' => Hash::make('password-production'),
            'tahun' => 2024,
        ]);

        $this->seed(DatabaseSeeder::class);
        $admin->refresh();

        $this->assertSame(4, Access::count());
        $this->assertSame(7, Status::count());
        $this->assertSame(4, AlihMediaStatus::count());
        $this->assertSame(
            [
                1 => 'Biasa',
                2 => 'Terbatas',
                3 => 'Rahasia',
                4 => 'Sangat Rahasia',
            ],
            Access::orderBy('id')->pluck('sifat_akses', 'id')->all()
        );
        $this->assertSame(
            [
                1 => 'Aktif',
                2 => 'Usul Pindah UP ke UK',
                3 => 'Inaktif',
                4 => 'Usul Musnah',
                5 => 'Musnah',
                6 => 'Usul Permanen',
                7 => 'Permanen',
            ],
            Status::orderBy('id')->pluck('nama_status', 'id')->all()
        );
        $this->assertSame(1, User::where('username', 'admin')->count());
        $this->assertSame(1, (int) $admin->id);
        $this->assertSame('Admin Production', $admin->name);
        $this->assertSame(2024, (int) $admin->tahun);
        $this->assertTrue(Hash::check('password-production', $admin->password));
    }
}
