<?php

namespace Tests\Unit;

use App\Jobs\ProcessAlihMediaWatermarkJob;
use App\Services\SafeSpreadsheetValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class QueueAndSpreadsheetSafetyTest extends TestCase
{
    public function test_database_queue_retry_window_exceeds_watermark_job_timeout()
    {
        $job = new ProcessAlihMediaWatermarkJob(1);

        $this->assertGreaterThan(
            $job->timeout,
            (int) config('queue.connections.database.retry_after')
        );
    }

    public function test_formula_like_spreadsheet_values_are_written_as_text()
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new SafeSpreadsheetValueBinder);
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', '=HYPERLINK("https://example.test")');
        $sheet->setCellValue('A2', "\t+1+1");
        $sheet->setCellValue('A3', 42);

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('A3')->getDataType());
    }
}
