<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AGENDA_INDEX_NAME = 'incomings_tahun_nomor_agenda_unique';

    public function up(): void
    {
        Schema::create('incomings', function (Blueprint $table) {
            $table->id();
            $table->integer('nomor_agenda')->nullable();
            $table->date('tanggal_diterima');
            $table->string('nomor_surat');
            $table->text('pengirim');
            $table->date('tanggal_surat')->nullable();
            $table->text('perihal');
            $table->string('url');
            $table->integer('tahun');

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

            $table->unique(['tahun', 'nomor_agenda'], self::AGENDA_INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomings');
    }

    private function addIsSrikandi(Blueprint $table): void
    {
        $table->boolean('is_srikandi')
            ->default(false)
            ->comment('1 = berasal dari SRIKANDI, 0 = penerimaan manual');
    }
};
