<?php

namespace Database\Seeders;

use App\Models\AlihMediaStatus;
use Illuminate\Database\Seeder;

class AlihMediaStatusSeeder extends Seeder
{
    public function run()
    {
        $statuses = [
            1 => 'Diproses',
            2 => 'Selesai',
            3 => 'Gagal',
            4 => 'Ditutup',
        ];

        foreach ($statuses as $id => $namaStatus) {
            AlihMediaStatus::updateOrCreate(
                ['id' => $id],
                ['nama_status' => $namaStatus]
            );
        }
    }
}
