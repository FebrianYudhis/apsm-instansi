<?php

namespace Database\Factories;

use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

class FilelistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $statusId = Status::inRandomOrder()->value('id');

        if ($statusId === null) {
            throw new LogicException(
                'Status master belum tersedia. Jalankan DatabaseSeeder sebelum memakai FilelistFactory.'
            );
        }

        return [
            'classification_id' => Classification::factory(),
            'nama_berkas' => $this->faker->word(),
            'status_id' => $statusId,
            'retensi_aktif' => 1,
            'retensi_inaktif' => 1,
            'keterangan_akhir' => $this->faker->randomElement(['Permanen', 'Musnah']),
            'alih_media_status_id' => null,
        ];
    }

    public function alihMediaProcessing()
    {
        return $this->state(function () {
            return ['alih_media_status_id' => Filelist::ALIH_MEDIA_PROCESSING];
        });
    }

    public function alihMediaDone()
    {
        return $this->state(function () {
            return ['alih_media_status_id' => Filelist::ALIH_MEDIA_DONE];
        });
    }

    public function alihMediaFailed()
    {
        return $this->state(function () {
            return ['alih_media_status_id' => Filelist::ALIH_MEDIA_FAILED];
        });
    }

    public function alihMediaClosed()
    {
        return $this->state(function () {
            return ['alih_media_status_id' => Filelist::ALIH_MEDIA_CLOSED];
        });
    }
}
