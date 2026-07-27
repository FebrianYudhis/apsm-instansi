<?php

namespace App\Http\Controllers;

use App\Services\SuratFilterQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class SuratListController extends Controller
{
    public function masuk(Request $request, SuratFilterQuery $suratFilter)
    {
        $filters = $suratFilter->validateIncoming($request);

        if (! $request->ajax()) {
            return view('app.surat.masuk.index', ['judul' => 'List Surat Masuk']);
        }

        $query = $suratFilter->incoming((int) Auth::user()->tahun, $filters)
            ->with(['filelist.classification', 'access'])
            ->orderByDesc('tanggal_diterima')
            ->orderByDesc('nomor_agenda');

        return DataTables::of($query)
            ->addColumn('aksi', fn ($data) => $this->suratActions($data, 'masuk'))
            ->rawColumns(['aksi'])
            ->toJson();
    }

    public function keluar(Request $request, SuratFilterQuery $suratFilter)
    {
        $filters = $suratFilter->validateOutgoing($request);

        if (! $request->ajax()) {
            return view('app.surat.keluar.index', ['judul' => 'List Surat Keluar']);
        }

        $query = $suratFilter->outgoing((int) Auth::user()->tahun, $filters)
            ->with(['filelist.classification', 'access'])
            ->orderByDesc('tanggal_surat');

        return DataTables::of($query)
            ->addColumn('aksi', fn ($data) => $this->suratActions($data, 'keluar'))
            ->rawColumns(['aksi'])
            ->toJson();
    }

    private function suratActions($data, string $jenis): string
    {
        $editRoute = $jenis === 'masuk' ? 'masuk.edit' : 'keluar.edit';
        $deleteRoute = $jenis === 'masuk' ? 'masuk.hapus' : 'keluar.hapus';
        $button = "<div class='d-flex justify-content-center'>";
        $button .= "<a href='".e(route('surat.detailItem', [$jenis, $data->id]))."' class='btn btn-info btn-sm mr-1' title='Lihat Detail'><i class='fa fa-eye'></i></a>";

        if ($data->url_watermarked || $data->url) {
            $button .= "<a href='".e(route('document.admin', [
                'jenis' => $jenis,
                'id' => $data->id,
                'versi' => 'tampil',
            ]))."' target='_blank' rel='noopener noreferrer' class='btn btn-success btn-sm mr-1' title='Lihat Berkas (PDF)'><i class='fa fa-file-pdf'></i></a>";
        }

        if ($data->isAlihMediaLocked()) {
            $button .= "<button type='button' class='btn btn-secondary btn-sm' title='Terkunci karena alih media' disabled><i class='fa fa-lock'></i></button>";
        } else {
            $button .= "<a href='".e(route($editRoute, [$data->id]))."' class='btn btn-primary btn-sm mr-1' title='Edit'><i class='fa fa-edit'></i></a>";
            $button .= "<form action='".e(route($deleteRoute, [$data->id]))."' class='m-0 konfirmasi-hapus' method='POST'>".csrf_field().method_field('delete')."<button type='submit' class='btn btn-danger btn-sm' title='Hapus'><i class='fa fa-trash'></i></button></form>";
        }

        return $button.'</div>';
    }
}
