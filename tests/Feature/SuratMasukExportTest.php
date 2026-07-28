<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\Incoming;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SuratMasukExportTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    private $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->withActiveYear(2026);
        $this->access = Access::create(['sifat_akses' => 'Biasa']);
        $this->actingAs($this->user);
    }

    public function test_export_filters_srikandi_and_non_srikandi_by_letter_date()
    {
        $this->makeIncoming([
            'nomor_agenda' => 1,
            'tanggal_surat' => '2026-01-15',
            'nomor_surat' => 'MANUAL/DI-LUAR',
        ]);
        $this->makeIncoming([
            'nomor_agenda' => 2,
            'tanggal_surat' => '2026-02-10',
            'nomor_surat' => 'MANUAL/DALAM',
            'perihal' => '=1+1',
        ]);
        $this->makeIncoming([
            'nomor_agenda' => null,
            'tanggal_surat' => '2026-02-20',
            'nomor_surat' => 'SRIKANDI/DALAM',
            'is_srikandi' => true,
        ]);
        $this->makeIncoming([
            'nomor_agenda' => null,
            'tanggal_surat' => '2025-02-20',
            'nomor_surat' => 'SRIKANDI/TAHUN-LAIN',
            'tahun' => 2025,
            'is_srikandi' => true,
        ]);

        $allResponse = $this->get(route('surat.masuk.export-pencatatan'))
            ->assertOk()
            ->assertDownload();
        $allWorkbook = $this->workbookFromResponse($allResponse);
        $allSheet = $allWorkbook->getActiveSheet();

        $this->assertSame('Sumber Surat: Semua', $allSheet->getCell('A4')->getValue());
        $this->assertSame('MANUAL/DI-LUAR', $allSheet->getCell('G8')->getValue());
        $this->assertSame('MANUAL/DALAM', $allSheet->getCell('G9')->getValue());
        $this->assertSame('SRIKANDI', $allSheet->getCell('A10')->getValue());
        $this->assertSame('SRIKANDI/DALAM', $allSheet->getCell('G10')->getValue());
        $this->assertNull($allSheet->getCell('G11')->getValue());
        $allWorkbook->disconnectWorksheets();

        $srikandiResponse = $this->get(route('surat.masuk.export-pencatatan', [
            'sumber_surat' => 'srikandi',
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]))
            ->assertOk()
            ->assertDownload();
        $srikandiWorkbook = $this->workbookFromResponse($srikandiResponse);
        $srikandiSheet = $srikandiWorkbook->getActiveSheet();

        $this->assertSame(
            'Sumber Surat: Dari SRIKANDI',
            $srikandiSheet->getCell('A4')->getValue()
        );
        $this->assertSame(
            'Periode Tanggal Surat: 01-02-2026 s.d. 28-02-2026',
            $srikandiSheet->getCell('A5')->getValue()
        );
        $this->assertSame('SRIKANDI', $srikandiSheet->getCell('A8')->getValue());
        $this->assertSame('Dari SRIKANDI', $srikandiSheet->getCell('B8')->getValue());
        $this->assertSame('SRIKANDI/DALAM', $srikandiSheet->getCell('G8')->getValue());
        $this->assertNull($srikandiSheet->getCell('G9')->getValue());
        $srikandiWorkbook->disconnectWorksheets();

        $manualResponse = $this->get(route('surat.masuk.export-pencatatan', [
            'sumber_surat' => 'non_srikandi',
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]))
            ->assertOk()
            ->assertDownload();
        $manualWorkbook = $this->workbookFromResponse($manualResponse);
        $manualSheet = $manualWorkbook->getActiveSheet();

        $this->assertSame('Bukan dari SRIKANDI', $manualSheet->getCell('B8')->getValue());
        $this->assertSame('MANUAL/DALAM', $manualSheet->getCell('G8')->getValue());
        $this->assertSame('=1+1', $manualSheet->getCell('H8')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $manualSheet->getCell('H8')->getDataType());
        $this->assertNull($manualSheet->getCell('G9')->getValue());
        $manualWorkbook->disconnectWorksheets();
    }

    public function test_incoming_list_uses_the_same_srikandi_and_date_filters()
    {
        $this->makeIncoming([
            'nomor_agenda' => 1,
            'tanggal_surat' => '2026-02-10',
            'nomor_surat' => 'MANUAL/FILTER',
        ]);
        $this->makeIncoming([
            'nomor_agenda' => null,
            'tanggal_surat' => '2026-02-20',
            'nomor_surat' => 'SRIKANDI/FILTER',
            'is_srikandi' => true,
        ]);
        $this->makeIncoming([
            'nomor_agenda' => null,
            'tanggal_surat' => '2026-03-20',
            'nomor_surat' => 'SRIKANDI/DI-LUAR',
            'is_srikandi' => true,
        ]);

        $this->get(route('surat.masuk', [
            'sumber_surat' => 'srikandi',
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.nomor_surat', 'SRIKANDI/FILTER');
    }

    public function test_incoming_export_rejects_invalid_filters()
    {
        $this->from(route('surat.masuk'))
            ->get(route('surat.masuk.export-pencatatan', [
                'sumber_surat' => 'tidak-valid',
                'tanggal_dari' => '2026-03-01',
                'tanggal_sampai' => '2026-02-01',
            ]))
            ->assertRedirect(route('surat.masuk'))
            ->assertSessionHasErrors([
                'sumber_surat',
                'tanggal_sampai',
            ]);
    }

    public function test_incoming_page_contains_the_filtered_export_dialog()
    {
        $this->get(route('surat.masuk'))
            ->assertOk()
            ->assertSee('modalFilterSuratMasuk', false)
            ->assertSee('name="sumber_surat"', false)
            ->assertSee('name="tanggal_dari"', false)
            ->assertSee('name="tanggal_sampai"', false)
            ->assertSee('btnCariFilter', false)
            ->assertSee('btnExportSuratMasuk', false)
            ->assertSee('Terapkan Filter')
            ->assertSee('Unduh Excel dengan Filter Aktif');
    }

    private function makeIncoming(array $overrides = []): Incoming
    {
        return Incoming::create(array_merge([
            'nomor_agenda' => 1,
            'tanggal_diterima' => '2026-07-24',
            'nomor_surat' => 'IN/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-07-23',
            'perihal' => 'Perihal Surat',
            'url' => 'dokumen/masuk/surat.pdf',
            'tahun' => 2026,
            'is_srikandi' => false,
            'access_id' => $this->access->id,
            'filelist_id' => null,
        ], $overrides));
    }

    private function workbookFromResponse($response)
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'surat-masuk-export-');
        file_put_contents($temporaryPath, $response->streamedContent());

        try {
            return IOFactory::load($temporaryPath);
        } finally {
            @unlink($temporaryPath);
        }
    }
}
