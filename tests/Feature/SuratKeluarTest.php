<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\AlihMediaStatus;
use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\AlihMediaStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SuratKeluarTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    private $access;

    private $filelist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AlihMediaStatusSeeder::class);

        $this->user = User::factory()->create();
        $this->withActiveYear(2026);
        $this->access = Access::create(['sifat_akses' => 'Biasa']);
        $status = Status::create(['nama_status' => 'Aktif']);
        $classification = Classification::create([
            'kode_klasifikasi' => 'TU.01',
            'keterangan' => 'Tata Usaha',
        ]);
        $this->filelist = Filelist::create([
            'classification_id' => $classification->id,
            'nama_berkas' => 'Berkas Aktif',
            'status_id' => $status->id,
        ]);

        Storage::fake('documents');
        $this->actingAs($this->user);
    }

    public function test_store_normalizes_all_valid_combinations_and_manipulated_srikandi_request()
    {
        $this->post(route('keluar.tambah'), $this->validPayload([
            'nomorSurat' => 'MANUAL/001',
            'jenis' => 0,
            'pemberkasan' => $this->filelist->id,
        ]))->assertRedirect(route('surat.keluar'));

        $this->assertDatabaseHas('outcomings', [
            'nomor_surat' => 'MANUAL/001',
            'is_digital' => false,
            'is_srikandi' => false,
            'filelist_id' => $this->filelist->id,
        ]);

        $this->post(route('keluar.tambah'), $this->validPayload([
            'nomorSurat' => 'DIGITAL/001',
            'jenis' => 1,
            'pemberkasan' => $this->filelist->id,
        ]))->assertRedirect(route('surat.keluar'));

        $this->assertDatabaseHas('outcomings', [
            'nomor_surat' => 'DIGITAL/001',
            'is_digital' => 1,
            'is_srikandi' => false,
            'filelist_id' => $this->filelist->id,
        ]);

        $this->post(route('keluar.tambah'), $this->validPayload([
            'nomorSurat' => 'SRIKANDI/001',
            'jenis' => 0,
            'isSrikandi' => 1,
            'pemberkasan' => $this->filelist->id,
        ]))->assertRedirect(route('surat.keluar'));

        $this->assertDatabaseHas('outcomings', [
            'nomor_surat' => 'SRIKANDI/001',
            'is_digital' => 1,
            'is_srikandi' => 1,
            'filelist_id' => null,
        ]);
    }

    public function test_outcomings_schema_no_longer_contains_legacy_location_column()
    {
        $this->assertFalse(Schema::hasColumn('outcomings', 'lokasi_berkas'));
        $this->assertTrue(Schema::hasColumn('outcomings', 'is_srikandi'));
        $this->assertFalse(Schema::hasColumn('filelists', 'is_alih_media'));
        $this->assertTrue(Schema::hasColumn('filelists', 'alih_media_status_id'));
        $this->assertSame(
            ['Diproses', 'Selesai', 'Gagal', 'Ditutup'],
            AlihMediaStatus::orderBy('id')->pluck('nama_status')->all()
        );
    }

    public function test_outcoming_boolean_flags_default_to_false()
    {
        $surat = Outcoming::create([
            'tanggal_surat' => '2026-07-24',
            'nomor_surat' => 'DEFAULT/001',
            'tujuan' => 'Tujuan',
            'perihal' => 'Perihal',
            'url' => 'dokumen/keluar/default.pdf',
            'tahun' => 2026,
            'access_id' => $this->access->id,
        ])->refresh();

        $this->assertFalse($surat->is_digital);
        $this->assertFalse($surat->is_srikandi);
    }

    public function test_factories_and_alih_media_status_seeder_follow_the_new_schema()
    {
        $this->seed(AlihMediaStatusSeeder::class);

        $digital = Outcoming::factory()->digital()->create([
            'access_id' => $this->access->id,
        ]);
        $srikandi = Outcoming::factory()->srikandi()->create([
            'access_id' => $this->access->id,
        ]);
        $processingFilelist = Filelist::factory()->alihMediaProcessing()->create([
            'classification_id' => $this->filelist->classification_id,
            'status_id' => $this->filelist->status_id,
        ]);

        $this->assertTrue($digital->is_digital);
        $this->assertFalse($digital->is_srikandi);
        $this->assertTrue($srikandi->is_digital);
        $this->assertTrue($srikandi->is_srikandi);
        $this->assertNull($srikandi->filelist_id);
        $this->assertSame(
            Filelist::ALIH_MEDIA_PROCESSING,
            (int) $processingFilelist->alih_media_status_id
        );
        $this->assertSame(4, AlihMediaStatus::count());
    }

    public function test_store_requires_jenis_and_sifat()
    {
        $payload = $this->validPayload();
        unset($payload['jenis'], $payload['sifat']);
        $payload['isSrikandi'] = 1;

        $this->post(route('keluar.tambah'), $payload)
            ->assertSessionHasErrors(['jenis', 'sifat']);

        $this->assertDatabaseCount('outcomings', 0);
    }

    public function test_watermarked_outcoming_cannot_be_edited_updated_or_deleted()
    {
        Storage::disk('documents')->put('dokumen/keluar/lama.pdf', 'pdf lama');
        Storage::disk('documents')->put('dokumen/keluar/watermark-lama.pdf', 'watermark lama');

        $surat = Outcoming::create([
            'tanggal_surat' => '2026-07-23',
            'nomor_surat' => 'LAMA/001',
            'tujuan' => 'Tujuan Lama',
            'perihal' => 'Perihal Lama',
            'url' => 'dokumen/keluar/lama.pdf',
            'tahun' => 2026,
            'is_digital' => false,
            'is_srikandi' => false,
            'url_watermarked' => 'dokumen/keluar/watermark-lama.pdf',
            'access_id' => $this->access->id,
            'filelist_id' => $this->filelist->id,
        ]);

        $this->get(route('keluar.edit', $surat->id))
            ->assertRedirect(route('surat.keluar'));

        $this->post(route('keluar.edit', $surat->id), $this->validPayload([
            'nomorSurat' => 'BARU/001',
            'jenis' => 1,
        ]))->assertRedirect(route('surat.keluar'));

        $this->delete(route('keluar.hapus', $surat->id))
            ->assertRedirect(route('surat.keluar'));

        $surat->refresh();

        $this->assertSame('LAMA/001', $surat->nomor_surat);
        $this->assertFalse($surat->is_digital);
        $this->assertSame('dokumen/keluar/watermark-lama.pdf', $surat->url_watermarked);
        $this->assertNull($surat->deleted_at);
        Storage::disk('documents')->assertExists('dokumen/keluar/lama.pdf');
        Storage::disk('documents')->assertExists('dokumen/keluar/watermark-lama.pdf');
    }

    public function test_update_unlocked_outcoming_replaces_pdf_after_successful_save()
    {
        Storage::disk('documents')->put('dokumen/keluar/lama.pdf', 'pdf lama');

        $surat = Outcoming::create([
            'tanggal_surat' => '2026-07-23',
            'nomor_surat' => 'LAMA/002',
            'tujuan' => 'Tujuan Lama',
            'perihal' => 'Perihal Lama',
            'url' => 'dokumen/keluar/lama.pdf',
            'tahun' => 2026,
            'access_id' => $this->access->id,
        ]);

        $this->post(route('keluar.edit', $surat->id), $this->validPayload([
            'nomorSurat' => 'BARU/002',
            'jenis' => 1,
        ]))->assertRedirect(route('surat.keluar'));

        $surat->refresh();

        $this->assertSame('BARU/002', $surat->nomor_surat);
        $this->assertTrue($surat->is_digital);
        Storage::disk('documents')->assertMissing('dokumen/keluar/lama.pdf');
        Storage::disk('documents')->assertExists($surat->url);
    }

    public function test_export_filters_srikandi_and_non_srikandi_by_letter_date()
    {
        $this->makeOutcoming([
            'tanggal_surat' => '2026-01-15',
            'nomor_surat' => 'MANUAL/DI-LUAR',
            'is_srikandi' => false,
        ]);
        $this->makeOutcoming([
            'tanggal_surat' => '2026-02-10',
            'nomor_surat' => 'MANUAL/DALAM',
            'perihal' => '=1+1',
            'is_srikandi' => false,
        ]);
        $this->makeOutcoming([
            'tanggal_surat' => '2026-02-20',
            'nomor_surat' => 'SRIKANDI/DALAM',
            'is_digital' => true,
            'is_srikandi' => true,
            'filelist_id' => null,
        ]);
        $this->makeOutcoming([
            'tanggal_surat' => '2025-02-20',
            'nomor_surat' => 'SRIKANDI/TAHUN-LAIN',
            'tahun' => 2025,
            'is_digital' => true,
            'is_srikandi' => true,
            'filelist_id' => null,
        ]);

        $allResponse = $this->get(route('surat.keluar.export-pencatatan'))
            ->assertOk()
            ->assertDownload();
        $allWorkbook = $this->workbookFromResponse($allResponse);
        $allSheet = $allWorkbook->getActiveSheet();

        $this->assertSame('Jalur Pengiriman: Semua', $allSheet->getCell('A4')->getValue());
        $this->assertSame('MANUAL/DI-LUAR', $allSheet->getCell('C8')->getValue());
        $this->assertSame('MANUAL/DALAM', $allSheet->getCell('C9')->getValue());
        $this->assertSame('SRIKANDI/DALAM', $allSheet->getCell('C10')->getValue());
        $this->assertNull($allSheet->getCell('C11')->getValue());
        $allWorkbook->disconnectWorksheets();

        $srikandiResponse = $this->get(route('surat.keluar.export-pencatatan', [
            'jalur_pengiriman' => 'srikandi',
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]))
            ->assertOk()
            ->assertDownload();
        $srikandiWorkbook = $this->workbookFromResponse($srikandiResponse);
        $srikandiSheet = $srikandiWorkbook->getActiveSheet();

        $this->assertSame('Jalur Pengiriman: SRIKANDI', $srikandiSheet->getCell('A4')->getValue());
        $this->assertSame(
            'Periode Tanggal Surat: 01-02-2026 s.d. 28-02-2026',
            $srikandiSheet->getCell('A5')->getValue()
        );
        $this->assertSame('SRIKANDI', $srikandiSheet->getCell('B8')->getValue());
        $this->assertSame('SRIKANDI/DALAM', $srikandiSheet->getCell('C8')->getValue());
        $this->assertNull($srikandiSheet->getCell('C9')->getValue());
        $srikandiWorkbook->disconnectWorksheets();

        $manualResponse = $this->get(route('surat.keluar.export-pencatatan', [
            'jalur_pengiriman' => 'non_srikandi',
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]))
            ->assertOk()
            ->assertDownload();
        $manualWorkbook = $this->workbookFromResponse($manualResponse);
        $manualSheet = $manualWorkbook->getActiveSheet();

        $this->assertSame('Tanpa SRIKANDI', $manualSheet->getCell('B8')->getValue());
        $this->assertSame('MANUAL/DALAM', $manualSheet->getCell('C8')->getValue());
        $this->assertSame('=1+1', $manualSheet->getCell('E8')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $manualSheet->getCell('E8')->getDataType());
        $this->assertNull($manualSheet->getCell('C9')->getValue());
        $manualWorkbook->disconnectWorksheets();
    }

    public function test_outcoming_export_rejects_invalid_filters()
    {
        $this->from(route('surat.keluar'))
            ->get(route('surat.keluar.export-pencatatan', [
                'jalur_pengiriman' => 'tidak-valid',
                'tanggal_dari' => '2026-03-01',
                'tanggal_sampai' => '2026-02-01',
            ]))
            ->assertRedirect(route('surat.keluar'))
            ->assertSessionHasErrors([
                'jalur_pengiriman',
                'tanggal_sampai',
            ]);
    }

    public function test_outcoming_list_uses_the_same_srikandi_and_date_filters()
    {
        $this->makeOutcoming([
            'tanggal_surat' => '2026-02-10',
            'nomor_surat' => 'MANUAL/FILTER',
            'is_srikandi' => false,
        ]);
        $this->makeOutcoming([
            'tanggal_surat' => '2026-02-20',
            'nomor_surat' => 'SRIKANDI/FILTER',
            'is_digital' => true,
            'is_srikandi' => true,
            'filelist_id' => null,
        ]);
        $this->makeOutcoming([
            'tanggal_surat' => '2026-03-20',
            'nomor_surat' => 'SRIKANDI/DI-LUAR',
            'is_digital' => true,
            'is_srikandi' => true,
            'filelist_id' => null,
        ]);

        $this->get(route('surat.keluar', [
            'jalur_pengiriman' => 'srikandi',
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.nomor_surat', 'SRIKANDI/FILTER');
    }

    public function test_outcoming_page_contains_the_filtered_export_dialog()
    {
        $this->get(route('surat.keluar'))
            ->assertOk()
            ->assertSee('modalFilterSuratKeluar', false)
            ->assertSee('name="jalur_pengiriman"', false)
            ->assertSee('name="tanggal_dari"', false)
            ->assertSee('name="tanggal_sampai"', false)
            ->assertSee('btnCariFilter', false)
            ->assertSee('btnExportSuratKeluar', false)
            ->assertSee('Terapkan Filter')
            ->assertSee('Unduh Excel dengan Filter Aktif');
    }

    private function makeOutcoming(array $overrides = []): Outcoming
    {
        return Outcoming::create(array_merge([
            'tanggal_surat' => '2026-07-23',
            'nomor_surat' => 'OUT/001',
            'tujuan' => 'Tujuan Surat',
            'perihal' => 'Perihal Surat',
            'url' => 'dokumen/keluar/surat.pdf',
            'tahun' => 2026,
            'is_digital' => false,
            'is_srikandi' => false,
            'access_id' => $this->access->id,
            'filelist_id' => $this->filelist->id,
        ], $overrides));
    }

    private function workbookFromResponse($response)
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'surat-keluar-export-');
        file_put_contents($temporaryPath, $response->streamedContent());

        try {
            return IOFactory::load($temporaryPath);
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'jenis' => 0,
            'tanggalSurat' => '2026-07-23',
            'nomorSurat' => 'SK/001',
            'tujuan' => 'Tujuan Surat',
            'perihal' => 'Perihal Surat',
            'sifat' => $this->access->id,
            'pemberkasan' => '',
            'berkas' => UploadedFile::fake()->createWithContent(
                'surat.pdf',
                "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
            ),
        ], $overrides);
    }
}
