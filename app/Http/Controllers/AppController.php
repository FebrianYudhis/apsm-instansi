<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Services\ActiveYear;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\DataTables;

class AppController extends Controller
{
    public function __construct(
        private ActiveYear $activeYear,
        private DocumentService $documents
    ) {}

    public function digital(Request $request)
    {
        if ($request->ajax()) {
            $query = Digital::query();

            return DataTables::of($query)
                ->addColumn('aksi', function ($data) {
                    $menu = "<div class='dropdown text-center'>";
                    $menu .= "<button class='btn btn-sm btn-light border shadow-none' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false' title='Menu Aksi' style='width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;'>";
                    $menu .= "<i class='fa fa-ellipsis-v text-secondary'></i>";
                    $menu .= '</button>';
                    $menu .= "<div class='dropdown-menu dropdown-menu-right shadow border-0 py-1' style='min-width: 175px;'>";

                    $menu .= "<a href='".e(route('surat.detailItem', ['digital', $data['id']]))."' target='_blank' rel='noopener noreferrer' class='dropdown-item text-info py-2 d-flex align-items-center' title='Lihat Detail'>";
                    $menu .= "<i class='fa fa-eye mr-2' style='width: 16px; text-align: center;'></i> <span>Lihat Detail</span>";
                    $menu .= '</a>';

                    if (! empty($data['url'])) {
                        $menu .= "<a href='".e($this->documents->adminUrl(
                            DocumentService::TYPE_DIGITAL,
                            $data,
                            DocumentService::VARIANT_ORIGINAL
                        ))."' target='_blank' rel='noopener noreferrer' class='dropdown-item text-success py-2 d-flex align-items-center' title='Lihat Berkas (PDF)'>";
                        $menu .= "<i class='fa fa-file-pdf mr-2' style='width: 16px; text-align: center;'></i> <span>Lihat Berkas (PDF)</span>";
                        $menu .= '</a>';
                    }

                    $menu .= "<a href='".e(route('digital.edit', [$data['id']]))."' class='dropdown-item text-primary py-2 d-flex align-items-center' title='Edit'>";
                    $menu .= "<i class='fa fa-edit mr-2' style='width: 16px; text-align: center;'></i> <span>Edit</span>";
                    $menu .= '</a>';

                    $menu .= "<div class='dropdown-divider my-1'></div>";
                    $menu .= "<form action='".e(route('digital.hapus', [$data['id']]))."' class='m-0' method='POST'>";
                    $menu .= csrf_field().method_field('delete');
                    $menu .= "<button type='submit' class='dropdown-item text-danger py-2 d-flex align-items-center konfirmasi-hapus' title='Hapus'>";
                    $menu .= "<i class='fa fa-trash mr-2 text-danger' style='width: 16px; text-align: center;'></i> <span>Hapus</span>";
                    $menu .= '</button></form>';

                    $menu .= '</div></div>';

                    return $menu;
                })->rawColumns(['aksi'])->toJson();
        }

        $data = [
            'judul' => 'List Surat Digital',
        ];

        return view('app.surat.digital.index', $data);
    }

