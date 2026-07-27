<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classification_id')->constrained()->onDelete('restrict');
            $table->string('nama_berkas');
            $table->foreignId('status_id')->nullable()->default(1)->constrained()->onDelete('restrict');
            $table->integer('retensi_aktif')->nullable();
            $table->integer('retensi_inaktif')->nullable();
            $table->enum('keterangan_akhir', ['Permanen', 'Musnah'])->nullable();

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $this->addAlihMediaStatus($table);
            }

            $table->timestamps();
            $table->softDeletes();

            if (DB::connection()->getDriverName() === 'sqlite') {
                $this->addAlihMediaStatus($table);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filelists');
    }

    private function addAlihMediaStatus(Blueprint $table): void
    {
        $table->foreignId('alih_media_status_id')
            ->nullable()
            ->constrained('alih_media_statuses')
            ->onDelete('restrict');
    }
};
