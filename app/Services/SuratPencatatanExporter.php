<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SuratPencatatanExporter
{
    public function __construct(
        private SuratFilterQuery $suratFilter,
        private ExportActivityLogger $exportLogger
    ) {}

    /**
     * @param  array{sumber_surat: string, tanggal_dari: ?string, tanggal_sampai: ?string}  $filters
     */
    public function incoming(int $year, array $filters): StreamedResponse
    {
        $letters = $this->suratFilter
            ->incoming($year, $filters)
            ->orderBy('tanggal_diterima')
            ->orderBy('nomor_surat')
            ->get();

        $sourceLabels = [
            'semua' => 'Semua',
            'srikandi' => 'Dari SRIKANDI',
            'non_srikandi' => 'Bukan dari SRIKANDI',
        ];

        $spreadsheet = $this->newSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pencatatan Naskah Masuk');
        $sheet->setCellValue('A1', 'Agenda Elektronik Penerimaan dan Pencatatan Naskah Dinas Masuk');
        $sheet->setCellValue('A2', config('app.pencipta_arsip'));
        $sheet->setCellValue('A3', 'Tahun '.$year);
        $sheet->setCellValue('A4', 'Sumber Surat: '.$sourceLabels[$filters['sumber_surat']]);
        $sheet->setCellValue('A5', 'Periode Tanggal Diterima: '.$this->formatPeriod($filters));

        foreach (range(1, 5) as $row) {
            $sheet->mergeCells("A{$row}:H{$row}");
        }

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
        $this->writeHeaders($spreadsheet, $headers, $headerRow);

        $row = $headerRow + 1;
        foreach ($letters as $letter) {
            $sheet->setCellValue(
                'A'.$row,
                $letter->is_srikandi ? 'SRIKANDI' : (string) $letter->nomor_agenda
            );
            $sheet->setCellValue(
                'B'.$row,
                $letter->is_srikandi ? 'Dari SRIKANDI' : 'Bukan dari SRIKANDI'
            );
            $sheet->setCellValue('C'.$row, (string) ($letter->pengirim ?? '-'));
            $sheet->setCellValue('D'.$row, '-');
            $sheet->setCellValue('E'.$row, $this->formatDate($letter->tanggal_surat));
            $sheet->setCellValue('F'.$row, $this->formatDate($letter->tanggal_diterima));
            $sheet->setCellValue('G'.$row, (string) ($letter->nomor_surat ?? '-'));
            $sheet->setCellValue('H'.$row, (string) ($letter->perihal ?? '-'));
            $row++;
        }

        $this->styleSheet($spreadsheet, $headers, $headerRow, max($headerRow, $row - 1));

        $fileName = 'pencatatan-naskah-masuk-'
            .$filters['sumber_surat']
            .'-'
            .now()->format('Ymd-His')
            .'.xlsx';

        $this->exportLogger->logPrepared(
            'pencatatan_surat_masuk',
            $letters->count(),
            $fileName,
            $filters,
            ['cakupan' => 'tahun_aktif']
        );

        return $this->download($spreadsheet, $fileName);
    }

    /**
     * @param  array{jalur_pengiriman: string, tanggal_dari: ?string, tanggal_sampai: ?string}  $filters
     */
    public function outgoing(int $year, array $filters): StreamedResponse
    {
        $letters = $this->suratFilter
            ->outgoing($year, $filters)
            ->with('access')
            ->orderBy('tanggal_surat')
            ->orderBy('nomor_surat')
            ->get();

        $routeLabels = [
            'semua' => 'Semua',
            'srikandi' => 'SRIKANDI',
            'non_srikandi' => 'Tanpa SRIKANDI',
        ];

        $spreadsheet = $this->newSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pencatatan Naskah Keluar');
        $sheet->setCellValue('A1', 'Agenda Elektronik Pencatatan Naskah Dinas Keluar');
        $sheet->setCellValue('A2', config('app.pencipta_arsip'));
        $sheet->setCellValue('A3', 'Tahun '.$year);
        $sheet->setCellValue('A4', 'Jalur Pengiriman: '.$routeLabels[$filters['jalur_pengiriman']]);
        $sheet->setCellValue('A5', 'Periode Tanggal Surat: '.$this->formatPeriod($filters));

        foreach (range(1, 5) as $row) {
            $sheet->mergeCells("A{$row}:F{$row}");
        }

        $headers = [
            'A' => 'Tanggal Surat',
            'B' => 'Jalur Pengiriman',
            'C' => 'Nomor Surat',
            'D' => 'Tujuan',
            'E' => 'Perihal',
            'F' => 'SKKAAD',
        ];
        $headerRow = 7;
        $this->writeHeaders($spreadsheet, $headers, $headerRow);

        $row = $headerRow + 1;
        foreach ($letters as $letter) {
            $sheet->setCellValue('A'.$row, $this->formatDate($letter->tanggal_surat));
            $sheet->setCellValue('B'.$row, $letter->is_srikandi ? 'SRIKANDI' : 'Tanpa SRIKANDI');
            $sheet->setCellValue('C'.$row, (string) ($letter->nomor_surat ?? '-'));
            $sheet->setCellValue('D'.$row, (string) ($letter->tujuan ?? '-'));
            $sheet->setCellValue('E'.$row, (string) ($letter->perihal ?? '-'));
            $sheet->setCellValue('F'.$row, (string) ($letter->access?->sifat_akses ?? '-'));
            $row++;
        }

        $this->styleSheet($spreadsheet, $headers, $headerRow, max($headerRow, $row - 1));

        $fileName = 'pencatatan-naskah-keluar-'
            .$filters['jalur_pengiriman']
            .'-'
            .now()->format('Ymd-His')
            .'.xlsx';

        $this->exportLogger->logPrepared(
            'pencatatan_surat_keluar',
            $letters->count(),
            $fileName,
            $filters,
            ['cakupan' => 'tahun_aktif']
        );

        return $this->download($spreadsheet, $fileName);
    }

    private function newSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new SafeSpreadsheetValueBinder);

        return $spreadsheet;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function writeHeaders(Spreadsheet $spreadsheet, array $headers, int $row): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $column => $title) {
            $sheet->setCellValue($column.$row, $title);
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function styleSheet(
        Spreadsheet $spreadsheet,
        array $headers,
        int $headerRow,
        int $lastRow
    ): void {
        $sheet = $spreadsheet->getActiveSheet();
        $lastColumn = array_key_last($headers);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(36);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(26);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle('A1:A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
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
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$lastRow}")->applyFromArray([
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
        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$headerRow}");
    }

    /**
     * @param  array{tanggal_dari: ?string, tanggal_sampai: ?string}  $filters
     */
    private function formatPeriod(array $filters): string
    {
        $startDate = $filters['tanggal_dari'];
        $endDate = $filters['tanggal_sampai'];

        if ($startDate && $endDate) {
            return $this->formatDate($startDate).' s.d. '.$this->formatDate($endDate);
        }

        if ($startDate) {
            return 'Mulai '.$this->formatDate($startDate);
        }

        if ($endDate) {
            return 'Sampai '.$this->formatDate($endDate);
        }

        return 'Semua tanggal';
    }

    private function formatDate(mixed $date): string
    {
        if (empty($date) || $date === '-') {
            return '-';
        }

        try {
            return Carbon::parse($date)->format('d-m-Y');
        } catch (Throwable) {
            return (string) $date;
        }
    }

    private function download(Spreadsheet $spreadsheet, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
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