    public function klasifikasi(Request $request)
    {
        if ($request->ajax()) {
            $query = Classification::query();

            return DataTables::of($query)
                ->addColumn('aksi', function ($data) {
                    $menu = "<div class='dropdown text-center'>";
                    $menu .= "<button class='btn btn-sm btn-light border shadow-none' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false' title='Menu Aksi' style='width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;'>";
                    $menu .= "<i class='fa fa-ellipsis-v text-secondary'></i>";
                    $menu .= '</button>';
                    $menu .= "<div class='dropdown-menu dropdown-menu-right shadow border-0 py-1' style='min-width: 150px;'>";

                    $menu .= "<a href='".e(route('klasifikasi.edit', [$data['id']]))."' class='dropdown-item text-primary py-2 d-flex align-items-center' title='Edit'>";
                    $menu .= "<i class='fa fa-edit mr-2' style='width: 16px; text-align: center;'></i> <span>Edit</span>";
                    $menu .= '</a>';

                    $menu .= "<div class='dropdown-divider my-1'></div>";
                    $menu .= "<form action='".e(route('klasifikasi.hapus', [$data['id']]))."' class='m-0' method='POST'>";
                    $menu .= csrf_field().method_field('delete');
                    $menu .= "<button type='submit' class='dropdown-item text-danger py-2 d-flex align-items-center konfirmasi-hapus' title='Hapus'>";
                    $menu .= "<i class='fa fa-trash mr-2 text-danger' style='width: 16px; text-align: center;'></i> <span>Hapus</span>";
                    $menu .= '</button></form>';

                    $menu .= '</div></div>';

                    return $menu;
                })->rawColumns(['aksi'])->toJson();
        }

        $data = [
            'judul' => 'List Klasifikasi',
        ];

        return view('app.surat.klasifikasi.index', $data);
    }

    public function detailItem($jenis, $idSurat)
    {
        if ($jenis == 'masuk') {
            $surat = Incoming::with(['filelist.classification', 'access'])->find($idSurat);
        } elseif ($jenis == 'keluar') {
            $surat = Outcoming::with(['filelist.classification', 'access'])->find($idSurat);
        } elseif ($jenis == 'digital') {
            $surat = Digital::find($idSurat);
        } else {
            Alert::error('Gagal', 'Jenis Surat Tidak Valid');

            return redirect()->back();
        }

        if (! $surat) {
            Alert::error('Gagal', 'Surat Tidak Ditemukan');

            return redirect()->back();
        }

        $editRouteName = match ($jenis) {
            'masuk' => 'masuk.edit',
            'keluar' => 'keluar.edit',
            'digital' => 'digital.edit',
        };

        $data = [
            'judul' => 'Detail Naskah',
            'jenis' => $jenis,
            'surat' => $surat,
            'editUrl' => route($editRouteName, [$surat->id]),
            'editPath' => route($editRouteName, [$surat->id], false),
            'documentFileName' => $this->documents->descriptiveFileName($surat),
            'documentOriginalUrl' => $this->documents->adminUrl(
                $jenis,
                $surat,
                DocumentService::VARIANT_ORIGINAL
            ),
            'documentWatermarkUrl' => $jenis === DocumentService::TYPE_DIGITAL
                ? null
                : $this->documents->adminUrl(
                    $jenis,
                    $surat,
                    DocumentService::VARIANT_WATERMARK
                ),
            'requiresYearSwitch' => $jenis !== DocumentService::TYPE_DIGITAL
                && (int) $surat->tahun !== $this->activeYear->current(),
        ];

        return view('app.surat.detail-item', $data);
    }

