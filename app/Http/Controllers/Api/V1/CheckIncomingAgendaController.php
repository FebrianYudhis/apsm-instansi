<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incoming;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckIncomingAgendaController extends Controller
{
    /**
     * Handle the incoming request to check agenda availability.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $startYear = (int) config('app.start_year', 2025);
        $currentYear = now()->year;

        $validated = $request->validate([
            'nomor_agenda' => ['required', 'integer', 'min:1'],
            'tahun' => ['nullable', 'integer', "between:{$startYear},{$currentYear}"],
        ]);

        $nomorAgenda = (int) $validated['nomor_agenda'];
        $tahun = isset($validated['tahun']) ? (int) $validated['tahun'] : $currentYear;

        $existing = Incoming::withTrashed()
            ->where('nomor_agenda', $nomorAgenda)
            ->where('tahun', $tahun)
            ->first();

        if (! $existing) {
            return response()->json([
                'available' => true,
                'message' => "Nomor agenda {$nomorAgenda} tersedia untuk tahun {$tahun}.",
            ]);
        }

        $isDeleted = $existing->trashed();

        return response()->json([
            'available' => false,
            'message' => $isDeleted
                ? "Nomor agenda {$nomorAgenda} sudah terpakai oleh arsip surat yang berada di tempat sampah (soft-deleted) pada tahun {$tahun}."
                : "Nomor agenda {$nomorAgenda} sudah digunakan pada tahun {$tahun}.",
            'data' => [
                'id' => $existing->id,
                'nomor_agenda' => $existing->nomor_agenda,
                'nomor_surat' => $existing->nomor_surat,
                'pengirim' => $existing->pengirim,
                'perihal' => $existing->perihal,
                'tanggal_surat' => $existing->tanggal_surat ? Carbon::parse($existing->tanggal_surat)->format('d/m/Y') : '-',
                'tanggal_diterima' => $existing->tanggal_diterima ? Carbon::parse($existing->tanggal_diterima)->format('d/m/Y') : '-',
                'is_deleted' => $isDeleted,
                'detail_url' => $isDeleted ? null : route('surat.detailItem', ['masuk', $existing->id]),
            ],
        ]);
    }
}
