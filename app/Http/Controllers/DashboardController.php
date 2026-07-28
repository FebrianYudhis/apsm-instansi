<?php

namespace App\Http\Controllers;

use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Services\ActiveYear;

class DashboardController extends Controller
{
    public function __construct(private ActiveYear $activeYear) {}

    public function index()
    {
        $tahun = $this->activeYear->current();
        $statusAktifId = Status::where('nama_status', Status::ACTIVE)->value('id');
        $statusInaktifId = Status::where('nama_status', Status::INACTIVE)->value('id');

        $suratMasuk = Incoming::where('tahun', $tahun);
        $suratKeluar = Outcoming::where('tahun', $tahun);
        $berkasAktifInaktifIds = array_filter([$statusAktifId, $statusInaktifId]);

        return view('app.index', [
            'judul' => 'Beranda',
            'tahun' => $tahun,
            'suratMasuk' => (clone $suratMasuk)->count(),
            'suratKeluar' => (clone $suratKeluar)->count(),
            'suratDigital' => Digital::count(),
            'berkasAktif' => $statusAktifId ? Filelist::where('status_id', $statusAktifId)->count() : 0,
            'berkasInaktif' => $statusInaktifId ? Filelist::where('status_id', $statusInaktifId)->count() : 0,
            'berkasPermanen' => Filelist::where('keterangan_akhir', 'Permanen')->count(),
            'berkasMusnah' => Filelist::where('keterangan_akhir', 'Musnah')->count(),
            'alihMediaMenunggu' => Filelist::where('keterangan_akhir', 'Permanen')
                ->whereNull('alih_media_status_id')
                ->when($berkasAktifInaktifIds, function ($query) use ($berkasAktifInaktifIds) {
                    $query->whereIn('status_id', $berkasAktifInaktifIds);
                })
                ->count(),
            'alihMediaDiproses' => Filelist::where('keterangan_akhir', 'Permanen')
                ->where('alih_media_status_id', Filelist::ALIH_MEDIA_PROCESSING)->count(),
            'alihMediaGagal' => Filelist::where('keterangan_akhir', 'Permanen')
                ->where('alih_media_status_id', Filelist::ALIH_MEDIA_FAILED)->count(),
            'alihMediaSelesai' => Filelist::where('keterangan_akhir', 'Permanen')
                ->where('alih_media_status_id', Filelist::ALIH_MEDIA_CLOSED)->count(),
            'suratBelumBerkas' => (clone $suratMasuk)->whereNull('filelist_id')
                ->where('is_srikandi', false)->count()
                + (clone $suratKeluar)->whereNull('filelist_id')
                    ->where('is_srikandi', false)->count(),
            'berkasTanpaIsi' => Filelist::doesntHave('incomings')->doesntHave('outcomings')->count(),
            'suratMasukTerbaru' => (clone $suratMasuk)->orderByDesc('tanggal_diterima')
                ->orderByDesc('nomor_agenda')->limit(5)->get(),
            'suratKeluarTerbaru' => (clone $suratKeluar)->orderByDesc('tanggal_surat')
                ->orderByDesc('nomor_surat')->limit(5)->get(),
        ]);
    }
}
