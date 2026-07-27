<?php

namespace App\Http\Controllers\Surat;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Filelist;
use App\Models\Incoming;
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

class SuratMasukController extends Controller
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
            'judul' => 'Tambah Surat Masuk',
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.masuk.tambah', $data);
    }

    public function store()
    {
        $isSrikandi = request()->boolean('isSrikandi');
        if ($isSrikandi) {
            request()->merge(['nomorAgenda' => null, 'pemberkasan' => null]);
        } elseif (request('pemberkasan') === 'null' || request('pemberkasan') === '') {
            request()->merge(['pemberkasan' => null]);
        }

        request()->validate([
            'isSrikandi' => ['sometimes', 'boolean'],
            'nomorAgenda' => $isSrikandi
                ? ['nullable']
                : ['required', 'integer', 'min:1'],
            'tanggalDiterima' => ['required', 'date'],
            'tanggalSurat' => ['nullable', 'date'],
            'nomorSurat' => ['required', 'string', 'max:255'],
            'pengirim' => ['required', 'string', 'max:65535'],
            'perihal' => ['required', 'string', 'max:65535'],
            'sifat' => ['required', 'integer', Rule::exists('accesses', 'id')],
            'pemberkasan' => [
                'nullable',
                'integer',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'berkas' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ],
        ]);

        if ($isSrikandi) {
            $nomorAgenda = null;
        } else {
            $agendaSudahDigunakan = Incoming::withTrashed()
                ->where('nomor_agenda', request('nomorAgenda'))
                ->where('tahun', Auth::user()->tahun)
                ->exists();
            if ($agendaSudahDigunakan) {
                Alert::error('Gagal', 'Nomor Agenda Sudah Digunakan');

                return redirect()->route('masuk.tambah');
            }

            $nomorAgenda = request('nomorAgenda');
        }

        if (! $this->isValidPemberkasanTujuan(request('pemberkasan'))) {
            Alert::error('Gagal', 'Berkas tujuan sudah dialihmediakan atau tidak valid');

            return redirect()->route('masuk.tambah')->withInput();
        }

        $dokumen = app(DocumentService::class)->storeOriginal(
            DocumentService::TYPE_INCOMING,
            request()->file('berkas')
        );

        try {
            $masukkan = new Incoming([
                'nomor_agenda' => $nomorAgenda,
                'tanggal_diterima' => request('tanggalDiterima'),
                'nomor_surat' => request('nomorSurat'),
                'pengirim' => request('pengirim'),
                'tanggal_surat' => request('tanggalSurat'),
                'perihal' => request('perihal'),
                'url' => $dokumen,
                'tahun' => Auth::user()->tahun,
                'is_srikandi' => $isSrikandi,
                'filelist_id' => request('pemberkasan') == 'null' ? null : request('pemberkasan'),
                'access_id' => request('sifat'),
            ]);
            DB::transaction(function () use ($masukkan, $nomorAgenda) {
                app(FilelistMutationLock::class)->lock(null, $masukkan->filelist_id);
                $this->ensureAgendaAvailable($nomorAgenda, Auth::user()->tahun);
                $masukkan->saveOrFail();
            });
        } catch (Throwable $exception) {
            Storage::disk(config('documents.disk'))->delete($dokumen);
            throw $exception;
        }

        Alert::success('Berhasil', 'Surat Masuk Berhasil Ditambahkan');

        return redirect()->route('surat.masuk');
    }

    public function hapus($id)
    {
        $surat = Incoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Masuk Tidak Ditemukan');

            return redirect()->route('surat.masuk');
        }

        if ($surat->tahun != Auth::user()->tahun) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.masuk');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat dihapus');

            return redirect()->route('surat.masuk');
        }

        $currentFilelistId = $surat->filelist_id;
        try {
            $deleted = DB::transaction(function () use ($id, $currentFilelistId) {
                $filelists = app(FilelistMutationLock::class)->lock($currentFilelistId, null);
                $lockedSurat = Incoming::lockForUpdate()->find($id);

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
            Alert::success('Berhasil', 'Surat Masuk Berhasil Dihapus');

            return redirect()->route('surat.masuk');
        } else {
            Alert::error('Gagal', 'Surat Masuk gagal dihapus karena datanya berubah atau masuk proses alih media');

            return redirect()->route('surat.masuk');
        }
    }

    public function edit($id)
    {
        $surat = Incoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Masuk Tidak Ditemukan');

            return redirect()->route('surat.masuk');
        } elseif ($surat->tahun != Auth::user()->tahun) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.masuk');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.masuk');
        }

        $data = [
            'judul' => 'Edit Surat Masuk',
            'data' => $surat,
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.masuk.edit', $data);
    }

    public function update($id)
    {
        $surat = Incoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Masuk Tidak Ditemukan');

            return redirect()->route('surat.masuk');
        }

        if ($surat->tahun != Auth::user()->tahun) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.masuk');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.masuk');
        }

        $isSrikandi = request()->boolean('isSrikandi');
        if ($isSrikandi) {
            request()->merge(['nomorAgenda' => null, 'pemberkasan' => null]);
        } elseif (request('pemberkasan') === 'null' || request('pemberkasan') === '') {
            request()->merge(['pemberkasan' => null]);
        }

        request()->validate([
            'isSrikandi' => ['sometimes', 'boolean'],
            'nomorAgenda' => $isSrikandi
                ? ['nullable']
                : ['required', 'integer', 'min:1'],
            'tanggalDiterima' => ['required', 'date'],
            'tanggalSurat' => ['nullable', 'date'],
            'nomorSurat' => ['required', 'string', 'max:255'],
            'pengirim' => ['required', 'string', 'max:65535'],
            'perihal' => ['required', 'string', 'max:65535'],
            'sifat' => ['required', 'integer', Rule::exists('accesses', 'id')],
            'pemberkasan' => [
                'nullable',
                'integer',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'berkas' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ],
        ]);

        if ($isSrikandi) {
            $nomorAgenda = null;
        } else {
            $agendaSudahDigunakan = Incoming::withTrashed()
                ->where('nomor_agenda', request('nomorAgenda'))
                ->where('tahun', Auth::user()->tahun)
                ->where('id', '!=', $id)
                ->exists();
            if ($agendaSudahDigunakan) {
                Alert::error('Gagal', 'Nomor Agenda Sudah Digunakan');

                return redirect()->route('masuk.edit', $id);
            }

            $nomorAgenda = request('nomorAgenda');
        }

        if (! $this->isValidPemberkasanTujuan(request('pemberkasan'))) {
            Alert::error('Gagal', 'Berkas tujuan sudah dialihmediakan atau tidak valid');

            return redirect()->route('masuk.edit', $id)->withInput();
        }

        $dokumenBaru = request()->file('berkas')
            ? app(DocumentService::class)->storeOriginal(
                DocumentService::TYPE_INCOMING,
                request()->file('berkas')
            )
            : null;
        $dokumenLama = $surat->url;
        $currentFilelistId = $surat->filelist_id;

        $surat->fill([
            'nomor_agenda' => $nomorAgenda,
            'tanggal_diterima' => request('tanggalDiterima'),
            'nomor_surat' => request('nomorSurat'),
            'pengirim' => request('pengirim'),
            'tanggal_surat' => request('tanggalSurat'),
            'perihal' => request('perihal'),
            'url' => $dokumenBaru ?: $dokumenLama,
            'is_srikandi' => $isSrikandi,
            'filelist_id' => request('pemberkasan') == 'null' ? null : request('pemberkasan'),
            'access_id' => request('sifat'),
        ]);
        $changes = $surat->getDirty();

        try {
            DB::transaction(function () use (&$surat, $changes, $nomorAgenda, $id, $currentFilelistId) {
                $filelists = app(FilelistMutationLock::class)->lock(
                    $currentFilelistId,
                    $changes['filelist_id'] ?? $surat->filelist_id
                );
                $lockedSurat = Incoming::lockForUpdate()->find($id);

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

                $this->ensureAgendaAvailable($nomorAgenda, Auth::user()->tahun, $id);
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

        if ($dokumenBaru && $dokumenLama !== $dokumenBaru) {
            app(DocumentService::class)->delete(
                DocumentService::TYPE_INCOMING,
                $dokumenLama
            );
        }

        Alert::success('Berhasil', 'Surat Masuk Berhasil Diubah');

        return redirect()->route('surat.masuk');
    }

    private function isValidPemberkasanTujuan($filelistId): bool
    {
        if ($filelistId === null || $filelistId === '' || $filelistId === 'null') {
            return true;
        }

        return Filelist::where('id', $filelistId)
            ->where('status_id', 1)
            ->whereNull('alih_media_status_id')
            ->exists();
    }

    private function ensureAgendaAvailable($nomorAgenda, int $tahun, ?int $ignoreId = null): void
    {
        if ($nomorAgenda === null) {
            return;
        }

        $query = Incoming::withTrashed()
            ->where('nomor_agenda', $nomorAgenda)
            ->where('tahun', $tahun);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'nomorAgenda' => 'Nomor Agenda Sudah Digunakan',
            ]);
        }
    }

    public function exportPencatatanExcel(
        Request $request,
        SuratFilterQuery $suratFilter,
        ExportActivityLogger $exportLogger
    ) {
        $filters = $suratFilter->validateIncoming($request);
        $sumberSurat = $filters['sumber_surat'];
        $tanggalDari = $filters['tanggal_dari'];
        $tanggalSampai = $filters['tanggal_sampai'];

        $suratMasuk = $suratFilter
            ->incoming((int) Auth::user()->tahun, $filters)
            ->orderBy('tanggal_surat', 'asc')
            ->orderBy('nomor_surat', 'asc')
            ->get();

        $sumberLabels = [
            'semua' => 'Semua',
            'srikandi' => 'Dari SRIKANDI',
            'non_srikandi' => 'Bukan dari SRIKANDI',
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
        $sheet->setTitle('Pencatatan Naskah Masuk');
        $sheet->setCellValue('A1', 'Agenda Elektronik Penerimaan dan Pencatatan Naskah Dinas Masuk');
        $sheet->setCellValue('A2', config('app.pencipta_arsip'));
        $sheet->setCellValue('A3', 'Tahun '.Auth::user()->tahun);
        $sheet->setCellValue('A4', 'Sumber Surat: '.$sumberLabels[$sumberSurat]);
        $sheet->setCellValue('A5', 'Periode Tanggal Surat: '.$periode);
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');
        $sheet->mergeCells('A4:H4');
        $sheet->mergeCells('A5:H5');

        $headers = [
            'A' => 'Nomor Agenda',
            'B' => 'Sumber Surat',
            'C' => 'Pengirim',
            'D' => 'Penerima Surat',
            'E' => 'Tanggal Surat',
            'F' => 'Tanggal Terima',
            'G' => 'Nomor Surat',
            'H' => 'Perihal',
        ];

        $headerRow = 7;
        foreach ($headers as $column => $title) {
            $sheet->setCellValue($column.$headerRow, $title);
        }

        $row = $headerRow + 1;
        foreach ($suratMasuk as $surat) {
            $sheet->setCellValue(
                'A'.$row,
                $surat->is_srikandi ? 'SRIKANDI' : (string) $surat->nomor_agenda
            );
            $sheet->setCellValue(
                'B'.$row,
                $surat->is_srikandi ? 'Dari SRIKANDI' : 'Bukan dari SRIKANDI'
            );
            $sheet->setCellValue('C'.$row, (string) ($surat->pengirim ?? '-'));
            $sheet->setCellValue('D'.$row, '-');
            $sheet->setCellValue('E'.$row, $this->formatTanggalIndonesia($surat->tanggal_surat));
            $sheet->setCellValue('F'.$row, $this->formatTanggalIndonesia($surat->tanggal_diterima));
            $sheet->setCellValue('G'.$row, (string) ($surat->nomor_surat ?? '-'));
            $sheet->setCellValue('H'.$row, (string) ($surat->perihal ?? '-'));
            $row++;
        }

        $lastRow = max($headerRow, $row - 1);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(36);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(26);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle('A1:A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A'.$headerRow.':H'.$headerRow)->applyFromArray([
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

        $sheet->getStyle('A'.$headerRow.':H'.$lastRow)->applyFromArray([
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
        $sheet->setAutoFilter('A'.$headerRow.':H'.$headerRow);

        $fileName = 'pencatatan-naskah-masuk-'
            .$sumberSurat
            .'-'
            .now()->format('Ymd-His')
            .'.xlsx';

        $exportLogger->logPrepared(
            'pencatatan_surat_masuk',
            $suratMasuk->count(),
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
