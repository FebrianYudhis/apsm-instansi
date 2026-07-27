<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            AccessSeeder::class,
            StatusSeeder::class,
            AlihMediaStatusSeeder::class,
            UserSeeder::class,
        ]);
        // \App\Models\Classification::factory(10)->create();
        // \App\Models\Filelist::factory(100)->create();
        // \App\Models\Incoming::factory(800)->create();
        // \App\Models\Outcoming::factory(800)->create();
        // \App\Models\Digital::factory(150)->create();
    }
}
