<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Services\ExportActivityLogger;
use App\Services\SafeSpreadsheetValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KlasifikasiExportController extends Controller
{
    public function exportExcel(ExportActivityLogger $exportLogger)
    {
        $classifications = Classification::query()
            ->orderBy('kode_klasifikasi', 'asc')
            ->get(['kode_klasifikasi', 'keterangan']);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new SafeSpreadsheetValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daftar Klasifikasi');
        $sheet->setCellValue('A1', 'DAFTAR KLASIFIKASI ARSIP');
        $sheet->setCellValue('A2', (string) config('app.pencipta_arsip'));
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');

        $headerRow = 4;
        $headers = [
            'A' => 'Nomor',
            'B' => 'Kode Klasifikasi',
            'C' => 'Keterangan',
        ];

        foreach ($headers as $column => $title) {
            $sheet->setCellValue($column.$headerRow, $title);
        }

        $row = $headerRow + 1;
        foreach ($classifications as $index => $classification) {
            $sheet->setCellValue('A'.$row, $index + 1);
            $sheet->setCellValue('B'.$row, (string) $classification->kode_klasifikasi);
            $sheet->setCellValue('C'.$row, (string) $classification->keterangan);
            $row++;
        }

        $lastRow = max($headerRow, $row - 1);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1:C2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A'.$headerRow.':C'.$headerRow)->applyFromArray([
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
        $sheet->getStyle('A'.$headerRow.':C'.$lastRow)->applyFromArray([
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
        if ($lastRow > $headerRow) {
            $sheet->getStyle('A'.($headerRow + 1).':A'.$lastRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(70);
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setAutoFilter('A'.$headerRow.':C'.$headerRow);

        $fileName = 'daftar-klasifikasi-'.now()->format('Ymd-His').'.xlsx';

        $exportLogger->logPrepared(
            'daftar_klasifikasi',
            $classifications->count(),
            $fileName,
            [],
            ['cakupan' => 'global_aktif']
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
