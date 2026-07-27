<?php

namespace Database\Factories;

use App\Models\Digital;
use Database\Factories\Concerns\CreatesDummyPdf;
use Illuminate\Database\Eloquent\Factories\Factory;

class DigitalFactory extends Factory
{
    use CreatesDummyPdf;

    public function configure()
    {
        return $this->afterCreating(function (Digital $digital) {
            $this->createDummyPdf($digital, 'dokumen/digital');
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'perihal' => $this->faker->sentence(),
            'url' => $this->dummyPdfPath('dokumen/digital', 'surat-digital'),
        ];
    }
}
