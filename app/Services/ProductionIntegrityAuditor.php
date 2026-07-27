<?php

namespace App\Services;

use App\Models\Filelist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductionIntegrityAuditor
{
    private const DOCUMENT_ROOTS = [
        'dokumen/masuk',
        'dokumen/keluar',
        'dokumen/digital',
        'dokumen/alih-media',
    ];

    private $disk;

    private $publicDisk;

    private $findings = [];

    private $filesScanned = 0;

    public function __construct()
    {
        $this->disk = Storage::disk(config('documents.disk'));
        $this->publicDisk = Storage::disk('public');
    }

    public function audit(?int $year = null, bool $scanOrphans = true): array
    {
        $this->findings = [];
        $this->filesScanned = 0;

        $incomings = DB::table('incomings')->get();
        $outcomings = DB::table('outcomings')->get();
        $digitals = DB::table('digitals')->get();
        $classifications = DB::table('classifications')->get();
        $filelists = DB::table('filelists')->get();

        $selectedIncomings = $incomings->filter(function ($row) use ($year) {
            return $year === null || (int) $row->tahun === $year;
        });
        $selectedOutcomings = $outcomings->filter(function ($row) use ($year) {
            return $year === null || (int) $row->tahun === $year;
        });

        $references = $this->collectAllDocumentReferences($incomings, $outcomings, $digitals);
        $this->auditDocuments($selectedIncomings, 'surat_masuk', 'dokumen/masuk');
        $this->auditDocuments($selectedOutcomings, 'surat_keluar', 'dokumen/keluar');
        $this->auditDocuments($digitals, 'surat_digital', 'dokumen/digital', false);
        $this->auditIncomingSrikandiState($selectedIncomings);
        $this->auditAgendaDuplicates($selectedIncomings);
        $this->auditRelations($selectedIncomings, $selectedOutcomings, $filelists);
        $this->auditAlihMedia($filelists, $incomings, $outcomings);

        if ($scanOrphans) {
            $this->auditOrphanFiles($references);
        }

        usort($this->findings, function (array $left, array $right) {
            $severityOrder = ['error' => 0, 'warning' => 1];
            $severityComparison = ($severityOrder[$left['severity']] ?? 9)
                <=> ($severityOrder[$right['severity']] ?? 9);

            if ($severityComparison !== 0) {
                return $severityComparison;
            }

            return [$left['code'], $left['message']] <=> [$right['code'], $right['message']];
        });

        $errors = count(array_filter($this->findings, function (array $finding) {
            return $finding['severity'] === 'error';
        }));
        $warnings = count($this->findings) - $errors;
        $softDeleted = [
            'incomings' => $this->summarizeSoftDeleted($selectedIncomings),
            'outcomings' => $this->summarizeSoftDeleted($selectedOutcomings),
            'digitals' => $this->summarizeSoftDeleted($digitals),
            'classifications' => $this->summarizeSoftDeleted($classifications),
            'filelists' => $this->summarizeSoftDeleted($filelists),
        ];

        return [
            'read_only' => true,
            'disk' => config('documents.disk'),
            'scope' => [
                'year' => $year,
                'orphans_scanned' => $scanOrphans,
                'note' => $year === null
                    ? 'Semua tahun, seluruh Surat Digital, dan seluruh Berkas.'
                    : 'Surat Masuk/Keluar tahun '.$year.'; Surat Digital dan Berkas tetap diperiksa seluruhnya.',
            ],
            'counts' => [
                'incomings_checked' => $selectedIncomings->count(),
                'outcomings_checked' => $selectedOutcomings->count(),
                'digitals_checked' => $digitals->count(),
                'filelists_checked' => $filelists->count(),
                'files_scanned' => $this->filesScanned,
                'errors' => $errors,
                'warnings' => $warnings,
            ],
            'soft_deleted' => $softDeleted,
            'findings' => $this->findings,
        ];
    }

    private function summarizeSoftDeleted($rows): array
    {
        $ids = $rows
            ->filter(function ($row) {
                return ! empty($row->deleted_at);
            })
            ->pluck('id')
            ->values();

        return [
            'count' => $ids->count(),
            'sample_ids' => $ids->take(20)->all(),
            'has_more' => $ids->count() > 20,
        ];
    }

    private function auditDocuments($rows, string $type, string $expectedRoot, bool $hasWatermark = true): void
    {
        foreach ($rows as $row) {
            $this->auditDocumentPath($type, $row, 'url', $expectedRoot);

            if ($hasWatermark && ! empty($row->url_watermarked)) {
                $this->auditDocumentPath($type, $row, 'url_watermarked', 'dokumen/alih-media');
            }
        }
    }

    private function auditDocumentPath(string $type, $row, string $column, string $expectedRoot): void
    {
        $path = $this->normalizePath($row->{$column} ?? null);
        $context = [
            'record_type' => $type,
            'record_id' => $row->id,
            'column' => $column,
            'path' => $row->{$column} ?? null,
            'soft_deleted' => ! empty($row->deleted_at),
        ];

        if ($path === null) {
            $this->addFinding(
                empty($row->deleted_at) ? 'error' : 'warning',
                'document.invalid_path',
                $this->recordLabel($type, $row->id).' memiliki path '.$column.' kosong atau tidak aman.',
                $context
            );

            return;
        }

        if (! $this->isWithinRoot($path, $expectedRoot)) {
            $this->addFinding(
                empty($row->deleted_at) ? 'error' : 'warning',
                'document.unexpected_directory',
                $this->recordLabel($type, $row->id).' menunjuk ke luar folder '.$expectedRoot.'.',
                $context
            );

            return;
        }

        $privateExists = $this->disk->exists($path);
        $publicExists = $this->publicDisk->exists($path);

        if ($publicExists) {
            $this->addFinding(
                'error',
                'document.public_file_exposure',
                $this->recordLabel($type, $row->id).' masih tersedia pada storage public.',
                $context
            );
        }

        if (! $privateExists) {
            $this->addFinding(
                empty($row->deleted_at) ? 'error' : 'warning',
                $publicExists ? 'document.private_file_missing' : 'document.file_missing',
                $publicExists
                    ? $this->recordLabel($type, $row->id).' belum tersedia pada storage private.'
                    : $this->recordLabel($type, $row->id).' menunjuk file yang tidak ditemukan.',
                $context
            );
        }
    }

    private function auditIncomingSrikandiState($incomings): void
    {
        foreach ($incomings as $row) {
            if (! in_array($row->is_srikandi, [0, 1, '0', '1'], true)) {
                $this->addFinding(
                    'error',
                    'incoming.invalid_srikandi_flag',
                    'Flag SRIKANDI Surat Masuk harus bernilai 0 atau 1.',
                    [
                        'record_id' => $row->id,
                        'is_srikandi' => $row->is_srikandi,
                    ]
                );
            }

            if ((int) $row->is_srikandi !== 1) {
                continue;
            }

            if ($row->nomor_agenda !== null || $row->filelist_id !== null) {
                $this->addFinding(
                    'error',
                    'incoming.invalid_srikandi_state',
                    'Surat Masuk SRIKANDI masih memiliki nomor agenda atau pemberkasan.',
                    [
                        'record_id' => $row->id,
                        'nomor_agenda' => $row->nomor_agenda,
                        'filelist_id' => $row->filelist_id,
                    ]
                );
            }
        }
    }

    private function auditAgendaDuplicates($incomings): void
    {
        $groups = [];

        foreach ($incomings as $row) {
            if ($row->nomor_agenda === null) {
                continue;
            }

            $key = (string) $row->tahun.':'.(string) $row->nomor_agenda;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'year' => (int) $row->tahun,
                    'agenda' => $row->nomor_agenda,
                    'ids' => [],
                ];
            }
            $groups[$key]['ids'][] = $row->id;
        }

        foreach ($groups as $group) {
            if (count($group['ids']) < 2) {
                continue;
            }

            $this->addFinding(
                'error',
                'incoming.duplicate_agenda',
                'Nomor agenda '.$group['agenda'].' pada tahun '.$group['year'].' digunakan lebih dari sekali.',
                [
                    'year' => $group['year'],
                    'agenda' => $group['agenda'],
                    'record_ids' => $group['ids'],
                ]
            );
        }
    }

    private function auditRelations($incomings, $outcomings, $filelists): void
    {
        $filelistById = [];
        foreach ($filelists as $filelist) {
            $filelistById[(string) $filelist->id] = $filelist;
        }

        $accessIds = $this->idSet('accesses');
        $classificationIds = $this->idSet('classifications');
        $statusIds = $this->idSet('statuses');

        foreach ([['surat_masuk', $incomings], ['surat_keluar', $outcomings]] as $group) {
            [$type, $rows] = $group;
            foreach ($rows as $row) {
                if ($row->filelist_id !== null) {
                    $filelist = $filelistById[(string) $row->filelist_id] ?? null;
                    if ($filelist === null) {
                        $this->addFinding(
                            'error',
                            'relation.filelist_missing',
                            $this->recordLabel($type, $row->id).' menunjuk berkas yang tidak ada.',
                            ['filelist_id' => $row->filelist_id]
                        );
                    } elseif (empty($row->deleted_at) && ! empty($filelist->deleted_at)) {
                        $this->addFinding(
                            'error',
                            'relation.filelist_soft_deleted',
                            $this->recordLabel($type, $row->id).' aktif tetapi berkasnya sudah dihapus.',
                            ['filelist_id' => $row->filelist_id]
                        );
                    }
                }

                if ($row->access_id !== null && ! isset($accessIds[(string) $row->access_id])) {
                    $this->addFinding(
                        'error',
                        'relation.access_missing',
                        $this->recordLabel($type, $row->id).' menunjuk sifat akses yang tidak ada.',
                        ['access_id' => $row->access_id]
                    );
                }
            }
        }

        foreach ($filelists as $filelist) {
            if (! isset($classificationIds[(string) $filelist->classification_id])) {
                $this->addFinding(
                    'error',
                    'relation.classification_missing',
                    'Berkas #'.$filelist->id.' menunjuk klasifikasi yang tidak ada.',
                    ['classification_id' => $filelist->classification_id]
                );
            }

            if ($filelist->status_id !== null && ! isset($statusIds[(string) $filelist->status_id])) {
                $this->addFinding(
                    'error',
                    'relation.status_missing',
                    'Berkas #'.$filelist->id.' menunjuk status yang tidak ada.',
                    ['status_id' => $filelist->status_id]
                );
            }
        }
    }

    private function auditAlihMedia($filelists, $incomings, $outcomings): void
    {
        $lettersByFilelist = [];
        foreach ([['surat_masuk', $incomings], ['surat_keluar', $outcomings]] as $group) {
            [$type, $rows] = $group;
            foreach ($rows as $row) {
                if ($row->filelist_id === null || ! empty($row->deleted_at)) {
                    continue;
                }

                $lettersByFilelist[(string) $row->filelist_id][] = [$type, $row];
            }
        }

        $validStates = [
            Filelist::ALIH_MEDIA_PROCESSING,
            Filelist::ALIH_MEDIA_DONE,
            Filelist::ALIH_MEDIA_FAILED,
            Filelist::ALIH_MEDIA_CLOSED,
        ];

        foreach ($filelists as $filelist) {
            if (! empty($filelist->deleted_at)) {
                continue;
            }

            $letters = $lettersByFilelist[(string) $filelist->id] ?? [];
            $state = $filelist->alih_media_status_id === null ? null : (int) $filelist->alih_media_status_id;
            $complete = 0;

            foreach ($letters as [$type, $row]) {
                $watermark = $this->normalizePath($row->url_watermarked ?? null);
                if ($watermark !== null
                    && $this->isWithinRoot($watermark, 'dokumen/alih-media')
                    && $this->disk->exists($watermark)) {
                    $complete++;
                }

                if ($state === null && ! empty($row->url_watermarked)) {
                    $this->addFinding(
                        'error',
                        'alih_media.watermark_without_state',
                        $this->recordLabel($type, $row->id).' memiliki watermark tetapi status alih media berkas kosong.',
                        ['filelist_id' => $filelist->id, 'path' => $row->url_watermarked]
                    );
                }
            }

            if ($state === null) {
                continue;
            }

            if (! in_array($state, $validStates, true)) {
                $this->addFinding(
                    'error',
                    'alih_media.invalid_state',
                    'Berkas #'.$filelist->id.' memiliki status alih media yang tidak dikenal.',
                    ['alih_media_status_id' => $filelist->alih_media_status_id]
                );

                continue;
            }

            if (count($letters) === 0) {
                $this->addFinding(
                    'error',
                    'alih_media.empty_filelist',
                    'Berkas #'.$filelist->id.' berstatus alih media tetapi tidak memiliki surat aktif.',
                    ['alih_media_status_id' => $state]
                );

                continue;
            }

            if (in_array($state, [Filelist::ALIH_MEDIA_DONE, Filelist::ALIH_MEDIA_CLOSED], true)
                && $complete !== count($letters)) {
                $this->addFinding(
                    'error',
                    'alih_media.incomplete_watermarks',
                    'Berkas #'.$filelist->id.' ditandai selesai/tutup tetapi watermark fisik belum lengkap.',
                    [
                        'alih_media_status_id' => $state,
                        'letters' => count($letters),
                        'watermarks_found' => $complete,
                    ]
                );
            }

            if ($state === Filelist::ALIH_MEDIA_FAILED && $complete === count($letters)) {
                $this->addFinding(
                    'warning',
                    'alih_media.failed_but_complete',
                    'Berkas #'.$filelist->id.' berstatus gagal, tetapi seluruh watermark fisik tersedia.',
                    ['letters' => count($letters)]
                );
            }
        }
    }

    private function collectAllDocumentReferences($incomings, $outcomings, $digitals): array
    {
        $references = [];

        foreach ([$incomings, $outcomings] as $rows) {
            foreach ($rows as $row) {
                foreach (['url', 'url_watermarked'] as $column) {
                    $path = $this->normalizePath($row->{$column} ?? null);
                    if ($path !== null) {
                        $references[$path] = true;
                    }
                }
            }
        }

        foreach ($digitals as $row) {
            $path = $this->normalizePath($row->url ?? null);
            if ($path !== null) {
                $references[$path] = true;
            }
        }

        return $references;
    }

    private function auditOrphanFiles(array $references): void
    {
        foreach (self::DOCUMENT_ROOTS as $root) {
            foreach ($this->disk->allFiles($root) as $storedPath) {
                $this->filesScanned++;
                $path = $this->normalizePath($storedPath);

                if ($path !== null && ! isset($references[$path])) {
                    $this->addFinding(
                        'warning',
                        'document.orphan_file',
                        'File fisik tidak direferensikan oleh database.',
                        ['path' => $path]
                    );
                }
            }

            foreach ($this->publicDisk->allFiles($root) as $storedPath) {
                $this->filesScanned++;
                $path = $this->normalizePath($storedPath);

                if ($path !== null && ! isset($references[$path])) {
                    $this->addFinding(
                        'error',
                        'document.public_orphan_exposure',
                        'File tanpa referensi database masih tersedia pada storage public.',
                        ['path' => $path]
                    );
                }
            }
        }
    }

    private function idSet(string $table): array
    {
        $set = [];
        foreach (DB::table($table)->pluck('id') as $id) {
            $set[(string) $id] = true;
        }

        return $set;
    }

    private function normalizePath($path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === ''
            || preg_match('#(^|/)\.\.(/|$)#', $path)
            || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*:#', $path)) {
            return null;
        }

        return preg_replace('#/+#', '/', $path);
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        return $path === $root || strpos($path, $root.'/') === 0;
    }

    private function recordLabel(string $type, $id): string
    {
        $labels = [
            'surat_masuk' => 'Surat Masuk',
            'surat_keluar' => 'Surat Keluar',
            'surat_digital' => 'Surat Digital',
        ];

        return ($labels[$type] ?? $type).' #'.$id;
    }

    private function addFinding(string $severity, string $code, string $message, array $context = []): void
    {
        $this->findings[] = [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ];
    }
}
