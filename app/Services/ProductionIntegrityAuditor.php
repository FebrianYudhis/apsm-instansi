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

        $referenceInventory = $this->collectDocumentReferences($incomings, $outcomings, $digitals);
        $this->auditDocuments($selectedIncomings, 'surat_masuk', 'dokumen/masuk');
        $this->auditDocuments($selectedOutcomings, 'surat_keluar', 'dokumen/keluar');
        $this->auditDocuments($digitals, 'surat_digital', 'dokumen/digital', false);
        $this->auditIncomingSrikandiState($selectedIncomings);
        $this->auditAgendaDuplicates($selectedIncomings);
        $this->auditRelations($selectedIncomings, $selectedOutcomings, $filelists);
        $this->auditAlihMedia($filelists, $incomings, $outcomings);

        $storageReconciliation = $scanOrphans
            ? $this->auditOrphanFiles(
                $referenceInventory['all_paths'],
                $referenceInventory['paths_by_root']
            )
            : null;
        $isSynchronized = $storageReconciliation === null
            ? null
            : $referenceInventory['database_problem_count'] === 0
                && $storageReconciliation['totals']['synchronized'];

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
            'reconciliation' => [
                'scope' => 'Seluruh tahun dan termasuk data soft deleted agar semua referensi file dapat dicocokkan.',
                'database' => $referenceInventory['database'],
                'database_problem_count' => $referenceInventory['database_problem_count'],
                'storage' => $storageReconciliation,
                'synchronized' => $isSynchronized,
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

    private function collectDocumentReferences($incomings, $outcomings, $digitals): array
    {
        $incomingOriginals = $this->collectReferenceGroup($incomings, 'url', 'dokumen/masuk');
        $incomingWatermarks = $this->collectReferenceGroup(
            $incomings,
            'url_watermarked',
            'dokumen/alih-media'
        );
        $outgoingOriginals = $this->collectReferenceGroup($outcomings, 'url', 'dokumen/keluar');
        $outgoingWatermarks = $this->collectReferenceGroup(
            $outcomings,
            'url_watermarked',
            'dokumen/alih-media'
        );
        $digitalOriginals = $this->collectReferenceGroup($digitals, 'url', 'dokumen/digital');

        $groups = [
            $incomingOriginals,
            $incomingWatermarks,
            $outgoingOriginals,
            $outgoingWatermarks,
            $digitalOriginals,
        ];
        $allPaths = [];
        $pathsByRoot = array_fill_keys(self::DOCUMENT_ROOTS, []);

        foreach ($groups as $group) {
            foreach ($group['path_occurrences'] as $path => $occurrences) {
                $allPaths[$path] = true;
                $root = $this->documentRootForPath($path);

                if ($root !== null) {
                    $pathsByRoot[$root][$path] = ($pathsByRoot[$root][$path] ?? 0)
                        + $occurrences;
                }
            }
        }

        foreach ($pathsByRoot as $root => $occurrencesByPath) {
            foreach ($occurrencesByPath as $path => $occurrences) {
                if ($occurrences < 2) {
                    continue;
                }

                $this->addFinding(
                    'warning',
                    'document.duplicate_reference',
                    'Satu file fisik direferensikan oleh lebih dari satu kolom database.',
                    [
                        'root' => $root,
                        'path' => $path,
                        'reference_count' => $occurrences,
                    ]
                );
            }
        }

        $database = [
            'incomings' => [
                'rows' => $incomings->count(),
                'original' => $this->referenceGroupSummary($incomingOriginals),
                'watermark' => $this->referenceGroupSummary($incomingWatermarks),
            ],
            'outcomings' => [
                'rows' => $outcomings->count(),
                'original' => $this->referenceGroupSummary($outgoingOriginals),
                'watermark' => $this->referenceGroupSummary($outgoingWatermarks),
            ],
            'digitals' => [
                'rows' => $digitals->count(),
                'original' => $this->referenceGroupSummary($digitalOriginals),
                'watermark' => null,
            ],
        ];
        $databaseProblemCount = 0;

        foreach ($database as $summary) {
            $databaseProblemCount += $summary['rows']
                - $summary['original']['valid_in_expected_root'];
            $databaseProblemCount += $summary['original']['duplicate_references'];

            if ($summary['watermark'] !== null) {
                $databaseProblemCount += $summary['watermark']['invalid_or_wrong_root'];
                $databaseProblemCount += $summary['watermark']['duplicate_references'];
            }
        }

        return [
            'database' => $database,
            'database_problem_count' => $databaseProblemCount,
            'all_paths' => $allPaths,
            'paths_by_root' => $pathsByRoot,
        ];
    }

    private function collectReferenceGroup($rows, string $column, string $expectedRoot): array
    {
        $filled = 0;
        $normalized = 0;
        $validInExpectedRoot = 0;
        $expectedPathOccurrences = [];
        $pathOccurrences = [];

        foreach ($rows as $row) {
            $rawPath = $row->{$column} ?? null;

            if (! is_string($rawPath) || trim($rawPath) === '') {
                continue;
            }

            $filled++;
            $path = $this->normalizePath($rawPath);

            if ($path === null) {
                continue;
            }

            $normalized++;
            $pathOccurrences[$path] = ($pathOccurrences[$path] ?? 0) + 1;

            if ($this->isWithinRoot($path, $expectedRoot)) {
                $validInExpectedRoot++;
                $expectedPathOccurrences[$path] = ($expectedPathOccurrences[$path] ?? 0) + 1;
            }
        }

        return [
            'filled' => $filled,
            'normalized' => $normalized,
            'valid_in_expected_root' => $validInExpectedRoot,
            'unique_paths' => count($expectedPathOccurrences),
            'duplicate_references' => $validInExpectedRoot - count($expectedPathOccurrences),
            'invalid_or_wrong_root' => $filled - $validInExpectedRoot,
            'path_occurrences' => $pathOccurrences,
        ];
    }

    private function referenceGroupSummary(array $group): array
    {
        return [
            'filled' => $group['filled'],
            'normalized' => $group['normalized'],
            'valid_in_expected_root' => $group['valid_in_expected_root'],
            'unique_paths' => $group['unique_paths'],
            'duplicate_references' => $group['duplicate_references'],
            'invalid_or_wrong_root' => $group['invalid_or_wrong_root'],
        ];
    }

    private function auditOrphanFiles(array $references, array $pathsByRoot): array
    {
        $roots = [];
        $totals = [
            'references' => 0,
            'unique_references' => 0,
            'duplicate_references' => 0,
            'private_files' => 0,
            'matched_private_files' => 0,
            'missing_private_files' => 0,
            'orphan_private_files' => 0,
            'public_files' => 0,
            'referenced_public_files' => 0,
            'orphan_public_files' => 0,
        ];

        foreach (self::DOCUMENT_ROOTS as $root) {
            $privateStoredPaths = $this->disk->allFiles($root);
            $publicStoredPaths = $this->publicDisk->allFiles($root);
            $this->filesScanned += count($privateStoredPaths) + count($publicStoredPaths);

            $privatePaths = $this->normalizeStoredPaths($privateStoredPaths);
            $publicPaths = $this->normalizeStoredPaths($publicStoredPaths);
            $referenceOccurrences = $pathsByRoot[$root] ?? [];
            $referencePaths = array_fill_keys(array_keys($referenceOccurrences), true);
            $matchedPrivate = array_intersect_key($referencePaths, $privatePaths);
            $missingPrivate = array_diff_key($referencePaths, $privatePaths);
            $orphanPrivate = array_diff_key($privatePaths, $references);
            $referencedPublic = array_intersect_key($referencePaths, $publicPaths);
            $orphanPublic = array_diff_key($publicPaths, $references);
            $referenceCount = array_sum($referenceOccurrences);
            $uniqueReferenceCount = count($referencePaths);
            $duplicateReferenceCount = $referenceCount - $uniqueReferenceCount;

            foreach (array_keys($orphanPrivate) as $path) {
                $this->addFinding(
                    'warning',
                    'document.orphan_file',
                    'File fisik tidak direferensikan oleh database.',
                    ['path' => $path]
                );
            }

            foreach (array_keys($orphanPublic) as $path) {
                $this->addFinding(
                    'error',
                    'document.public_orphan_exposure',
                    'File tanpa referensi database masih tersedia pada storage public.',
                    ['path' => $path]
                );
            }

            $rootSummary = [
                'references' => $referenceCount,
                'unique_references' => $uniqueReferenceCount,
                'duplicate_references' => $duplicateReferenceCount,
                'private_files' => count($privatePaths),
                'matched_private_files' => count($matchedPrivate),
                'missing_private_files' => count($missingPrivate),
                'orphan_private_files' => count($orphanPrivate),
                'public_files' => count($publicPaths),
                'referenced_public_files' => count($referencedPublic),
                'orphan_public_files' => count($orphanPublic),
            ];
            $rootSummary['synchronized'] = $rootSummary['duplicate_references'] === 0
                && $rootSummary['missing_private_files'] === 0
                && $rootSummary['orphan_private_files'] === 0
                && $rootSummary['public_files'] === 0;
            $roots[$root] = $rootSummary;

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $rootSummary[$key];
            }
        }

        $totals['synchronized'] = $totals['duplicate_references'] === 0
            && $totals['missing_private_files'] === 0
            && $totals['orphan_private_files'] === 0
            && $totals['public_files'] === 0;

        return [
            'roots' => $roots,
            'totals' => $totals,
        ];
    }

    private function normalizeStoredPaths(array $storedPaths): array
    {
        $paths = [];

        foreach ($storedPaths as $storedPath) {
            $path = $this->normalizePath($storedPath);

            if ($path !== null) {
                $paths[$path] = true;
            }
        }

        return $paths;
    }

    private function documentRootForPath(string $path): ?string
    {
        foreach (self::DOCUMENT_ROOTS as $root) {
            if ($this->isWithinRoot($path, $root)) {
                return $root;
            }
        }

        return null;
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
