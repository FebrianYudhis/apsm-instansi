<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClassificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'kode_klasifikasi' => substr(strtoupper($this->faker->word()), 0, 2).'.0'.$this->faker->randomDigit().'.0'.$this->faker->randomDigit(),
            'keterangan' => $this->faker->realText(40),
        ];
    }
}
