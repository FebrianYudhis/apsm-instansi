<?php

namespace App\Http\Controllers;

use App\Models\Filelist;
use App\Models\Status;
use App\Services\ExportActivityLogger;
use App\Services\SafeSpreadsheetValueBinder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BerkasExportController extends Controller
{
    private function formatTanggalIndonesia($tanggal): string
    {
        if (empty($tanggal) || $tanggal === '-') {
            return '-';
        }

        try {
            return Carbon::parse($tanggal)->format('d-m-Y');
        } catch (\Throwable $th) {
            return (string) $tanggal;
        }
    }

    private function getPenciptaArsip(): string
    {
        return (string) config('app.pencipta_arsip', 'Stasiun Meteorologi Kelas IV H. Asan Kotawaringin Timur');
    }

    private function resolveJenisExport(Request $request): string
    {
        $statusId = $request->input('status_id');
        $jenisExport = $request->input('jenis_export', 'daftar_isi_berkas');

        if ($statusId === null || $statusId === '' || in_array((int) $statusId, [1, 3], true)) {
            return $jenisExport === 'daftar_berkas' ? 'daftar_berkas' : 'daftar_isi_berkas';
        }

        return 'daftar_isi_berkas';
    }

    public function exportBerkasExcel(
        Request $request,
        ExportActivityLogger $exportLogger
    ) {
        $kodeKlasifikasi = $request->input('kode_klasifikasi');
        $keteranganAkhir = $request->input('keterangan_akhir');
        $isi = $request->input('isi');
        $tanggalDari = $request->input('tanggal_dari');
        $tanggalSampai = $request->input('tanggal_sampai');

        $request->validate([
            'isi' => ['nullable', 'in:kosong'],
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date', 'after_or_equal:tanggal_dari'],
        ]);
        $jenisExport = $this->resolveJenisExport($request);
        $statusId = (int) $request->input('status_id');
        $hasStatusFilter = $request->filled('status_id');
        $isStatusInaktif = $hasStatusFilter && $statusId === 3;
        $isStandardArchiveFormat = ! $hasStatusFilter || in_array($statusId, [1, 3], true);
        $kodeKlasifikasiLabel = 'Semua';
        $statusLabel = 'Semua';
        $keteranganAkhirLabel = 'Semua';

        if (! empty($kodeKlasifikasi)) {
            $kodeKlasifikasiLabel = $kodeKlasifikasi;
        }

        if (in_array($keteranganAkhir, ['Permanen', 'Musnah'], true)) {
            $keteranganAkhirLabel = $keteranganAkhir;
        }

        if ($request->filled('status_id')) {
            $statusModel = Status::find($request->status_id);
            $statusLabel = $statusModel ? $statusModel->nama_status : (string) $request->status_id;
        }

        $query = Filelist::with([
            'classification:id,kode_klasifikasi',
            'status:id,nama_status',
            'incomings' => function ($incomingQuery) use ($tanggalDari, $tanggalSampai) {
                $incomingQuery->with('access:id,sifat_akses')
                    ->orderBy('tanggal_surat', 'asc')
                    ->orderBy('nomor_surat', 'asc');

                if (! empty($tanggalDari)) {
                    $incomingQuery->whereDate('tanggal_surat', '>=', $tanggalDari);
                }

                if (! empty($tanggalSampai)) {
                    $incomingQuery->whereDate('tanggal_surat', '<=', $tanggalSampai);
                }
            },
            'outcomings' => function ($outcomingQuery) use ($tanggalDari, $tanggalSampai) {
                $outcomingQuery->with('access:id,sifat_akses')
                    ->orderBy('tanggal_surat', 'asc')
                    ->orderBy('nomor_surat', 'asc');

                if (! empty($tanggalDari)) {
                    $outcomingQuery->whereDate('tanggal_surat', '>=', $tanggalDari);
                }

                if (! empty($tanggalSampai)) {
                    $outcomingQuery->whereDate('tanggal_surat', '<=', $tanggalSampai);
                }
            },
        ])
            ->join('classifications', 'filelists.classification_id', '=', 'classifications.id')
            ->orderBy('classifications.kode_klasifikasi', 'asc')
            ->orderBy('filelists.nama_berkas', 'asc')
            ->select('filelists.*');

        if ($request->filled('status_id')) {
            $query->where('filelists.status_id', $request->status_id);
        }

        if (! empty($kodeKlasifikasi)) {
            $query->where('classifications.kode_klasifikasi', $kodeKlasifikasi);
        }

        if (in_array($keteranganAkhir, ['Permanen', 'Musnah'], true)) {
            $query->where('filelists.keterangan_akhir', $keteranganAkhir);
        }

        if ($isi === 'kosong') {
            $query->withoutContents();
        }

        $isFilterItem = $request->filled('tanggal_dari') || $request->filled('tanggal_sampai');

        if ($isFilterItem) {
            $query->where(function ($filterQuery) use ($tanggalDari, $tanggalSampai) {
                $filterQuery->orWhereExists(function ($subQuery) use ($tanggalDari, $tanggalSampai) {
                    $subQuery->selectRaw('1')
                        ->from('incomings')
                        ->whereColumn('incomings.filelist_id', 'filelists.id');

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
                        ->whereColumn('outcomings.filelist_id', 'filelists.id');

                    if (! empty($tanggalDari)) {
                        $subQuery->whereDate('outcomings.tanggal_surat', '>=', $tanggalDari);
                    }

                    if (! empty($tanggalSampai)) {
                        $subQuery->whereDate('outcomings.tanggal_surat', '<=', $tanggalSampai);
                    }
                });
            });
        }

        $berkasList = $query->get();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new SafeSpreadsheetValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = 7;

        if ($jenisExport === 'daftar_isi_berkas') {
            $fileName = 'daftar-isi-berkas-'.now()->format('Ymd-His').'.xlsx';
            $sheet->setTitle('Daftar Isi Berkas');
            $sheet->setCellValue('A1', 'DAFTAR ISI BERKAS');

            if ($isStandardArchiveFormat) {
                $headers = [
                    'A' => 'Nomor Urut',
                    'B' => 'Kode Klasifikasi',
                    'C' => $isStatusInaktif ? 'Unit Kearsipan' : 'Unit Pengolah',
                    'D' => 'Nama Berkas',
                    'E' => 'Jumlah Isi Berkas',
                    'F' => $isStatusInaktif ? 'No Box' : 'Lokasi',
                    'G' => 'JRA Aktif',
                    'H' => 'JRA Inaktif',
                    'I' => 'Nasib Akhir',
                    'J' => 'Nomor Isi Berkas',
                    'K' => 'Pencipta Arsip',
                    'L' => 'Tujuan Surat',
                    'M' => 'Nomor Surat',
                    'N' => 'Perihal',
                    'O' => 'Uraian Informasi',
                    'P' => 'Tanggal Surat',
                    'Q' => 'Tingkat Perkembangan',
                    'R' => 'Keterangan',
                    'S' => 'SKKAD',
                ];
            } else {
                $headers = [
                    'A' => 'No Berkas',
                    'B' => 'Kode Klasifikasi',
                    'C' => 'Nama Berkas',
                    'D' => 'Status Berkas',
                    'E' => 'Retensi Aktif',
                    'F' => 'Retensi Inaktif',
                    'G' => 'Keterangan Akhir',
                    'H' => 'No Item',
                    'I' => 'Jenis Naskah',
                    'J' => 'Nomor Naskah',
                    'K' => 'Tanggal Item',
                    'L' => 'Uraian Informasi Arsip',
                    'M' => 'Identitas Naskah',
                    'N' => 'SKKAD',
                ];
            }
        } else {
            $fileName = 'daftar-berkas-'.now()->format('Ymd-His').'.xlsx';
            $sheet->setTitle('Daftar Berkas');
            $sheet->setCellValue('A1', 'DAFTAR BERKAS');

            if ($isStandardArchiveFormat) {
                $headers = [
                    'A' => 'Nomor Urut',
                    'B' => 'Kode Klasifikasi',
                    'C' => $isStatusInaktif ? 'Unit Kearsipan' : 'Unit Pengolah',
                    'D' => 'Nama Berkas',
                    'E' => 'Jumlah Isi Berkas',
                    'F' => $isStatusInaktif ? 'No Box' : 'Lokasi',
                    'G' => 'JRA Aktif',
                    'H' => 'JRA Inaktif',
                    'I' => 'Nasib Akhir',
                ];
            } else {
                $headers = [
                    'A' => 'No',
                    'B' => 'Kode Klasifikasi',
                    'C' => 'Nama Berkas',
                    'D' => 'Status Berkas',
                    'E' => 'Jumlah Isi Berkas',
                    'F' => 'Retensi Aktif',
                    'G' => 'Retensi Inaktif',
                    'H' => 'Keterangan Akhir',
                ];
            }
        }

        $sheet->mergeCells('A1:'.array_key_last($headers).'1');

        $sheet->setCellValue('A2', 'Filter Status');
        $sheet->setCellValue('B2', $statusLabel);
        $sheet->setCellValue('A3', 'Filter Tanggal');
        $sheet->setCellValue('B3', trim($this->formatTanggalIndonesia($tanggalDari ?: '-').' s/d '.$this->formatTanggalIndonesia($tanggalSampai ?: '-')));
        $sheet->setCellValue('A4', 'Filter Kode Klasifikasi');
        $sheet->setCellValue('B4', $kodeKlasifikasiLabel);
        $sheet->setCellValue('A5', 'Diekspor Pada');
        $sheet->setCellValue('B5', now()->format('d-m-Y H:i:s'));
        $sheet->setCellValue('A6', 'Filter Keterangan Akhir');
        $sheet->setCellValue('B6', $keteranganAkhirLabel);

        foreach ($headers as $column => $title) {
            $sheet->setCellValue($column.$headerRow, $title);
        }

        $row = $headerRow + 1;
        $nomor = 1;
        $penciptaArsip = $this->getPenciptaArsip();

        foreach ($berkasList as $berkas) {
            if ($jenisExport === 'daftar_isi_berkas') {
                $items = collect();

                foreach ($berkas->incomings as $incoming) {
                    $penciptaSurat = (string) ($incoming->pengirim ?? '-');
                    $tujuanSurat = $penciptaArsip;

                    $items->push([
                        'jenis_naskah' => 'Masuk',
                        'nomor_naskah' => $incoming->nomor_surat,
                        'tanggal_item' => $incoming->tanggal_surat,
                        'uraian' => $incoming->perihal,
                        'identitas_naskah' => $incoming->pengirim,
                        'pencipta_arsip' => $penciptaSurat,
                        'tujuan_surat' => $tujuanSurat,
                        'perihal' => $incoming->perihal,
                        'lokasi' => $incoming->is_srikandi ? 'SRIKANDI' : 'Manual',
                        'skkad' => optional($incoming->access)->sifat_akses,
                    ]);
                }

                foreach ($berkas->outcomings as $outcoming) {
                    $penciptaSurat = $penciptaArsip;
                    $tujuanSurat = (string) ($outcoming->tujuan ?? '-');

                    $items->push([
                        'jenis_naskah' => 'Keluar',
                        'nomor_naskah' => $outcoming->nomor_surat,
                        'tanggal_item' => $outcoming->tanggal_surat,
                        'uraian' => $outcoming->perihal,
                        'identitas_naskah' => $outcoming->tujuan,
                        'pencipta_arsip' => $penciptaSurat,
                        'tujuan_surat' => $tujuanSurat,
                        'perihal' => $outcoming->perihal,
                        'lokasi' => $outcoming->is_srikandi ? 'SRIKANDI' : 'Manual',
                        'skkad' => optional($outcoming->access)->sifat_akses,
                    ]);
                }

                $items = $items->sortBy([
                    ['tanggal_item', 'asc'],
                    ['nomor_naskah', 'asc'],
                ])->values();
                $jumlahIsiBerkas = $items->count();

                if ($items->isEmpty()) {
                    $items = collect([[
                        'jenis_naskah' => '-',
                        'nomor_naskah' => '-',
                        'tanggal_item' => '-',
                        'uraian' => '-',
                        'identitas_naskah' => '-',
                        'pencipta_arsip' => '-',
                        'tujuan_surat' => '-',
                        'perihal' => '-',
                        'lokasi' => '-',
                        'skkad' => '-',
                    ]]);
                }

                $kodeKlasifikasiBerkas = (string) (optional($berkas->classification)->kode_klasifikasi ?? '-');
                $startRow = $row;
                $itemNo = 1;

                if ($isStandardArchiveFormat) {
                    foreach ($items as $item) {
                        $tanggalSurat = $this->formatTanggalIndonesia($item['tanggal_item'] ?? '-');
                        $penciptaSurat = (string) ($item['pencipta_arsip'] ?? '-');
                        $tujuanSurat = (string) ($item['tujuan_surat'] ?? '-');
                        $nomorSurat = (string) ($item['nomor_naskah'] ?? '-');
                        $perihal = (string) ($item['perihal'] ?? '-');
                        $uraianInformasi = "Surat dari {$penciptaSurat} kepada {$tujuanSurat} nomor {$nomorSurat} tanggal {$tanggalSurat} tentang {$perihal}";

                        $sheet->setCellValue('J'.$row, $itemNo++);
                        $sheet->setCellValue('K'.$row, $penciptaSurat);
                        $sheet->setCellValue('L'.$row, $tujuanSurat);
                        $sheet->setCellValue('M'.$row, $nomorSurat);
                        $sheet->setCellValue('N'.$row, $perihal);
                        $sheet->setCellValue('O'.$row, $uraianInformasi);
                        $sheet->setCellValue('P'.$row, $tanggalSurat);
                        $sheet->setCellValue('Q'.$row, 'Asli');
                        $sheet->setCellValue('R'.$row, '');
                        $sheet->setCellValue('S'.$row, (string) ($item['skkad'] ?? '-'));
                        $row++;
                    }

                    $endRow = $row - 1;
                    $sheet->setCellValue('A'.$startRow, $nomor++);
                    $sheet->setCellValue('B'.$startRow, $kodeKlasifikasiBerkas);
                    $sheet->setCellValue('C'.$startRow, $penciptaArsip);
                    $sheet->setCellValue('D'.$startRow, (string) ($berkas->nama_berkas ?? '-'));
                    $sheet->setCellValue('E'.$startRow, $jumlahIsiBerkas);
                    $sheet->setCellValue('F'.$startRow, $isStatusInaktif ? '1' : 'Filling Cabinet');
                    $sheet->setCellValue('G'.$startRow, (string) ($berkas->retensi_aktif ?? '-'));
                    $sheet->setCellValue('H'.$startRow, (string) ($berkas->retensi_inaktif ?? '-'));
                    $sheet->setCellValue('I'.$startRow, (string) ($berkas->keterangan_akhir ?? '-'));

                    if ($endRow > $startRow) {
                        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $column) {
                            $sheet->mergeCells($column.$startRow.':'.$column.$endRow);
                        }
                    }
                } else {
                    foreach ($items as $item) {
                        $sheet->setCellValue('H'.$row, $itemNo++);
                        $sheet->setCellValue('I'.$row, (string) ($item['jenis_naskah'] ?? '-'));
                        $sheet->setCellValue('J'.$row, (string) ($item['nomor_naskah'] ?? '-'));
                        $sheet->setCellValue('K'.$row, $this->formatTanggalIndonesia($item['tanggal_item'] ?? '-'));
                        $sheet->setCellValue('L'.$row, (string) ($item['uraian'] ?? '-'));
                        $sheet->setCellValue('M'.$row, (string) ($item['identitas_naskah'] ?? '-'));
                        $sheet->setCellValue('N'.$row, (string) ($item['skkad'] ?? '-'));
                        $row++;
                    }

                    $endRow = $row - 1;
                    $sheet->setCellValue('A'.$startRow, $nomor++);
                    $sheet->setCellValue('B'.$startRow, $kodeKlasifikasiBerkas);
                    $sheet->setCellValue('C'.$startRow, (string) ($berkas->nama_berkas ?? '-'));
                    $sheet->setCellValue('D'.$startRow, (string) (optional($berkas->status)->nama_status ?? '-'));
                    $sheet->setCellValue('E'.$startRow, (string) ($berkas->retensi_aktif ?? '-'));
                    $sheet->setCellValue('F'.$startRow, (string) ($berkas->retensi_inaktif ?? '-'));
                    $sheet->setCellValue('G'.$startRow, (string) ($berkas->keterangan_akhir ?? '-'));

                    if ($endRow > $startRow) {
                        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $column) {
                            $sheet->mergeCells($column.$startRow.':'.$column.$endRow);
                        }
                    }
                }
            } else {
                $totalIsi = $berkas->incomings->count() + $berkas->outcomings->count();
                $kodeKlasifikasiBerkas = (string) (optional($berkas->classification)->kode_klasifikasi ?? '-');

                if ($isStandardArchiveFormat) {
                    $sheet->setCellValue('A'.$row, $nomor++);
                    $sheet->setCellValue('B'.$row, $kodeKlasifikasiBerkas);
                    $sheet->setCellValue('C'.$row, $penciptaArsip);
                    $sheet->setCellValue('D'.$row, (string) ($berkas->nama_berkas ?? '-'));
                    $sheet->setCellValue('E'.$row, $totalIsi);
                    $sheet->setCellValue('F'.$row, $isStatusInaktif ? '1' : 'Filling Cabinet');
                    $sheet->setCellValue('G'.$row, (string) ($berkas->retensi_aktif ?? '-'));
                    $sheet->setCellValue('H'.$row, (string) ($berkas->retensi_inaktif ?? '-'));
                    $sheet->setCellValue('I'.$row, (string) ($berkas->keterangan_akhir ?? '-'));
                } else {
                    $sheet->setCellValue('A'.$row, $nomor++);
                    $sheet->setCellValue('B'.$row, $kodeKlasifikasiBerkas);
                    $sheet->setCellValue('C'.$row, (string) ($berkas->nama_berkas ?? '-'));
                    $sheet->setCellValue('D'.$row, (string) (optional($berkas->status)->nama_status ?? '-'));
                    $sheet->setCellValue('E'.$row, $totalIsi);
                    $sheet->setCellValue('F'.$row, (string) ($berkas->retensi_aktif ?? '-'));
                    $sheet->setCellValue('G'.$row, (string) ($berkas->retensi_inaktif ?? '-'));
                    $sheet->setCellValue('H'.$row, (string) ($berkas->keterangan_akhir ?? '-'));
                }
                $row++;
            }
        }

        $lastRow = $row - 1;

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $lastColumn = array_key_last($headers);

        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->applyFromArray([
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

        if ($lastRow >= $headerRow) {
            $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$lastRow)->applyFromArray([
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
        }

        if ($lastRow >= ($headerRow + 1)) {
            $sheet->getStyle('A'.($headerRow + 1).':A'.$lastRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($jenisExport === 'daftar_isi_berkas') {
                $centerCols = $isStandardArchiveFormat
                    ? ['A', 'E', 'G', 'H', 'J', 'P']
                    : ['A', 'E', 'F', 'H', 'I', 'K'];

                foreach ($centerCols as $centerColumn) {
                    $sheet->getStyle($centerColumn.($headerRow + 1).':'.$centerColumn.$lastRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            } else {
                $centerCols = $isStandardArchiveFormat
                    ? ['A', 'B', 'F']
                    : ['A', 'B', 'D', 'F', 'G'];

                foreach ($centerCols as $centerColumn) {
                    $sheet->getStyle($centerColumn.($headerRow + 1).':'.$centerColumn.$lastRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        }

        foreach (array_keys($headers) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setAutoFilter('A'.$headerRow.':'.$lastColumn.$headerRow);

        $exportLogger->logPrepared(
            $jenisExport,
            max(0, $lastRow - $headerRow),
            $fileName,
            [
                'status_id' => $hasStatusFilter ? $statusId : null,
                'kode_klasifikasi' => $kodeKlasifikasi,
                'keterangan_akhir' => $keteranganAkhir,
                'isi' => $isi,
                'tanggal_dari' => $tanggalDari,
                'tanggal_sampai' => $tanggalSampai,
            ],
            [
                'cakupan' => 'global',
                'jumlah_berkas' => $berkasList->count(),
            ]
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
