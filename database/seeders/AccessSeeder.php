<?php

namespace Database\Seeders;

use App\Models\Access;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sifatAkses = [
            1 => 'Biasa',
            2 => 'Terbatas',
            3 => 'Rahasia',
            4 => 'Sangat Rahasia',
        ];

        foreach ($sifatAkses as $id => $sifat) {
            $idConflict = Access::whereKey($id)
                ->where('sifat_akses', '!=', $sifat)
                ->exists();
            $nameConflict = Access::where('sifat_akses', $sifat)
                ->where('id', '!=', $id)
                ->exists();

            if ($idConflict || $nameConflict) {
                throw new \RuntimeException(
                    'Seeder akses dibatalkan: ID '.$id
                    .' harus digunakan oleh sifat akses '.$sifat.'.'
                );
            }

            Access::updateOrCreate(
                ['id' => $id],
                ['sifat_akses' => $sifat]
            );
        }
    }
}