    public function berkas(Request $request)
    {
        $request->validate([
            'isi' => ['nullable', 'in:kosong'],
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date', 'after_or_equal:tanggal_dari'],
        ]);

        if ($request->ajax()) {
            $kodeKlasifikasi = $request->input('kode_klasifikasi');
            $keteranganAkhir = $request->input('keterangan_akhir');
            $tanggalDari = $request->input('tanggal_dari');
            $tanggalSampai = $request->input('tanggal_sampai');

            $query = Filelist::select(
                'filelists.*',
                'classifications.kode_klasifikasi as kode_klasifikasi',
                'statuses.nama_status as nama_status'
            )
                ->withCount([
                    'incomings as total_incomings' => function ($countQuery) use ($tanggalDari, $tanggalSampai) {
                        if (! empty($tanggalDari)) {
                            $countQuery->whereDate('incomings.tanggal_surat', '>=', $tanggalDari);
                        }

                        if (! empty($tanggalSampai)) {
                            $countQuery->whereDate('incomings.tanggal_surat', '<=', $tanggalSampai);
                        }
                    },
                    'outcomings as total_outcomings' => function ($countQuery) use ($tanggalDari, $tanggalSampai) {
                        if (! empty($tanggalDari)) {
                            $countQuery->whereDate('outcomings.tanggal_surat', '>=', $tanggalDari);
                        }

                        if (! empty($tanggalSampai)) {
                            $countQuery->whereDate('outcomings.tanggal_surat', '<=', $tanggalSampai);
                        }
                    },
                ])
                ->join('classifications', 'filelists.classification_id', '=', 'classifications.id')
                ->join('statuses', 'filelists.status_id', '=', 'statuses.id')
                ->orderBy('classifications.kode_klasifikasi', 'asc')
                ->orderBy('filelists.nama_berkas', 'asc');

            if ($request->filled('status_id')) {
                $query->where('filelists.status_id', $request->status_id);
            }

            if (! empty($kodeKlasifikasi)) {
                $query->where('classifications.kode_klasifikasi', $kodeKlasifikasi);
            }

            if (in_array($keteranganAkhir, ['Permanen', 'Musnah'], true)) {
                $query->where('filelists.keterangan_akhir', $keteranganAkhir);
            }

            if ($request->input('isi') === 'kosong') {
                $query->withoutContents();
            }

            $isFilterItem = $request->filled('tanggal_dari') || $request->filled('tanggal_sampai');

            if ($isFilterItem) {
                $query->where(function ($filterQuery) use ($tanggalDari, $tanggalSampai) {
                    $filterQuery->orWhereExists(function ($subQuery) use ($tanggalDari, $tanggalSampai) {
                        $subQuery->selectRaw('1')
                            ->from('incomings')
                            ->whereColumn('incomings.filelist_id', 'filelists.id')
                            ->whereNull('incomings.deleted_at');

                        if (! empty($tanggalDari)) {
                            $subQuery->whereDate('incomings.tanggal_surat', '>=', $tanggalDari);
                        }

                        if (! empty($tanggalSampai)) {
                            $subQuery->whereDate('incomings.tanggal_surat', '<=', $tanggalSampai);
                        }
                    });

                    $filterQuery->orWhereExists(function ($subQuery) use ($tanggalDari, $tanggalSampai) {
                        $subQuery->selectRaw('1')
                            ->from('outcomings')
                            ->whereColumn('outcomings.filelist_id', 'filelists.id')
                            ->whereNull('outcomings.deleted_at');

                        if (! empty($tanggalDari)) {
                            $subQuery->whereDate('outcomings.tanggal_surat', '>=', $tanggalDari);
                        }

                        if (! empty($tanggalSampai)) {
                            $subQuery->whereDate('outcomings.tanggal_surat', '<=', $tanggalSampai);
                        }
                    });
                });
            }

            return DataTables::of($query)
                ->addColumn('total_isi', function ($data) {
                    return (int) $data->total_incomings + (int) $data->total_outcomings;
                })
                ->addColumn('keterangan_akhir', function ($data) {
                    return $data->keterangan_akhir ?: '-';
                })
                ->addColumn('aksi', function ($data) use ($request) {
                    $filterParams = array_filter([
                        'kode_klasifikasi' => $request->input('kode_klasifikasi'),
                        'status_id' => $request->input('status_id'),
                        'keterangan_akhir' => $request->input('keterangan_akhir'),
                        'isi' => $request->input('isi'),
                        'tanggal_dari' => $request->input('tanggal_dari'),
                        'tanggal_sampai' => $request->input('tanggal_sampai'),
                    ], function ($value) {
                        return $value !== null && $value !== '';
                    });

                    $menu = "<div class='dropdown text-center'>";
                    $menu .= "<button class='btn btn-sm btn-light border shadow-none' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false' title='Menu Aksi' style='width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;'>";
                    $menu .= "<i class='fa fa-ellipsis-v text-secondary'></i>";
                    $menu .= '</button>';
                    $menu .= "<div class='dropdown-menu dropdown-menu-right shadow border-0 py-1' style='min-width: 195px;'>";

                    $menu .= "<h6 class='dropdown-header text-uppercase font-weight-bold text-muted px-3 py-1' style='font-size: 0.72rem; letter-spacing: 0.5px;'>Aksi Berkas</h6>";

                    $menu .= "<a href='".e(route('berkas.buka', array_merge([$data['id']], $filterParams)))."' class='dropdown-item text-success py-2 d-flex align-items-center' target='_blank' rel='noopener noreferrer' title='Buka Berkas'>";
                    $menu .= "<i class='fa fa-folder-open mr-2' style='width: 16px; text-align: center;'></i> <span>Buka Berkas</span>";
                    $menu .= '</a>';

                    if ($data->isAlihMediaLocked()) {
                        $menu .= "<span class='dropdown-item disabled text-muted py-2 d-flex align-items-center' title='Terkunci karena alih media'>";
                        $menu .= "<i class='fa fa-lock mr-2' style='width: 16px; text-align: center;'></i> <span>Terkunci (Alih Media)</span>";
                        $menu .= '</span>';
                    } else {
                        $menu .= "<form action='".e(route('berkas.hapus', [$data['id']]))."' class='m-0' method='POST'>";
                        $menu .= csrf_field().method_field('delete');
                        $menu .= "<button type='submit' class='dropdown-item text-danger py-2 d-flex align-items-center konfirmasi-hapus' title='Hapus Berkas'>";
                        $menu .= "<i class='fa fa-trash mr-2 text-danger' style='width: 16px; text-align: center;'></i> <span>Hapus Berkas</span>";
                        $menu .= '</button></form>';
                    }

                    $statusItems = $this->berkasStatusTransitions($data);
                    if ($statusItems !== '') {
                        $menu .= "<div class='dropdown-divider my-1'></div>";
                        $menu .= "<h6 class='dropdown-header text-uppercase font-weight-bold text-muted px-3 py-1' style='font-size: 0.72rem; letter-spacing: 0.5px;'>Ubah Status</h6>";
                        $menu .= $statusItems;
                    }

                    $menu .= '</div></div>';

                    return $menu;
                })
                ->rawColumns(['aksi'])->toJson();
        }

        $data = [
            'judul' => 'List Berkas',
            'classifications' => Classification::orderBy('kode_klasifikasi', 'asc')->get(['id', 'kode_klasifikasi', 'keterangan']),
            'statuses' => Status::orderBy('nama_status', 'asc')->get(),
        ];

        return view('app.surat.berkas.index', $data);
    }

