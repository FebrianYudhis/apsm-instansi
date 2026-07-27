<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('activitylog:clean')]
#[Description('Menghapus log aktivitas yang lebih lama dari 12 bulan')]
class CleanActivityLog extends Command
{
    public function handle(): int
    {
        $deletedCount = DB::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'))
            ->where('created_at', '<', now()->subMonthsNoOverflow(12))
            ->delete();

        $this->info("{$deletedCount} log aktivitas lebih dari 12 bulan telah dihapus.");

        return self::SUCCESS;
    }
}
