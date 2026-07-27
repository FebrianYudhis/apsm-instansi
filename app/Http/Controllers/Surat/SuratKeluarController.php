<?php

namespace App\Http\Controllers\Surat;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Filelist;
use App\Models\Outcoming;
use App\Rules\ValidPdf;
use App\Services\DocumentService;
use App\Services\ExportActivityLogger;
use App\Services\FilelistMutationLock;
use App\Services\SafeSpreadsheetValueBinder;
use App\Services\SuratFilterQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RealRashid\SweetAlert\Facades\Alert;
use Throwable;

class SuratKeluarController extends Controller
{
    private function formatTanggalIndonesia($tanggal): string
    {
        if (empty($tanggal) || $tanggal === '-') {
            return '-';
        }

        try {
            return Carbon::parse($tanggal)->format('d-m-Y');
        } catch (Throwable $th) {
            return (string) $tanggal;
        }
    }

    public function tambah()
    {
        $data = [
            'judul' => 'Tambah Surat Keluar',
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.keluar.tambah', $data);
    }

    public function store()
    {
        $data = $this->validateRequest(true);

        $dokumen = app(DocumentService::class)->storeOriginal(
            DocumentService::TYPE_OUTGOING,
            request()->file('berkas')
        );

        try {
            $surat = new Outcoming([
                'tanggal_surat' => $data['tanggalSurat'],
                'nomor_surat' => $data['nomorSurat'],
                'tujuan' => $data['tujuan'],
                'perihal' => $data['perihal'],
                'url' => $dokumen,
                'tahun' => Auth::user()->tahun,
                'is_digital' => $data['is_digital'],
                'is_srikandi' => $data['is_srikandi'],
                'filelist_id' => $data['pemberkasan'],
                'access_id' => $data['sifat'],
            ]);
            DB::transaction(function () use ($surat) {
                app(FilelistMutationLock::class)->lock(null, $surat->filelist_id);
                $surat->saveOrFail();
            });
        } catch (Throwable $exception) {
            Storage::disk(config('documents.disk'))->delete($dokumen);
            throw $exception;
        }

        Alert::success('Berhasil', 'Surat Keluar Berhasil Ditambahkan');

        return redirect()->route('surat.keluar');
    }

    public function hapus($id)
    {
        $surat = Outcoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Keluar Tidak Ditemukan');

            return redirect()->route('surat.keluar');
        }

        if ($surat->tahun != Auth::user()->tahun) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.keluar');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat dihapus');