    public function alihMediaPenyeleksian(Request $request)
    {
        if ($request->ajax()) {
            $statusFokusAlihMedia = $this->getStatusFokusAlihMedia();
            $query = $this->queryAlihMediaBerkas()
                ->whereNull('filelists.alih_media_status_id')
                ->whereIn('statuses.nama_status', $statusFokusAlihMedia);

            return $this->datatableAlihMedia($query, true);
        }

        $data = [
            'judul' => 'Penyeleksian Alih Media',
        ];

        return view('app.alih-media.penyeleksian.index', $data);
    }

    public function alihMediaDiproses(Request $request)
    {
        if ($request->ajax()) {
            $query = $this->queryAlihMediaBerkas()
                ->whereIn('filelists.alih_media_status_id', [
                    Filelist::ALIH_MEDIA_PROCESSING,
                    Filelist::ALIH_MEDIA_DONE,
                    Filelist::ALIH_MEDIA_FAILED,
                ]);

            return $this->datatableAlihMedia($query, false);
        }

        $data = [
            'judul' => 'Pemrosesan Alih Media',
        ];

        return view('app.alih-media.diproses.index', $data);
    }

    public function alihMediaSelesai(Request $request)
    {
        if ($request->ajax()) {
            $query = $this->queryAlihMediaBerkas()
                ->where('filelists.alih_media_status_id', Filelist::ALIH_MEDIA_CLOSED);

            return $this->datatableAlihMedia($query, false);
        }

        $data = [
            'judul' => 'Selesai Alih Media',
        ];

        return view('app.alih-media.selesai.index', $data);
    }

