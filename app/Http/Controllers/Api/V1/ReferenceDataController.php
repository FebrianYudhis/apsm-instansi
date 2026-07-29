<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Filelist;
use App\Models\Status;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    public function accesses(): JsonResponse
    {
        $accesses = Access::query()
            ->orderBy('sifat_akses')
            ->get()
            ->map(fn (Access $access): array => [
                'id' => $access->getKey(),
                'name' => $access->sifat_akses,
            ]);

        return response()->json(['data' => $accesses]);
    }

    public function activeFilelists(): JsonResponse
    {
        $filelists = Filelist::query()
            ->whereHas(
                'status',
                fn ($query) => $query->where('nama_status', Status::ACTIVE)
            )
            ->whereNull('alih_media_status_id')
            ->with('classification:id,kode_klasifikasi,keterangan')
            ->orderBy('nama_berkas')
            ->get()
            ->map(fn (Filelist $filelist): array => [
                'id' => $filelist->getKey(),
                'name' => $filelist->nama_berkas,
                'classification' => [
                    'code' => $filelist->classification?->kode_klasifikasi,
                    'description' => $filelist->classification?->keterangan,
                ],
            ]);

        return response()->json(['data' => $filelists]);
    }
}