            return redirect()->route('surat.keluar');
        }

        $currentFilelistId = $surat->filelist_id;
        try {
            $deleted = DB::transaction(function () use ($id, $currentFilelistId) {
                $filelists = app(FilelistMutationLock::class)->lock($currentFilelistId, null);
                $lockedSurat = Outcoming::lockForUpdate()->find($id);

                if (
                    ! $lockedSurat
                    || (int) $lockedSurat->tahun !== (int) Auth::user()->tahun
                    || (int) $lockedSurat->filelist_id !== (int) $currentFilelistId
                ) {
                    return false;
                }

                if ($currentFilelistId !== null) {
                    $lockedSurat->setRelation('filelist', $filelists->get($currentFilelistId));
                }

                return ! $lockedSurat->isAlihMediaLocked() && $lockedSurat->delete();
            });
        } catch (ValidationException $exception) {
            $deleted = false;
        }

        if ($deleted) {
            Alert::success('Berhasil', 'Surat Keluar Berhasil Dihapus');

            return redirect()->route('surat.keluar');
        } else {
            Alert::error('Gagal', 'Surat Keluar gagal dihapus karena datanya berubah atau masuk proses alih media');

            return redirect()->route('surat.keluar');
        }
    }

    public function edit($id)
    {
        $surat = Outcoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Keluar Tidak Ditemukan');

            return redirect()->route('surat.keluar');
        } elseif ($surat->tahun != Auth::user()->tahun) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.keluar');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.keluar');
        }

        $data = [
            'judul' => 'Edit Surat Keluar',
            'data' => $surat,
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.keluar.edit', $data);
    }

    public function update($id)
    {
        $surat = Outcoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Keluar Tidak Ditemukan');

            return redirect()->route('surat.keluar');
        }

        if ($surat->tahun != Auth::user()->tahun) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.keluar');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.keluar');
        }

        $data = $this->validateRequest(false);
        $dokumenBaru = request()->file('berkas')
            ? app(DocumentService::class)->storeOriginal(
                DocumentService::TYPE_OUTGOING,
                request()->file('berkas')
            )
            : null;
        $urlLama = $surat->url;
        $watermarkLama = $surat->url_watermarked;
        $currentFilelistId = $surat->filelist_id;

        $surat->fill([
            'tanggal_surat' => $data['tanggalSurat'],
            'nomor_surat' => $data['nomorSurat'],
            'tujuan' => $data['tujuan'],
            'perihal' => $data['perihal'],
            'url' => $dokumenBaru ?: $urlLama,
            'is_digital' => $data['is_digital'],
            'is_srikandi' => $data['is_srikandi'],
            'filelist_id' => $data['pemberkasan'],
            'access_id' => $data['sifat'],
        ]);

        $watermarkTidakBerlaku = $dokumenBaru !== null || $surat->isDirty([
            'tanggal_surat',
            'nomor_surat',
            'tujuan',
            'perihal',
            'url',
            'is_digital',
            'is_srikandi',
            'filelist_id',
            'access_id',
        ]);

        if ($watermarkTidakBerlaku) {
            $surat->url_watermarked = null;
        }
        $changes = $surat->getDirty();

        try {
            DB::transaction(function () use (&$surat, $changes, $id, $currentFilelistId) {
                $filelists = app(FilelistMutationLock::class)->lock(
                    $currentFilelistId,
                    $changes['filelist_id'] ?? $surat->filelist_id
                );
                $lockedSurat = Outcoming::lockForUpdate()->find($id);

                if (
                    ! $lockedSurat
                    || (int) $lockedSurat->tahun !== (int) Auth::user()->tahun
                    || (int) $lockedSurat->filelist_id !== (int) $currentFilelistId
                ) {
                    throw ValidationException::withMessages([
                        'pemberkasan' => 'Data surat berubah saat diproses. Muat ulang halaman dan coba kembali.',
                    ]);
                }

                if ($currentFilelistId !== null) {
                    $lockedSurat->setRelation('filelist', $filelists->get($currentFilelistId));
                }

                if ($lockedSurat->isAlihMediaLocked()) {
                    throw ValidationException::withMessages([
                        'pemberkasan' => 'Surat sudah masuk proses alih media dan tidak dapat diedit.',
                    ]);
                }

                $lockedSurat->fill($changes);
                $lockedSurat->saveOrFail();
                $surat = $lockedSurat;
            });
        } catch (Throwable $exception) {
            if ($dokumenBaru) {
                Storage::disk(config('documents.disk'))->delete($dokumenBaru);
            }
            throw $exception;
        }

        if ($dokumenBaru && $urlLama !== $dokumenBaru) {
            app(DocumentService::class)->delete(
                DocumentService::TYPE_OUTGOING,
                $urlLama
            );
        }
        if ($watermarkTidakBerlaku && $watermarkLama && $watermarkLama !== $surat->url) {
            app(DocumentService::class)->delete(
                DocumentService::TYPE_OUTGOING,
                $watermarkLama,
                DocumentService::VARIANT_WATERMARK
            );
        }

        Alert::success('Berhasil', 'Surat Keluar Berhasil Diubah');

        return redirect()->route('surat.keluar');
    }

    private function validateRequest(bool $berkasWajib): array
    {
        $isSrikandi = request()->boolean('isSrikandi');
        $pemberkasan = request('pemberkasan');

        if ($isSrikandi) {
            request()->merge([
                'pemberkasan' => null,
            ]);
        } elseif ($pemberkasan === '' || $pemberkasan === 'null') {
            request()->merge(['pemberkasan' => null]);
        }

        $data = request()->validate([
            'jenis' => ['required', Rule::in([0, 1])],
            'isSrikandi' => ['sometimes', 'boolean'],
            'tanggalSurat' => ['required', 'date'],
            'nomorSurat' => ['required', 'string', 'max:255'],
            'tujuan' => ['required', 'string', 'max:65535'],
            'perihal' => ['required', 'string', 'max:65535'],
            'sifat' => ['required', 'integer', Rule::exists('accesses', 'id')],
            'pemberkasan' => [
                'nullable',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'berkas' => array_filter([
                $berkasWajib ? 'required' : 'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ]),
        ]);

        $data['is_srikandi'] = $isSrikandi;
        $data['is_digital'] = $isSrikandi || (int) $data['jenis'] === 1;
        $data['pemberkasan'] = $isSrikandi ? null : ($data['pemberkasan'] ?? null);

        return $data;
    }

    public function exportPencatatanExcel(
        Request $request,
        SuratFilterQuery $suratFilter,
        ExportActivityLogger $exportLogger
    ) {
        $filters = $suratFilter->validateOutgoing($request);
        $jalurPengiriman = $filters['jalur_pengiriman'];
        $tanggalDari = $filters['tanggal_dari'];
        $tanggalSampai = $filters['tanggal_sampai'];

        $suratKeluar = $suratFilter
            ->outgoing((int) Auth::user()->tahun, $filters)
            ->with('access')
            ->orderBy('tanggal_surat', 'asc')
            ->orderBy('nomor_surat', 'asc')
            ->get();

        $jalurLabels = [
            'semua' => 'Semua',
            'srikandi' => 'SRIKANDI',
            'non_srikandi' => 'Tanpa SRIKANDI',
        ];
        $periode = 'Semua tanggal';
        if ($tanggalDari && $tanggalSampai) {
            $periode = $this->formatTanggalIndonesia($tanggalDari)
                .' s.d. '
                .$this->formatTanggalIndonesia($tanggalSampai);
        } elseif ($tanggalDari) {
            $periode = 'Mulai '.$this->formatTanggalIndonesia($tanggalDari);
        } elseif ($tanggalSampai) {
            $periode = 'Sampai '.$this->formatTanggalIndonesia($tanggalSampai);
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new SafeSpreadsheetValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pencatatan Naskah Keluar');
        $sheet->setCellValue('A1', 'Agenda Elektronik Pencatatan Naskah Dinas Keluar');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A2', config('app.pencipta_arsip'));
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A3', 'Tahun '.Auth::user()->tahun);
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue(
            'A4',
            'Jalur Pengiriman: '.$jalurLabels[$jalurPengiriman]
        );
        $sheet->mergeCells('A4:F4');
        $sheet->setCellValue('A5', 'Periode Tanggal Surat: '.$periode);
        $sheet->mergeCells('A5:F5');

        $headers = [
            'A' => 'Tanggal Surat',
            'B' => 'Jalur Pengiriman',
            'C' => 'Nomor Surat',
            'D' => 'Tujuan',
            'E' => 'Perihal',
            'F' => 'SKKAAD',
        ];

        $headerRow = 7;
        foreach ($headers as $column => $title) {
            $sheet->setCellValue($column.$headerRow, $title);
        }

        $row = $headerRow + 1;
        foreach ($suratKeluar as $surat) {
            $sheet->setCellValue('A'.$row, $this->formatTanggalIndonesia($surat->tanggal_surat));
            $sheet->setCellValue('B'.$row, $surat->is_srikandi ? 'SRIKANDI' : 'Tanpa SRIKANDI');
            $sheet->setCellValue('C'.$row, (string) ($surat->nomor_surat ?? '-'));
            $sheet->setCellValue('D'.$row, (string) ($surat->tujuan ?? '-'));
            $sheet->setCellValue('E'.$row, (string) ($surat->perihal ?? '-'));
            $sheet->setCellValue('F'.$row, (string) (optional($surat->access)->sifat_akses ?? '-'));
            $row++;
        }

        $lastRow = max($headerRow, $row - 1);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(36);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(26);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle('A1:A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A'.$headerRow.':F'.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A'.$headerRow.':F'.$lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '808080'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        foreach (array_keys($headers) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setAutoFilter('A'.$headerRow.':F'.$headerRow);

        $fileName = 'pencatatan-naskah-keluar-'
            .$jalurPengiriman
            .'-'
            .now()->format('Ymd-His')
            .'.xlsx';

        $exportLogger->logPrepared(
            'pencatatan_surat_keluar',
            $suratKeluar->count(),
            $fileName,
            $filters,
            ['cakupan' => 'tahun_aktif']
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            try {
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
