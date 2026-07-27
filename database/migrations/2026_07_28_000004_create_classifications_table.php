<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'classifications_active_code_unique';

    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table) {
            $table->id();
            $table->string('kode_klasifikasi');
            $table->string('keterangan');
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(
                'ALTER TABLE classifications ADD COLUMN active_unique_key TEXT '
                .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL '
                .'THEN UPPER(TRIM(kode_klasifikasi)) ELSE NULL END) VIRTUAL'
            );
        } else {
            DB::statement(
                'ALTER TABLE classifications ADD COLUMN active_unique_key VARCHAR(255) '
                .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL '
                .'THEN UPPER(TRIM(kode_klasifikasi)) ELSE NULL END) STORED'
            );
        }

        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDEX_NAME
            .' ON classifications (active_unique_key)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('classifications');
    }
};
