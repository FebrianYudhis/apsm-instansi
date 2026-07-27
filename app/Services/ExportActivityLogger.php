<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class ExportActivityLogger
{
    public function logPrepared(
        string $jenisExport,
        int $jumlahBaris,
        string $namaFile,
        array $filters = [],
        array $context = []
    ): void {
        $user = Auth::user();
        $properties = array_merge([
            'jenis_export' => $jenisExport,
            'status' => 'prepared',
            'tahun_aktif' => $user ? (int) $user->tahun : null,
            'filter' => $this->sanitize($filters),
            'jumlah_baris' => $jumlahBaris,
            'nama_file' => $namaFile,
        ], $this->sanitize($context));

        $activity = activity('export')
            ->event('exported')
            ->withProperties($properties);

        if ($user) {
            $activity->causedBy($user);
        }

        $activity->log('Export '.$jenisExport.' disiapkan');
    }

    private function sanitize(array $values): array
    {
        return array_filter($values, function ($value) {
            return is_null($value) || is_scalar($value);
        });
    }
}
