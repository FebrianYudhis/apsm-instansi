<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alih_media_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('nama_status')->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('alih_media_statuses')->insert([
            ['id' => 1, 'nama_status' => 'Diproses', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'nama_status' => 'Selesai', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'nama_status' => 'Gagal', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'nama_status' => 'Ditutup', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('alih_media_statuses');
    }
};