    private function queryAlihMediaBerkas()
    {
        return Filelist::select(
            'filelists.*',
            'classifications.kode_klasifikasi as kode_klasifikasi',
            'statuses.nama_status as nama_status'
        )
            ->withCount([
                'incomings as total_incomings',
                'outcomings as total_outcomings',
            ])
            ->with([
                'incomings:id,filelist_id,url_watermarked',
                'outcomings:id,filelist_id,url_watermarked',
            ])
            ->join('classifications', 'filelists.classification_id', '=', 'classifications.id')
            ->join('statuses', 'filelists.status_id', '=', 'statuses.id')
            ->where('filelists.keterangan_akhir', 'Permanen')
            ->orderBy('classifications.kode_klasifikasi', 'asc')
            ->orderBy('filelists.nama_berkas', 'asc');
    }

    private function getStatusFokusAlihMedia(): array
    {
        $statusPemrosesan = Filelist::join('statuses', 'filelists.status_id', '=', 'statuses.id')
            ->where('filelists.keterangan_akhir', 'Permanen')
            ->whereIn('filelists.alih_media_status_id', [
                Filelist::ALIH_MEDIA_PROCESSING,
                Filelist::ALIH_MEDIA_DONE,
                Filelist::ALIH_MEDIA_FAILED,
            ])
            ->whereIn('statuses.nama_status', ['Aktif', 'Inaktif'])
            ->distinct()
            ->pluck('statuses.nama_status')
            ->values()
            ->all();

        return count($statusPemrosesan) > 0 ? $statusPemrosesan : ['Aktif', 'Inaktif'];
    }

    private function datatableAlihMedia($query, bool $allowProcess)
    {
        return DataTables::of($query)
            ->addColumn('total_isi', function ($data) {
                return (int) $data->total_incomings + (int) $data->total_outcomings;
            })
            ->addColumn('status_alih_media', function ($data) {
                $statusAlihMedia = (int) $data->alih_media_status_id;
                $totalIsi = (int) $data->total_incomings + (int) $data->total_outcomings;
                $totalWatermarked = $data->incomings->filter(function ($surat) {
                    return $surat->hasExistingWatermarkedFile();
                })->count()
                    + $data->outcomings->filter(function ($surat) {
                        return $surat->hasExistingWatermarkedFile();
                    })->count();
                $semuaWatermarked = $totalIsi > 0 && $totalIsi === $totalWatermarked;
                $sebagianWatermarked = $totalWatermarked > 0 && $totalWatermarked < $totalIsi;

                if ($semuaWatermarked && in_array($statusAlihMedia, [Filelist::ALIH_MEDIA_PROCESSING, Filelist::ALIH_MEDIA_FAILED], true)) {
                    return "<span class='badge badge-info d-block p-2'>Status Tidak Sinkron: Semua PDF Sudah Watermark</span>";
                }

                if ($statusAlihMedia === Filelist::ALIH_MEDIA_DONE && ! $semuaWatermarked) {
                    return "<span class='badge badge-info d-block p-2'>Status Tidak Sinkron: PDF Belum Lengkap</span>";
                }

                if ((int) $data->alih_media_status_id === Filelist::ALIH_MEDIA_PROCESSING) {
                    if ($sebagianWatermarked) {
                        return "<span class='badge badge-warning d-block p-2'>Sedang Diproses: {$totalWatermarked}/{$totalIsi} PDF</span>";
                    }

                    return "<span class='badge badge-warning d-block p-2'>Sedang Diproses</span>";
                }

                if ((int) $data->alih_media_status_id === Filelist::ALIH_MEDIA_DONE) {
                    return "<span class='badge badge-success d-block p-2'>Sudah Diproses</span>";
                }

                if ((int) $data->alih_media_status_id === Filelist::ALIH_MEDIA_FAILED) {
                    return "<span class='badge badge-danger d-block p-2'>Gagal / Bisa Diulang</span>";
                }

                if ((int) $data->alih_media_status_id === Filelist::ALIH_MEDIA_CLOSED) {
                    return "<span class='badge badge-success d-block p-2'>Selesai Ditutup</span>";
                }

                return "<span class='badge badge-secondary d-block p-2'>Belum Diproses</span>";
            })
            ->addColumn('aksi', function ($data) use ($allowProcess) {
                $button = "<div class='d-flex justify-content-center'>";
                $button .= "<a href='".e(route('berkas.buka', [$data['id']]))."' class='btn btn-success btn-sm mr-1' target='_blank' rel='noopener noreferrer' title='Buka Berkas'><i class='fa fa-folder-open'></i></a>";

                if ((int) $data->alih_media_status_id === Filelist::ALIH_MEDIA_FAILED) {
                    $button .= "<form action='".e(route('alih-media.diproses.ulangi', [$data['id']]))."' class='m-0' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-primary btn-sm' title='Ulangi Proses'><i class='fa fa-redo'></i></button></form>";
                } elseif ($allowProcess) {
                    $button .= "<form action='".e(route('alih-media.penyeleksian.proses', [$data['id']]))."' class='m-0 konfirmasi-proses-alih-media' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-primary btn-sm' title='Proses'><i class='fa fa-cogs'></i></button></form>";
                }

                $button .= '</div>';

                return $button;
            })
            ->rawColumns(['status_alih_media', 'aksi'])->toJson();
    }

