<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Filelist;
use App\Services\FilelistOperationService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;
use Throwable;

class BerkasContentController extends Controller
{
    public function buka(Request $request, int $id): View|RedirectResponse
    {
        $filelist = Filelist::find($id);
        if (! $filelist) {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        $validated = $request->validate([
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date', 'after_or_equal:tanggal_dari'],
        ]);

        $tanggalDari = $validated['tanggal_dari'] ?? null;
        $tanggalSampai = $validated['tanggal_sampai'] ?? null;
        $incomingsQuery = $filelist->incomings()->with(['access', 'filelist']);
        $outcomingsQuery = $filelist->outcomings()->with(['access', 'filelist']);

        if ($tanggalDari) {
            $incomingsQuery->whereDate('tanggal_surat', '>=', $tanggalDari);
            $outcomingsQuery->whereDate('tanggal_surat', '>=', $tanggalDari);
        }

        if ($tanggalSampai) {
            $incomingsQuery->whereDate('tanggal_surat', '<=', $tanggalSampai);
            $outcomingsQuery->whereDate('tanggal_surat', '<=', $tanggalSampai);
        }

        $combinedData = collect();

        foreach ($incomingsQuery->get() as $incoming) {
            $combinedData->push([
                'id' => $incoming->id,
                'jenis' => 'masuk',
                'uraian' => $incoming->perihal,
                'nomor_naskah' => $incoming->nomor_surat,
                'tanggal_item' => $incoming->tanggal_surat,
                'skkad' => $incoming->access?->sifat_akses,
                'filelist_id' => $incoming->filelist_id,
                'tahun' => $incoming->tahun,
                'is_locked' => $incoming->isAlihMediaLocked(),
            ]);
        }

        foreach ($outcomingsQuery->get() as $outgoing) {
            $combinedData->push([
                'id' => $outgoing->id,
                'jenis' => 'keluar',
                'uraian' => $outgoing->perihal,
                'nomor_naskah' => $outgoing->nomor_surat,
                'tanggal_item' => $outgoing->tanggal_surat,
                'skkad' => $outgoing->access?->sifat_akses,
                'filelist_id' => $outgoing->filelist_id,
                'tahun' => $outgoing->tahun,
                'is_locked' => $outgoing->isAlihMediaLocked(),
            ]);
        }

        $tanggalItems = $combinedData
            ->filter(fn (array $item): bool => ! empty($item['tanggal_item']))
            ->sortBy('tanggal_item')
            ->values();

        $kurunWaktu = '-';
        if ($tanggalItems->isNotEmpty()) {
            $tanggalAwal = $this->formatTanggalIndonesia($tanggalItems->first()['tanggal_item']);
            $tanggalAkhir = $this->formatTanggalIndonesia($tanggalItems->last()['tanggal_item']);
            $kurunWaktu = $tanggalAwal === $tanggalAkhir
                ? $tanggalAwal
                : $tanggalAwal.' s/d '.$tanggalAkhir;
        }

        return view('app.surat.berkas.buka', [
            'judul' => 'Daftar Isi Berkas',
            'data' => $combinedData,
            'berkas' => $filelist,
            'kurunWaktu' => $kurunWaktu,
            'classification' => Classification::orderBy('kode_klasifikasi')->get(),
        ]);
    }

    public function daftarBerkasAktif(): JsonResponse
    {
        $filelists = Filelist::where('status_id', 1)
            ->whereNull('alih_media_status_id')
            ->with('classification:id,kode_klasifikasi')
            ->orderBy('nama_berkas')
            ->get(['id', 'classification_id', 'nama_berkas']);

        $data = $filelists->map(fn (Filelist $filelist): array => [
            'id' => $filelist->id,
            'kode_klasifikasi' => $filelist->classification?->kode_klasifikasi ?? '-',
            'nama_berkas' => $filelist->nama_berkas,
        ])->values();

        return response()->json(['data' => $data]);
    }

    public function gantiLokasiBulk(
        Request $request,
        FilelistOperationService $operations
    ): RedirectResponse {
        $validated = $request->validate([
            'berkas_asal' => ['required', 'integer', Rule::exists('filelists', 'id')],
            'pemberkasan' => [
                'required',
                'integer',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'regex:/^(masuk|keluar):\d+$/'],
        ]);

        $result = $operations->moveLetters(
            (int) $validated['berkas_asal'],
            (int) $validated['pemberkasan'],
            $validated['items']
        );

        $messages = [
            'source_invalid' => 'Berkas asal tidak valid untuk pemindahan surat',
            'target_invalid' => 'Berkas tujuan sudah dialihmediakan atau tidak valid',
            'same_filelist' => 'Berkas tujuan harus berbeda dari berkas asal',
            'incoming_invalid' => 'Terdapat surat masuk yang tidak berada di berkas asal',
            'outcoming_invalid' => 'Terdapat surat keluar yang tidak berada di berkas asal',
            'item_locked' => 'Surat yang sudah masuk proses alih media tidak dapat dipindahkan',
        ];

        if ($result['status'] !== 'updated') {
            Alert::error('Gagal', $messages[$result['status']] ?? 'Pemindahan surat gagal');

            return back();
        }

        if (($result['count'] ?? 0) < 1) {
            Alert::error('Gagal', 'Tidak ada surat yang berhasil dipindahkan');

            return back();
        }

        Alert::success('Berhasil', $result['count'].' surat berhasil dipindahkan');

        return back();
    }

    public function keluarkan(
        int $idBerkas,
        string $jenis,
        int $idSurat,
        FilelistOperationService $operations
    ): RedirectResponse {
        if (! in_array($jenis, ['masuk', 'keluar'], true)) {
            Alert::error('Gagal', 'Jenis Surat Tidak Valid');

            return back();
        }

        $result = $operations->releaseLetter($idBerkas, $jenis, $idSurat);

        if ($result !== 'updated') {
            $messages = [
                'filelist_not_found' => 'Berkas Tidak Ditemukan',
                'filelist_locked' => 'Berkas tidak valid atau sudah masuk proses alih media',
                'letter_not_found' => 'Surat Tidak Ditemukan Pada Berkas Ini',
                'letter_locked' => 'Surat yang sudah masuk proses alih media tidak dapat dipindahkan',
            ];
            Alert::error('Gagal', $messages[$result] ?? 'Surat gagal dikeluarkan dari berkas');

            return back();
        }

        Alert::success('Berhasil', 'Surat Berhasil Dikeluarkan Dari Berkas');

        return back();
    }

    private function formatTanggalIndonesia(mixed $tanggal): string
    {
        if (empty($tanggal) || $tanggal === '-') {
            return '-';
        }

        try {
            return Carbon::parse($tanggal)->format('d-m-Y');
        } catch (Throwable) {
            return (string) $tanggal;
        }
    }
}
