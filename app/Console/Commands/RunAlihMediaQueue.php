<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunAlihMediaQueue extends Command
{
    protected $signature = 'alih-media:queue
        {--once : Jalankan satu job saja lalu berhenti}
        {--stop-when-empty : Berhenti setelah semua antrean kosong}';

    protected $description = 'Menjalankan worker queue database untuk proses alih media';

    public function handle()
    {
        $this->info('Menjalankan queue alih media...');

        return $this->call('queue:work', [
            'connection' => 'database',
            '--queue' => 'default',
            '--tries' => 1,
            '--timeout' => 900,
            '--once' => (bool) $this->option('once'),
            '--stop-when-empty' => (bool) $this->option('stop-when-empty'),
        ]);
    }
}
