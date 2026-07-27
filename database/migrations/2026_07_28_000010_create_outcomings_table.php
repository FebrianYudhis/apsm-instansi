<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outcomings', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_surat');
            $table->string('nomor_surat');
            $table->text('tujuan');
            $table->text('perihal');
            $table->string('url');
            $table->integer('tahun');
            $table->boolean('is_digital')->default(false);

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $this->addIsSrikandi($table);
            }

            $table->string('url_watermarked')->nullable();
            $table->foreignId('access_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('filelist_id')->nullable()->constrained()->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            if (DB::connection()->getDriverName() === 'sqlite') {
                $this->addIsSrikandi($table);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outcomings');
    }

    private function addIsSrikandi(Blueprint $table): void
    {
        $table->boolean('is_srikandi')
            ->default(false)
            ->comment('1 = SRIKANDI, 0 = pengiriman manual');
    }
};
