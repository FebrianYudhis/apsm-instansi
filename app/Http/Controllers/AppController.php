<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\DataTables;

class AppController extends Controller
{
    public function digital(Request $request)
    {
        if ($request->ajax()) {
            $query = Digital::query();

            return DataTables::of($query)
                ->addColumn('aksi', function ($data) {
                    $button = "<div class='d-flex justify-content-center'>";
                    if (! empty($data['url'])) {
                        $button .= "<a href='".e(route('document.admin', [
                            'jenis' => 'digital',
                            'id' => $data['id'],
                            'versi' => 'asli',
                        ]))."' target='_blank' rel='noopener noreferrer' class='btn btn-success btn-sm mr-1' title='Lihat Berkas (PDF)'><i class='fa fa-file-pdf'></i></a>";
                    }
                    $button .= "<a href='".e(route('digital.edit', [$data['id']]))."' class='btn btn-primary btn-sm mr-1' title='Edit'><i class='fa fa-edit'></i></a>";
                    $button .= "<form action='".e(route('digital.hapus', [$data['id']]))."' class='m-0 konfirmasi-hapus' method='POST'> ".csrf_field().method_field('delete')." <button type='submit' class='btn btn-danger btn-sm' title='Hapus'><i class='fa fa-trash'></i></button></form>";
                    $button .= '</div>';

                    return $button;
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
                    $button = "<div class='d-flex justify-content-center'>";
                    $button .= "<a href='".e(route('klasifikasi.edit', [$data['id']]))."' class='btn btn-primary btn-sm mr-1' title='Edit'><i class='fa fa-edit'></i></a>";
                    $button .= "<form action='".e(route('klasifikasi.hapus', [$data['id']]))."' class='m-0 konfirmasi-hapus' method='POST'> ".csrf_field().method_field('delete')." <button type='submit' class='btn btn-danger btn-sm' title='Hapus'><i class='fa fa-trash'></i></button></form>";
                    $button .= '</div>';

                    return $button;
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
        } else {
            Alert::error('Gagal', 'Jenis Surat Tidak Valid');

            return redirect()->back();
        }

        if (! $surat) {
            Alert::error('Gagal', 'Surat Tidak Ditemukan');

            return redirect()->back();
        }

        $editRoute = $jenis == 'masuk'
            ? route('masuk.edit', [$surat->id])
            : route('keluar.edit', [$surat->id]);
        $editPath = $jenis == 'masuk'
            ? route('masuk.edit', [$surat->id], false)
            : route('keluar.edit', [$surat->id], false);

        $data = [
            'judul' => 'Detail Naskah',
            'jenis' => $jenis,
            'surat' => $surat,
            'editUrl' => $editRoute,
            'editPath' => $editPath,
            'requiresYearSwitch' => (int) $surat->tahun !== (int) Auth::user()->tahun,
        ];

        return view('app.surat.detail-item', $data);
    }

    public function berkas(Request $request)
    {
        $request->validate([
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
                        'tanggal_dari' => $request->input('tanggal_dari'),
                        'tanggal_sampai' => $request->input('tanggal_sampai'),
                    ], function ($value) {
                        return $value !== null && $value !== '';
                    });

                    $button = "<div class='d-flex justify-content-center'>";
                    $button .= "<a href='".e(route('berkas.buka', array_merge([$data['id']], $filterParams)))."' class='btn btn-success btn-sm mr-1' target='_blank' rel='noopener noreferrer' title='Buka Berkas'><i class='fa fa-folder-open'></i></a>";
                    if ($data->isAlihMediaLocked()) {
                        $button .= "<button type='button' class='btn btn-secondary btn-sm' title='Terkunci karena alih media' disabled><i class='fa fa-lock'></i></button>";
                    } else {
                        $button .= "<form action='".e(route('berkas.hapus', [$data['id']]))."' class='m-0 konfirmasi-hapus' method='POST'> ".csrf_field().method_field('delete')." <button type='submit' class='btn btn-danger btn-sm' title='Hapus'><i class='fa fa-trash'></i></button></form>";
                    }
                    $button .= '</div>';

                    return $button;
                })->addColumn('status', function ($data) {
                    if ($data->isAlihMediaLocked()) {
                        return '<span class="badge badge-secondary">Terkunci Alih Media</span>';
                    }

                    $button = '';
                    if ($data->status_id == 1) {
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 2]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-secondary w-100'>Usul Pindah UP ke UK</button></form>";
                    } elseif ($data->status_id == 2) {
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 1]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-info w-100'>Aktif</button></form>";
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 3]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-secondary w-100'>Inaktif</button></form>";
                    } elseif ($data->status_id == 3) {
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 2]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-info w-100'>Usul Pindah UP ke UK</button></form>";
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 4]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-secondary w-100'>Usul Musnah</button></form>";
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 6]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-secondary w-100'>Usul Permanen</button></form>";
                    } elseif ($data->status_id == 4) {
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 3]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-info w-100'>Inaktif</button></form>";
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 5]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-secondary w-100'>Musnah</button></form>";
                    } elseif ($data->status_id == 6) {
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 3]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-info w-100'>Inaktif</button></form>";
                        $button = $button."<form action='".e(route('berkas.pindah', [$data['id'], 7]))."' class='mt-1 w-100 konfirmasi-pindah' method='POST'> ".csrf_field()." <button type='submit' class='btn btn-secondary w-100'>Permanen</button></form>";
                    } elseif ($data->status_id == 5) {
                        $button = '<span class="badge badge-danger">Musnah</span>';
                    } elseif ($data->status_id == 7) {
                        $button = '<span class="badge badge-success">Permanen</span>';
                    }

                    return $button;
                })
                ->rawColumns(['aksi', 'status'])->toJson();
        }

        $data = [
            'judul' => 'List Berkas',
            'classifications' => Classification::orderBy('kode_klasifikasi', 'asc')->get(['id', 'kode_klasifikasi']),
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
}
