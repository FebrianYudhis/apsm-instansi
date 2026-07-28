<?php

namespace App\Console\Commands;

use App\Services\ProductionIntegrityAuditor;
use Illuminate\Console\Command;
use Throwable;

class AuditProductionIntegrity extends Command
{
    protected $signature = 'audit:integritas-production
                            {--year= : Batasi pemeriksaan Surat Masuk/Keluar pada satu tahun}
                            {--format=table : Format output: table atau json}
                            {--no-orphans : Lewati pemindaian file yatim untuk audit cepat}';

    protected $description = 'Audit read-only integritas database dan file arsip production';

    public function handle(ProductionIntegrityAuditor $auditor): int
    {
        $year = $this->parseYear($this->option('year'));
        if ($year === false) {
            $this->error('Opsi --year harus berupa tahun 4 digit, contoh: --year=2026.');

            return self::INVALID;
        }

        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['table', 'json'], true)) {
            $this->error('Opsi --format hanya menerima table atau json.');

            return self::INVALID;
        }

        try {
            $report = $auditor->audit($year, ! $this->option('no-orphans'));
        } catch (Throwable $exception) {
            $this->error('Audit tidak dapat diselesaikan: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTableReport($report);
        }

        $hasReconciliationMismatch = $report['reconciliation']['synchronized'] === false;

        return count($report['findings']) === 0 && ! $hasReconciliationMismatch
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function parseYear($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! preg_match('/^\d{4}$/', (string) $value)) {
            return false;
        }

        $year = (int) $value;

        return $year >= 1900 && $year <= 2200 ? $year : false;
    }

    private function renderTableReport(array $report): void
    {
        $this->info('Audit Integritas Production (READ-ONLY)');
        $this->line($report['scope']['note']);
        $this->line('Disk arsip: '.$report['disk']);
        $this->newLine();

        $this->table(
            ['Objek', 'Jumlah'],
            [
                ['Surat Masuk', $report['counts']['incomings_checked']],
                ['Surat Keluar', $report['counts']['outcomings_checked']],
                ['Surat Digital', $report['counts']['digitals_checked']],
                ['Berkas', $report['counts']['filelists_checked']],
                ['File fisik dipindai', $report['counts']['files_scanned']],
            ]
        );

        $this->renderReconciliation($report['reconciliation']);

        $this->newLine();
        $this->line('Data soft deleted (informasi, tidak mengubah exit code):');
        $this->table(
            ['Objek', 'Jumlah', 'Contoh ID'],
            [
                $this->softDeletedRow('Surat Masuk', $report['soft_deleted']['incomings']),
                $this->softDeletedRow('Surat Keluar', $report['soft_deleted']['outcomings']),
                $this->softDeletedRow('Surat Digital', $report['soft_deleted']['digitals']),
                $this->softDeletedRow('Klasifikasi', $report['soft_deleted']['classifications']),
                $this->softDeletedRow('Berkas', $report['soft_deleted']['filelists']),
            ]
        );

        if (count($report['findings']) === 0
            && $report['reconciliation']['synchronized'] !== false) {
            $this->info('OK: tidak ditemukan masalah integritas.');

            return;
        }

        if (count($report['findings']) === 0) {
            return;
        }

        $rows = [];
        foreach ($report['findings'] as $finding) {
            $context = count($finding['context']) > 0
                ? json_encode($finding['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '-';
            $rows[] = [
                strtoupper($finding['severity']),
                $finding['code'],
                $finding['message'],
                $context,
            ];
        }

        $this->table(['Level', 'Kode', 'Temuan', 'Konteks'], $rows);
        $this->warn(
            'Ditemukan '.$report['counts']['errors'].' error dan '
            .$report['counts']['warnings'].' warning. Tidak ada data yang diubah.'
        );
    }

    private function renderReconciliation(array $reconciliation): void
    {
        $this->newLine();
        $this->line('Rekonsiliasi referensi database dan file fisik:');
        $this->line($reconciliation['scope']);

        $databaseRows = [];
        $labels = [
            'incomings' => 'Surat Masuk',
            'outcomings' => 'Surat Keluar',
            'digitals' => 'Surat Digital',
        ];

        foreach ($reconciliation['database'] as $type => $summary) {
            $watermark = $summary['watermark'];
            $problematic = $summary['rows'] - $summary['original']['valid_in_expected_root'];
            $duplicates = $summary['original']['duplicate_references'];

            if ($watermark !== null) {
                $problematic += $watermark['invalid_or_wrong_root'];
                $duplicates += $watermark['duplicate_references'];
            }

            $databaseRows[] = [
                $labels[$type] ?? $type,
                $summary['rows'],
                $summary['original']['valid_in_expected_root'],
                $watermark === null ? '-' : $watermark['valid_in_expected_root'],
                $problematic,
                $duplicates,
            ];
        }

        $this->table(
            ['Data', 'Row DB', 'Ref Asli Valid', 'Ref Alih Media Valid', 'Ref Bermasalah', 'Ref Duplikat'],
            $databaseRows
        );

        if ($reconciliation['storage'] === null) {
            $this->warn('Pemindaian isi folder dilewati karena opsi --no-orphans.');

            return;
        }

        $storageRows = [];
        foreach ($reconciliation['storage']['roots'] as $root => $summary) {
            $storageRows[] = [
                $root,
                $summary['references'],
                $summary['unique_references'],
                $summary['private_files'],
                $summary['missing_private_files'],
                $summary['orphan_private_files'],
                $summary['public_files'],
                $summary['synchronized'] ? 'SESUAI' : 'TIDAK SESUAI',
            ];
        }

        $totals = $reconciliation['storage']['totals'];
        $storageRows[] = [
            'TOTAL',
            $totals['references'],
            $totals['unique_references'],
            $totals['private_files'],
            $totals['missing_private_files'],
            $totals['orphan_private_files'],
            $totals['public_files'],
            $reconciliation['synchronized'] ? 'SESUAI' : 'TIDAK SESUAI',
        ];

        $this->table(
            ['Folder', 'Ref DB', 'Ref Unik', 'File Private', 'Hilang', 'Yatim', 'File Public', 'Status'],
            $storageRows
        );

        if ($reconciliation['synchronized']) {
            $this->info('SESUAI: seluruh referensi database cocok dengan file private dan tidak ada file yatim/public.');
        } else {
            $this->warn('TIDAK SESUAI: periksa kolom bermasalah, file hilang, file yatim, duplikasi referensi, atau file public.');
        }
    }

    private function softDeletedRow(string $label, array $summary): array
    {
        $ids = count($summary['sample_ids']) > 0
            ? implode(', ', $summary['sample_ids'])
            : '-';

        if ($summary['has_more']) {
            $ids .= ', ...';
        }

        return [$label, $summary['count'], $ids];
    }
}
