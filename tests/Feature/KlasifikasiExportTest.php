<?php

namespace Tests\Feature;

use App\Models\Classification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class KlasifikasiExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_export_active_classifications_to_excel()
    {
        $user = User::factory()->create();
        Classification::create([
            'kode_klasifikasi' => 'TU.02',
            'keterangan' => '=HYPERLINK("https://example.test")',
        ]);
        Classification::create([
            'kode_klasifikasi' => 'TU.01',
            'keterangan' => 'Tata Usaha',
        ]);
        $deleted = Classification::create([
            'kode_klasifikasi' => 'TU.00',
            'keterangan' => 'Tidak boleh diekspor',
        ]);
        $deleted->delete();

        $response = $this->actingAs($user)
            ->get(route('surat.klasifikasi.export'))
            ->assertOk()
            ->assertDownload()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );

        $temporaryPath = tempnam(sys_get_temp_dir(), 'klasifikasi-export-');
        file_put_contents($temporaryPath, $response->streamedContent());

        try {
            $spreadsheet = IOFactory::load($temporaryPath);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('DAFTAR KLASIFIKASI ARSIP', $sheet->getCell('A1')->getValue());
            $this->assertSame('Nomor', $sheet->getCell('A4')->getValue());
            $this->assertSame('Kode Klasifikasi', $sheet->getCell('B4')->getValue());
            $this->assertSame('Keterangan', $sheet->getCell('C4')->getValue());
            $this->assertSame('TU.01', $sheet->getCell('B5')->getValue());
            $this->assertSame('TU.02', $sheet->getCell('B6')->getValue());
            $this->assertSame(
                '=HYPERLINK("https://example.test")',
                $sheet->getCell('C6')->getValue()
            );
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C6')->getDataType());
            $this->assertNull($sheet->getCell('B7')->getValue());

            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function test_classification_page_contains_the_excel_export_action()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('surat.klasifikasi'))
            ->assertOk()
            ->assertSee(route('surat.klasifikasi.export'), false)
            ->assertSee('Export Klasifikasi ke Excel');
    }

    public function test_empty_classification_list_can_still_be_exported()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('surat.klasifikasi.export'))
            ->assertOk()
            ->assertDownload();
    }
}
