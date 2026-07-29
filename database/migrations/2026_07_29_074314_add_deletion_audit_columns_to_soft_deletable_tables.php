<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'incomings',
        'outcomings',
        'digitals',
        'classifications',
        'filelists',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('deleted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->text('deletion_reason')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('deleted_by_user_id');
                $table->dropColumn('deletion_reason');
            });
        }
    }
};