    private function berkasStatusTransitions(Filelist $data): string
    {
        if ($data->isAlihMediaLocked()) {
            return "<span class='dropdown-item disabled text-muted py-2 d-flex align-items-center'>"
                ."<i class='fa fa-lock mr-2 text-secondary' style='width: 16px; text-align: center;'></i> <span>Terkunci Alih Media</span></span>";
        }

        $html = '';
        if ($data->status_id == 1) {
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 2]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-secondary py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-arrow-right mr-2 text-secondary' style='width: 16px; text-align: center;'></i> <span>Usul Pindah UP ke UK</span></button></form>";
        } elseif ($data->status_id == 2) {
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 1]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-info py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-undo mr-2 text-info' style='width: 16px; text-align: center;'></i> <span>Kembalikan ke Aktif</span></button></form>";
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 3]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-secondary py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-arrow-right mr-2 text-secondary' style='width: 16px; text-align: center;'></i> <span>Pindah ke Inaktif</span></button></form>";
        } elseif ($data->status_id == 3) {
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 2]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-info py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-undo mr-2 text-info' style='width: 16px; text-align: center;'></i> <span>Usul Pindah UP ke UK</span></button></form>";
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 4]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-warning py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-fire mr-2 text-warning' style='width: 16px; text-align: center;'></i> <span>Usul Musnah</span></button></form>";
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 6]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-success py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-archive mr-2 text-success' style='width: 16px; text-align: center;'></i> <span>Usul Permanen</span></button></form>";
        } elseif ($data->status_id == 4) {
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 3]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-info py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-undo mr-2 text-info' style='width: 16px; text-align: center;'></i> <span>Kembalikan ke Inaktif</span></button></form>";
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 5]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-danger py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-fire-alt mr-2 text-danger' style='width: 16px; text-align: center;'></i> <span>Musnahkan Berkas</span></button></form>";
        } elseif ($data->status_id == 6) {
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 3]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-info py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-undo mr-2 text-info' style='width: 16px; text-align: center;'></i> <span>Kembalikan ke Inaktif</span></button></form>";
            $html .= "<form action='".e(route('berkas.pindah', [$data['id'], 7]))."' class='m-0' method='POST'>".csrf_field()
                ."<button type='submit' class='dropdown-item text-success py-2 d-flex align-items-center konfirmasi-pindah'>"
                ."<i class='fa fa-check-circle mr-2 text-success' style='width: 16px; text-align: center;'></i> <span>Jadikan Permanen</span></button></form>";
        }

        return $html;
    }
}
