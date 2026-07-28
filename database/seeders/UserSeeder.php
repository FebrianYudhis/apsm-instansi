<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admin = User::where('username', 'admin')->first();

        if ($admin) {
            if ((int) $admin->id !== 1) {
                throw new \RuntimeException(
                    'Seeder user dibatalkan: username admin harus menggunakan ID 1.'
                );
            }

            return;
        }

        if (User::whereKey(1)->exists()) {
            throw new \RuntimeException(
                'Seeder user dibatalkan: ID 1 sudah digunakan oleh user lain.'
            );
        }

        $admin = new User([
            'username' => 'admin',
            'name' => 'Admin Aplikasi',
            'email' => 'admin@app.test',
            'password' => Hash::make('admin123'),
        ]);
        $admin->id = 1;
        $admin->save();
    }
}
