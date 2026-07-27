<?php

namespace Database\Factories;

use App\Models\Filelist;
use App\Models\Incoming;
use Carbon\Carbon;
use Database\Factories\Concerns\CreatesDummyPdf;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncomingFactory extends Factory
{
    use CreatesDummyPdf;

    public function configure()
    {
        return $this->afterCreating(function (Incoming $incoming) {
            $this->createDummyPdf($incoming, 'dokumen/masuk');
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
            'nomor_agenda' => $this->faker->unique()->numberBetween(1, 10000),
            'tanggal_diterima' => $this->faker->date(),
            'nomor_surat' => 'SRT'.$this->faker->randomNumber(4).'/'.$this->faker->numberBetween(1, 12).'/'.$this->faker->year(),
            'pengirim' => $this->faker->company(),
            'tanggal_surat' => $this->faker->date(),
            'perihal' => $this->faker->sentence(),
            'url' => $this->dummyPdfPath('dokumen/masuk', 'surat-masuk'),
            'tahun' => $this->faker->randomElement($years),
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

    public function srikandi()
    {
        return $this->state(function () {
            return [
                'nomor_agenda' => null,
                'is_srikandi' => true,
                'filelist_id' => null,
            ];
        });
    }
}
