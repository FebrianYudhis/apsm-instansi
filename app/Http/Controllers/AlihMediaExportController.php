<?php

namespace App\Http\Controllers;

use App\Models\Filelist;
use App\Services\ExportActivityLogger;
use App\Services\SafeSpreadsheetValueBinder;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RealRashid\SweetAlert\Facades\Alert;

class AlihMediaExportController extends Controller
{
    public function exportDaftarArsipExcel(ExportActivityLogger $exportLogger)
    {
        $berkasList = Filelist::with([
            'classification:id,kode_klasifikasi',
            'status:id,nama_status',
            'incomings' => function ($query) {
                $query->orderBy('tanggal_surat', 'asc')->orderBy('nomor_surat', 'asc');
            },
            'outcomings' => function ($query) {
                $query->orderBy('tanggal_surat', 'asc')->orderBy('nomor_surat', 'asc');
            },
        ])
            ->join('classifications', 'filelists.classification_id', '=', 'classifications.id')
            ->where('filelists.keterangan_akhir', 'Permanen')
            ->whereIn('filelists.alih_media_status_id', [
                Filelist::ALIH_MEDIA_PROCESSING,
                Filelist::ALIH_MEDIA_DONE,
                Filelist::ALIH_MEDIA_FAILED,
            ])
            ->orderBy('classifications.kode_klasifikasi', 'asc')
            ->orderBy('filelists.nama_berkas', 'asc')
            ->select('filelists.*')
            ->get();

        if ($berkasList->isEmpty()) {
            Alert::error('Gagal', 'Tidak ada data arsip alih media yang dapat diexport');

            return redirect()->back();
        }

        $statusLabels = $berkasList->map(function ($berkas) {
            return optional($berkas->status)->nama_status;
        })->unique()->values();

        if ($statusLabels->count() !== 1 || ! in_array($statusLabels->first(), ['Aktif', 'Inaktif'], true)) {
            Alert::error('Gagal', 'Export hanya dapat dilakukan untuk data berstatus Aktif atau Inaktif');

            return redirect()->back();
        }

        $unitLabel = $statusLabels->first() === 'Inaktif' ? 'Unit Kearsipan :' : 'Unit Pengolah :';

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new SafeSpreadsheetValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daftar Arsip Alih Media');
        $exportedAt = Carbon::now('Asia/Jakarta');

        $headers = [
            'B' => 'Nomor Urut',
            'C' => 'Kode Klasifikasi',
            'D' => 'Jenis Arsip',
            'E' => 'Semula',
            'F' => 'Menjadi',
            'G' => 'Jumlah',
            'H' => 'Alat',
            'I' => 'Waktu',
            'J' => 'Pelaksana',
            'K' => 'Keterangan',
        ];

        $sheet->mergeCells('B2:K2');
        $sheet->mergeCells('B3:K3');
        $sheet->mergeCells('B4:K4');
        $sheet->setCellValue('B2', 'DAFTAR ARSIP ALIH MEDIA');
        $sheet->setCellValue('B3', 'Organisasi :');
        $sheet->setCellValue('B4', $unitLabel);

        $headerRow = 6;
        foreach (['B', 'C', 'D', 'G', 'H', 'I', 'J', 'K'] as $column) {
            $sheet->mergeCells($column.$headerRow.':'.$column.($headerRow + 1));
        }
        $sheet->mergeCells('E'.$headerRow.':F'.$headerRow);

        $sheet->setCellValue('B'.$headerRow, 'Nomor Urut');
        $sheet->setCellValue('C'.$headerRow, 'Kode Klasifikasi');
        $sheet->setCellValue('D'.$headerRow, 'Jenis Arsip');
        $sheet->setCellValue('E'.$headerRow, 'Media Arsip');
        $sheet->setCellValue('E'.($headerRow + 1), 'Semula');
        $sheet->setCellValue('F'.($headerRow + 1), 'Menjadi');
        $sheet->setCellValue('G'.$headerRow, 'Jumlah');
        $sheet->setCellValue('H'.$headerRow, 'Alat');
        $sheet->setCellValue('I'.$headerRow, 'Waktu');
        $sheet->setCellValue('J'.$headerRow, 'Pelaksana');
        $sheet->setCellValue('K'.$headerRow, 'Keterangan');

        $row = $headerRow + 2;
        $nomor = 1;

        foreach ($berkasList as $berkas) {
            $items = collect();

            foreach ($berkas->incomings as $incoming) {
                $items->push([
                    'tanggal_surat' => $incoming->tanggal_surat,
                    'nomor_surat' => $incoming->nomor_surat,
                    'perihal' => $incoming->perihal,
                ]);
            }

            foreach ($berkas->outcomings as $outcoming) {
                $items->push([
                    'tanggal_surat' => $outcoming->tanggal_surat,
                    'nomor_surat' => $outcoming->nomor_surat,
                    'perihal' => $outcoming->perihal,
                ]);
            }

            $items = $items->sortBy([
                ['tanggal_surat', 'asc'],
                ['nomor_surat', 'asc'],
            ])->values();

            foreach ($items as $item) {
                $sheet->setCellValue('B'.$row, $nomor++);
                $sheet->setCellValue('C'.$row, (string) (optional($berkas->classification)->kode_klasifikasi ?? '-'));
                $sheet->setCellValue('D'.$row, (string) ($berkas->nama_berkas ?? '-'));
                $sheet->setCellValue('E'.$row, 'Kertas');
                $sheet->setCellValue('F'.$row, 'Elektronik Format PDF');
                $sheet->setCellValue('G'.$row, '1 Berkas');
                $sheet->setCellValue('H'.$row, 'Scanner Epson Workforce DS-410');
                $sheet->setCellValue('I'.$row, '');
                $sheet->setCellValue('J'.$row, '');
                $sheet->setCellValue('K'.$row, $this->formatKeterangan($item));
                $row++;
            }
        }

        $lastRow = max($headerRow + 1, $row - 1);

        $sheet->getRowDimension(2)->setRowHeight(61.5);
        $sheet->getRowDimension(3)->setRowHeight(31.5);
        $sheet->getRowDimension(4)->setRowHeight(31.5);
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(48);
        $sheet->getStyle('B3:B4')->getFont()->setBold(true)->setSize(24);
        $sheet->getStyle('B2:B4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('B'.$headerRow.':K'.($headerRow + 1))->applyFromArray([
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

        $sheet->getStyle('B'.$headerRow.':K'.$lastRow)->applyFromArray([
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

        if ($lastRow > ($headerRow + 1)) {
            foreach (['B', 'C', 'E', 'F', 'G'] as $column) {
                $sheet->getStyle($column.($headerRow + 2).':'.$column.$lastRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(2.85);
        $sheet->getColumnDimension('B')->setWidth(16.4);
        $sheet->getColumnDimension('C')->setWidth(23.4);
        $sheet->getColumnDimension('D')->setWidth(16.3);
        $sheet->getColumnDimension('E')->setWidth(12.85);
        $sheet->getColumnDimension('F')->setWidth(28.15);
        $sheet->getColumnDimension('G')->setWidth(12.85);
        $sheet->getColumnDimension('H')->setWidth(36.4);
        $sheet->getColumnDimension('I')->setWidth(9.3);
        $sheet->getColumnDimension('J')->setWidth(14);
        $sheet->getColumnDimension('K')->setWidth(139.15);

        $sheet->freezePane('B'.($headerRow + 2));
        $sheet->setAutoFilter('B'.($headerRow + 1).':K'.($headerRow + 1));

        $fileName = 'daftar-arsip-alih-media-'.$exportedAt->format('Ymd-His').'.xlsx';

        $exportLogger->logPrepared(
            'daftar_arsip_alih_media_diproses',
            max(0, $nomor - 1),
            $fileName,
            [
                'keterangan_akhir' => 'Permanen',
                'alih_media_status' => 'diproses',
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

    public function exportDaftarArsipSelesaiExcel(
        ExportActivityLogger $exportLogger
    ) {
        $berkasList = Filelist::with([
            'classification:id,kode_klasifikasi',
            'status:id,nama_status',
            'incomings' => function ($query) {
                $query->orderBy('tanggal_surat', 'asc')->orderBy('nomor_surat', 'asc');
            },
            'outcomings' => function ($query) {
                $query->orderBy('tanggal_surat', 'asc')->orderBy('nomor_surat', 'asc');
            },
        ])
            ->join('classifications', 'filelists.classification_id', '=', 'classifications.id')
            ->join('statuses', 'filelists.status_id', '=', 'statuses.id')
            ->where('filelists.keterangan_akhir', 'Permanen')
            ->where('filelists.alih_media_status_id', Filelist::ALIH_MEDIA_CLOSED)
            ->orderBy('statuses.nama_status', 'asc')
            ->orderBy('classifications.kode_klasifikasi', 'asc')
            ->orderBy('filelists.nama_berkas', 'asc')
            ->select('filelists.*')
            ->get();

        if ($berkasList->isEmpty()) {
            Alert::error('Gagal', 'Tidak ada data arsip alih media selesai yang dapat diexport');

            return redirect()->back();
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new SafeSpreadsheetValueBinder);
        $exportedAt = Carbon::now('Asia/Jakarta');
        $statusGroups = $berkasList->groupBy(function ($berkas) {
            return optional($berkas->status)->nama_status ?: 'Tanpa Status';
        });

        $sheetIndex = 0;
        foreach ($statusGroups as $statusLabel => $items) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($this->sanitizeSheetTitle($statusLabel));
            $this->fillDaftarArsipSelesaiSheet($sheet, $items);
            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);
        $fileName = 'daftar-arsip-alih-media-selesai-'.$exportedAt->format('Ymd-His').'.xlsx';

        $jumlahBaris = $berkasList->sum(function ($berkas) {
            return $berkas->incomings->count() + $berkas->outcomings->count();
        });

        $exportLogger->logPrepared(
            'daftar_arsip_alih_media_selesai',
            $jumlahBaris,
            $fileName,
            [
                'keterangan_akhir' => 'Permanen',
                'alih_media_status' => 'selesai',
            ],
            [
                'cakupan' => 'global',
                'jumlah_berkas' => $berkasList->count(),
                'jumlah_sheet' => $statusGroups->count(),
            ]
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function fillDaftarArsipSelesaiSheet($sheet, $berkasList): void
    {
        $headerRow = 5;

        $sheet->mergeCells('B2:I2');
        $sheet->mergeCells('B3:I3');
        $sheet->setCellValue('B2', 'DAFTAR ARSIP ALIH MEDIA');
        $sheet->setCellValue('B3', 'Unit Kerja : '.config('app.pencipta_arsip'));

        foreach (['B', 'C', 'D', 'G', 'H', 'I'] as $column) {
            $sheet->mergeCells($column.$headerRow.':'.$column.($headerRow + 1));
        }
        $sheet->mergeCells('E'.$headerRow.':F'.$headerRow);

        $sheet->setCellValue('B'.$headerRow, 'Nomor Urut');
        $sheet->setCellValue('C'.$headerRow, 'Kode Klasifikasi');
        $sheet->setCellValue('D'.$headerRow, 'Jenis Arsip');
        $sheet->setCellValue('E'.$headerRow, 'Media Arsip');
        $sheet->setCellValue('E'.($headerRow + 1), 'Semula');
        $sheet->setCellValue('F'.($headerRow + 1), 'Menjadi');
        $sheet->setCellValue('G'.$headerRow, 'Jumlah');
        $sheet->setCellValue('H'.$headerRow, 'Alat');
        $sheet->setCellValue('I'.$headerRow, 'Keterangan');

        $row = $headerRow + 2;
        $nomor = 1;

        foreach ($berkasList as $berkas) {
            $items = collect();

            foreach ($berkas->incomings as $incoming) {
                $items->push([
                    'tanggal_surat' => $incoming->tanggal_surat,
                    'nomor_surat' => $incoming->nomor_surat,
                    'perihal' => $incoming->perihal,
                ]);
            }

            foreach ($berkas->outcomings as $outcoming) {
                $items->push([
                    'tanggal_surat' => $outcoming->tanggal_surat,
                    'nomor_surat' => $outcoming->nomor_surat,
                    'perihal' => $outcoming->perihal,
                ]);
            }

            $items = $items->sortBy([
                ['tanggal_surat', 'asc'],
                ['nomor_surat', 'asc'],
            ])->values();

            foreach ($items as $item) {
                $sheet->setCellValue('B'.$row, $nomor++);
                $sheet->setCellValue('C'.$row, (string) (optional($berkas->classification)->kode_klasifikasi ?? '-'));
                $sheet->setCellValue('D'.$row, (string) ($berkas->nama_berkas ?? '-'));
                $sheet->setCellValue('E'.$row, 'Kertas');
                $sheet->setCellValue('F'.$row, 'Elektronik Format PDF');
                $sheet->setCellValue('G'.$row, '1 Berkas');
                $sheet->setCellValue('H'.$row, 'Scanner Epson Workforce DS-410');
                $sheet->setCellValue('I'.$row, $this->formatKeterangan($item));
                $row++;
            }
        }

        $lastRow = max($headerRow + 1, $row - 1);

        $sheet->getRowDimension(2)->setRowHeight(61.5);
        $sheet->getRowDimension(3)->setRowHeight(31.5);
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(48);
        $sheet->getStyle('B3')->getFont()->setBold(true)->setSize(24);
        $sheet->getStyle('B2:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('B'.$headerRow.':I'.($headerRow + 1))->applyFromArray([
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

        $sheet->getStyle('B'.$headerRow.':I'.$lastRow)->applyFromArray([
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

        if ($lastRow > ($headerRow + 1)) {
            foreach (['B', 'C', 'E', 'F', 'G'] as $column) {
                $sheet->getStyle($column.($headerRow + 2).':'.$column.$lastRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(2.85);
        $sheet->getColumnDimension('B')->setWidth(16.4);
        $sheet->getColumnDimension('C')->setWidth(23.4);
        $sheet->getColumnDimension('D')->setWidth(16.3);
        $sheet->getColumnDimension('E')->setWidth(12.85);
        $sheet->getColumnDimension('F')->setWidth(28.15);
        $sheet->getColumnDimension('G')->setWidth(12.85);
        $sheet->getColumnDimension('H')->setWidth(36.4);
        $sheet->getColumnDimension('I')->setWidth(139.15);

        $sheet->freezePane('B'.($headerRow + 2));
        $sheet->setAutoFilter('B'.($headerRow + 1).':I'.($headerRow + 1));
    }

    private function sanitizeSheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]\\:]/', ' ', $title);
        $title = trim(preg_replace('/\\s+/', ' ', $title));

        return mb_substr($title !== '' ? $title : 'Sheet', 0, 31);
    }

    private function formatKeterangan(array $item): string
    {
        $nomor = (string) ($item['nomor_surat'] ?? '-');
        $tanggal = $this->formatTanggalIndonesia($item['tanggal_surat'] ?? null);
        $perihal = (string) ($item['perihal'] ?? '-');

        return "Surat nomor: {$nomor} tertanggal {$tanggal} perihal {$perihal}";
    }

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
}
