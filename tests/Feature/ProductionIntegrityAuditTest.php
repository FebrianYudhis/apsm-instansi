<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Services\ProductionIntegrityAuditor;
use Database\Seeders\AlihMediaStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    private $access;

    private $filelist;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('documents');

        $this->access = Access::create(['sifat_akses' => 'Biasa']);
        $status = Status::create(['nama_status' => Status::ACTIVE]);
        $classification = Classification::create([
            'kode_klasifikasi' => 'TU.02',
            'keterangan' => 'Tata Usaha',
        ]);
        $this->filelist = Filelist::create([
            'classification_id' => $classification->id,
            'nama_berkas' => 'Berkas Audit',
            'status_id' => $status->id,
        ]);
    }

    public function test_integrity_command_returns_success_for_consistent_data()
    {
        $this->makeIncoming();
        $this->makeOutcoming();
        Digital::create([
            'perihal' => 'Surat Digital',
            'url' => 'dokumen/digital/digital.pdf',
        ]);

        Storage::disk('documents')->put('dokumen/masuk/masuk.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/keluar/keluar.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/digital/digital.pdf', 'pdf');

        $report = app(ProductionIntegrityAuditor::class)->audit();
        $exitCode = Artisan::call('audit:integritas-production');

        $this->assertSame(0, $exitCode);
        $this->assertTrue($report['read_only']);
        $this->assertSame([], $report['findings']);
    }

    public function test_integrity_command_reports_missing_watermarks_and_orphan_files()
    {
        $this->seed(AlihMediaStatusSeeder::class);

        $this->filelist->update(['alih_media_status_id' => Filelist::ALIH_MEDIA_DONE]);
        $this->makeIncoming([
            'filelist_id' => $this->filelist->id,
            'url_watermarked' => 'dokumen/alih-media/hilang.pdf',
        ]);

        Storage::disk('documents')->put('dokumen/masuk/masuk.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/digital/yatim.pdf', 'pdf');

        $report = app(ProductionIntegrityAuditor::class)->audit();
        $exitCode = Artisan::call('audit:integritas-production', ['--format' => 'json']);
        $codes = array_column($report['findings'], 'code');

        $this->assertSame(1, $exitCode);
        $this->assertTrue($report['read_only']);
        $this->assertContains('document.file_missing', $codes);
        $this->assertContains('document.orphan_file', $codes);
        $this->assertContains('alih_media.incomplete_watermarks', $codes);
    }

    public function test_year_filter_does_not_create_false_orphans_and_command_does_not_mutate_data()
    {
        $this->makeIncoming([
            'nomor_agenda' => 1,
            'tahun' => 2025,
            'url' => 'dokumen/masuk/tahun-lain.pdf',
        ]);
        $this->makeIncoming([
            'nomor_agenda' => 2,
            'tahun' => 2026,
            'url' => 'dokumen/masuk/tahun-audit.pdf',
        ]);
        $this->makeOutcoming();
        Digital::create([
            'perihal' => 'Digital Audit',
            'url' => 'dokumen/digital/digital-audit.pdf',
        ]);

        Storage::disk('documents')->put('dokumen/masuk/tahun-lain.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/masuk/tahun-audit.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/keluar/keluar.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/digital/digital-audit.pdf', 'pdf');

        $databaseBefore = $this->databaseSnapshot();
        $filesBefore = Storage::disk('documents')->allFiles('dokumen');

        $report = app(ProductionIntegrityAuditor::class)->audit(2026);
        $exitCode = Artisan::call('audit:integritas-production', ['--year' => '2026']);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $report['counts']['incomings_checked']);
        $this->assertSame([], $report['findings']);
        $this->assertSame($databaseBefore, $this->databaseSnapshot());
        $this->assertSame($filesBefore, Storage::disk('documents')->allFiles('dokumen'));
    }

    public function test_integrity_command_rejects_invalid_year()
    {
        $exitCode = Artisan::call('audit:integritas-production', ['--year' => 'semua']);

        $this->assertSame(2, $exitCode);
    }

    public function test_integrity_audit_reports_soft_deleted_counts_without_failing()
    {
        $incoming = $this->makeIncoming();
        $outcoming = $this->makeOutcoming();
        $digital = Digital::create([
            'perihal' => 'Digital Soft Deleted',
            'url' => 'dokumen/digital/soft-deleted.pdf',
        ]);
        $classification = Classification::create([
            'kode_klasifikasi' => 'TU.03',
            'keterangan' => 'Klasifikasi Soft Deleted',
        ]);
        $filelist = Filelist::create([
            'classification_id' => $classification->id,
            'nama_berkas' => 'Berkas Soft Deleted',
            'status_id' => $this->filelist->status_id,
        ]);

        Storage::disk('documents')->put('dokumen/masuk/masuk.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/keluar/keluar.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/digital/soft-deleted.pdf', 'pdf');

        $incoming->delete();
        $outcoming->delete();
        $digital->delete();
        $classification->delete();
        $filelist->delete();

        $report = app(ProductionIntegrityAuditor::class)->audit();
        $exitCode = Artisan::call('audit:integritas-production');

        $this->assertSame(0, $exitCode);
        $this->assertSame([], $report['findings']);
        $this->assertSame(1, $report['soft_deleted']['incomings']['count']);
        $this->assertSame(1, $report['soft_deleted']['outcomings']['count']);
        $this->assertSame(1, $report['soft_deleted']['digitals']['count']);
        $this->assertSame(1, $report['soft_deleted']['classifications']['count']);
        $this->assertSame(1, $report['soft_deleted']['filelists']['count']);
        $this->assertContains($incoming->id, $report['soft_deleted']['incomings']['sample_ids']);
    }

    public function test_integrity_audit_reports_invalid_incoming_srikandi_state()
    {
        $invalidState = $this->makeIncoming([
            'url' => 'dokumen/masuk/srikandi-state.pdf',
        ]);
        $invalidFlag = $this->makeIncoming([
            'nomor_agenda' => 2,
            'url' => 'dokumen/masuk/srikandi-flag.pdf',
        ]);

        DB::table('incomings')->where('id', $invalidState->id)->update([
            'is_srikandi' => 1,
            'nomor_agenda' => 999,
            'filelist_id' => $this->filelist->id,
        ]);
        DB::table('incomings')->where('id', $invalidFlag->id)->update([
            'is_srikandi' => 2,
        ]);

        Storage::disk('documents')->put('dokumen/masuk/srikandi-state.pdf', 'pdf');
        Storage::disk('documents')->put('dokumen/masuk/srikandi-flag.pdf', 'pdf');

        $codes = array_column(
            app(ProductionIntegrityAuditor::class)->audit()['findings'],
            'code'
        );

        $this->assertContains('incoming.invalid_srikandi_flag', $codes);
        $this->assertContains('incoming.invalid_srikandi_state', $codes);
    }

    public function test_integrity_audit_reports_a_document_that_remains_public()
    {
        $this->makeIncoming();
        Storage::disk('documents')->put('dokumen/masuk/masuk.pdf', 'pdf');
        Storage::disk('public')->put('dokumen/masuk/masuk.pdf', 'pdf');

        $report = app(ProductionIntegrityAuditor::class)->audit();
        $codes = array_column($report['findings'], 'code');

        $this->assertContains('document.public_file_exposure', $codes);
    }

    private function makeIncoming(array $overrides = []): Incoming
    {
        return Incoming::create(array_merge([
            'nomor_agenda' => 1,
            'tanggal_diterima' => '2026-07-23',
            'nomor_surat' => 'IN/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-07-22',
            'perihal' => 'Perihal Masuk',
            'url' => 'dokumen/masuk/masuk.pdf',
            'tahun' => 2026,
            'is_srikandi' => false,
            'access_id' => $this->access->id,
            'filelist_id' => null,
        ], $overrides));
    }

    private function makeOutcoming(array $overrides = []): Outcoming
    {
        return Outcoming::create(array_merge([
            'tanggal_surat' => '2026-07-23',
            'nomor_surat' => 'OUT/001',
            'tujuan' => 'Tujuan',
            'perihal' => 'Perihal Keluar',
            'url' => 'dokumen/keluar/keluar.pdf',
            'tahun' => 2026,
            'access_id' => $this->access->id,
            'filelist_id' => null,
        ], $overrides));
    }

    private function databaseSnapshot(): array
    {
        $snapshot = [];
        foreach ([
            'incomings',
            'outcomings',
            'digitals',
            'filelists',
            'accesses',
            'classifications',
            'statuses',
            'activity_log',
        ] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }
}
