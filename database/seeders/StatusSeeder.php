<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $namaStatus = [
            1 => 'Aktif',
            2 => 'Usul Pindah UP ke UK',
            3 => 'Inaktif',
            4 => 'Usul Musnah',
            5 => 'Musnah',
            6 => 'Usul Permanen',
            7 => 'Permanen',
        ];

        foreach ($namaStatus as $id => $status) {
            $idConflict = Status::whereKey($id)
                ->where('nama_status', '!=', $status)
                ->exists();
            $nameConflict = Status::where('nama_status', $status)
                ->where('id', '!=', $id)
                ->exists();

            if ($idConflict || $nameConflict) {
                throw new \RuntimeException(
                    'Seeder status dibatalkan: ID '.$id
                    .' harus digunakan oleh status '.$status.'.'
                );
            }

            Status::updateOrCreate(
                ['id' => $id],
                ['nama_status' => $status]
            );
        }
    }
}
