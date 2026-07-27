<?php

namespace Database\Factories;

use App\Models\Filelist;
use App\Models\Outcoming;
use Carbon\Carbon;
use Database\Factories\Concerns\CreatesDummyPdf;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutcomingFactory extends Factory
{
    use CreatesDummyPdf;

    public function configure()
    {
        return $this->afterCreating(function (Outcoming $outcoming) {
            $this->createDummyPdf($outcoming, 'dokumen/keluar');
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $startYear = (int) config('app.start_year', 2025);
        $currentYear = Carbon::now()->year;
        $startYear = min($startYear, $currentYear);
        $years = range($startYear, $currentYear);

        return [
            'tanggal_surat' => $this->faker->date(),
            'nomor_surat' => 'SRT'.$this->faker->randomNumber(4).'/'.$this->faker->numberBetween(1, 12).'/'.$this->faker->year(),
            'tujuan' => $this->faker->company(),
            'perihal' => $this->faker->sentence(),
            'url' => $this->dummyPdfPath('dokumen/keluar', 'surat-keluar'),
            'tahun' => $this->faker->randomElement($years),
            'is_digital' => false,
            'is_srikandi' => false,
            'filelist_id' => null,
        ];
    }

    public function withBerkas()
    {
        return $this->state(function (array $attributes) {
            return [
                'filelist_id' => ! empty($attributes['is_srikandi'])
                    ? null
                    : Filelist::factory(),
            ];
        });
    }

    public function withoutBerkas()
    {
        return $this->state(function () {
            return ['filelist_id' => null];
        });
    }

    public function digital()
    {
        return $this->state(function () {
            return [
                'is_digital' => true,
                'is_srikandi' => false,
            ];
        });
    }

    public function srikandi()
    {
        return $this->state(function () {
            return [
                'is_digital' => true,
                'is_srikandi' => true,
                'filelist_id' => null,
            ];
        });
    }
}
