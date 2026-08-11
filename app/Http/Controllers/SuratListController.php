<?php

namespace App\Http\Controllers;

use App\Http\Requests\PendingFilingFilterRequest;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Services\ActiveYear;
use App\Services\DocumentService;
use App\Services\SuratFilterQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class SuratListController extends Controller
{
    public function __construct(
        private ActiveYear $activeYear,
        private DocumentService $documents
    ) {}

    public function masuk(Request $request, SuratFilterQuery $suratFilter)
    {
        $filters = $suratFilter->validateIncoming($request);

        if (! $request->ajax()) {
            return view('app.surat.masuk.index', ['judul' => 'List Surat Masuk']);
        }

        $query = $suratFilter->incoming($this->activeYear->current(), $filters)
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

        $query = $suratFilter->outgoing($this->activeYear->current(), $filters)
            ->with(['filelist.classification', 'access'])
            ->orderByDesc('tanggal_surat');

        return DataTables::of($query)
            ->addColumn('aksi', fn ($data) => $this->suratActions($data, 'keluar'))
            ->rawColumns(['aksi'])
            ->toJson();
    }

    public function belumDiberkaskan(PendingFilingFilterRequest $request): View|JsonResponse
    {
        if (! $request->ajax()) {
            return view('app.surat.belum-diberkaskan', [
                'judul' => 'Surat Belum Diberkaskan',
            ]);
        }

        $validated = $request->validated();
        $jenis = $validated['jenis'] ?? null;
        $tahun = (int) ($validated['tahun'] ?? $this->activeYear->current());
        $isFiltered = $validated !== [];
        $suratMasuk = Incoming::query()
            ->selectRaw(
                "id, 'masuk' as jenis, tanggal_diterima as tanggal_pencatatan, ".
                'tanggal_surat, nomor_surat, pengirim as pihak, perihal, tahun, '.
                "CASE WHEN url IS NULL OR url = '' THEN 0 ELSE 1 END as has_pdf"
            )
            ->where('tahun', $tahun)
            ->pendingFiling();
        $suratKeluar = Outcoming::query()
            ->selectRaw(
                "id, 'keluar' as jenis, tanggal_surat as tanggal_pencatatan, ".
                'tanggal_surat, nomor_surat, tujuan as pihak, perihal, tahun, '.
                "CASE WHEN url IS NULL OR url = '' THEN 0 ELSE 1 END as has_pdf"
            )
            ->where('tahun', $tahun)
            ->pendingFiling();

        if ($isFiltered) {
            $suratMasuk->whereNull('url_watermarked');
            $suratKeluar->whereNull('url_watermarked');
        }

        $surat = match ($jenis) {
            'masuk' => $suratMasuk,
            'keluar' => $suratKeluar,
            default => $suratMasuk->unionAll($suratKeluar),
        };
        $query = DB::query()->fromSub(
            $surat,
            'surat_belum_diberkaskan'
        );

        return DataTables::of($query)
            ->addColumn('preview_url', function ($data): ?string {
                if (! (bool) $data->has_pdf) {
                    return null;
                }

                return route('document.admin', [
                    'jenis' => (string) $data->jenis,
                    'id' => (int) $data->id,
                    'versi' => DocumentService::VARIANT_DISPLAY,
                ]);
            })
            ->addColumn('aksi', function ($data): string {
                $jenis = (string) $data->jenis;
                $selectionKey = $jenis.':'.(int) $data->id;
                $letterNumber = (string) ($data->nomor_surat ?: '-');

                return "<div class='d-flex justify-content-center'>"
                    ."<a href='".e(route('surat.detailItem', [$jenis, $data->id]))
                    ."' class='btn btn-info btn-sm mr-1' title='Lihat Detail'>"
                    ."<i class='fa fa-eye'></i></a>"
                    ."<button type='button' class='btn btn-primary btn-sm open-direct-filing-modal' "
                    ."data-letter-key='".e($selectionKey)."' data-letter-number='".e($letterNumber)."' "
                    ."title='Berkaskan Surat' aria-label='Berkaskan Surat'>"
                    ."<i class='fa fa-folder-open' aria-hidden='true'></i></button>"
                    .'</div>';
            })
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
            $button .= "<a href='".e($this->documents->adminUrl(
                $jenis,
                $data,
                DocumentService::VARIANT_DISPLAY
            ))."' target='_blank' rel='noopener noreferrer' class='btn btn-success btn-sm mr-1' title='Lihat Berkas (PDF)'><i class='fa fa-file-pdf'></i></a>";
        }

        $button .= $this->directFilingButton($data, $jenis);

        if ($data->isAlihMediaLocked()) {
            $button .= "<button type='button' class='btn btn-secondary btn-sm' title='Terkunci karena alih media' disabled><i class='fa fa-lock'></i></button>";
        } else {
            $button .= "<a href='".e(route($editRoute, [$data->id]))."' class='btn btn-primary btn-sm mr-1' title='Edit'><i class='fa fa-edit'></i></a>";
            $button .= "<form action='".e(route($deleteRoute, [$data->id]))."' class='m-0 konfirmasi-hapus' method='POST'>".csrf_field().method_field('delete')."<button type='submit' class='btn btn-danger btn-sm' title='Hapus'><i class='fa fa-trash'></i></button></form>";
        }

        return $button.'</div>';
    }

    private function directFilingButton(object $data, string $jenis): string
    {
        if ($data->filelist_id !== null
            || (bool) $data->is_srikandi
            || $data->url_watermarked !== null) {
            return '';
        }

        $selectionKey = $jenis.':'.(int) $data->id;
        $letterNumber = (string) ($data->nomor_surat ?: '-');

        return "<button type='button' class='btn btn-primary btn-sm mr-1 open-direct-filing-modal' "
            ."data-letter-key='".e($selectionKey)."' data-letter-number='".e($letterNumber)."' "
            ."title='Berkaskan Surat' aria-label='Berkaskan Surat'>"
            ."<i class='fa fa-folder-open' aria-hidden='true'></i></button>";
    }
}
