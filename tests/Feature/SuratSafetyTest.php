<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use App\Services\DocumentService;
use Database\Seeders\AlihMediaStatusSeeder;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\TestCase;

class SuratSafetyTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    private $access;

    private $filelist;

    private $targetFilelist;

    private $nextAgenda = 1;

    private $activeStatus;

    private $proposedTransferStatus;

    private $permanentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AlihMediaStatusSeeder::class);

        $this->user = User::factory()->create();
        $this->withActiveYear(2026);
        $this->access = Access::create(['sifat_akses' => 'Biasa']);
        $this->activeStatus = Status::create(['nama_status' => Status::ACTIVE]);
        $this->proposedTransferStatus = Status::create(['nama_status' => Status::PROPOSE_TRANSFER]);
        $this->permanentStatus = Status::create(['nama_status' => Status::PERMANENT]);
        $classification = Classification::create([
            'kode_klasifikasi' => 'TU.02',
            'keterangan' => 'Tata Usaha',
        ]);
        $this->filelist = Filelist::create([
            'classification_id' => $classification->id,
            'nama_berkas' => 'Berkas Asal',
            'status_id' => $this->activeStatus->id,
        ]);
        $this->targetFilelist = Filelist::create([
            'classification_id' => $classification->id,
            'nama_berkas' => 'Berkas Tujuan',
            'status_id' => $this->activeStatus->id,
        ]);

        config(['services.mfa.secret' => 'JBSWY3DPEHPK3PXP']);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('documents');
    }

    public function test_guest_access_is_fail_closed_and_does_not_depend_on_access_ids()
    {
        $restricted = $this->makeIncoming([
            'nomor_surat' => 'GUEST-RESTRICTED',
            'access_id' => null,
            'url' => 'dokumen/masuk/rahasia.pdf',
        ]);

        $public = $this->makeIncoming([
            'nomor_surat' => 'GUEST-PUBLIC',
            'access_id' => $this->access->id,
            'url' => 'dokumen/masuk/publik.pdf',
        ]);
        $restrictedOutcoming = $this->makeOutcoming([
            'nomor_surat' => 'GUEST-OUT-RESTRICTED',
            'access_id' => null,
            'url' => 'dokumen/keluar/rahasia.pdf',
        ]);
        Storage::disk('documents')->put('dokumen/masuk/publik.pdf', "%PDF-1.4\n%%EOF");

        $restrictedResponse = $this->get(route('guest.masuk', ['pencarian' => 'GUEST-RESTRICTED']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('data.0.id', $restricted->id)
            ->assertJsonPath('data.0.requires_mfa', true)
            ->assertJsonPath('data.0.access_state', 'undefined');
        $this->assertArrayNotHasKey('url', $restrictedResponse->json('data.0'));

        $publicResponse = $this->get(route('guest.masuk', ['pencarian' => 'GUEST-PUBLIC']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('data.0.id', $public->id)
            ->assertJsonPath('data.0.requires_mfa', false)
            ->assertJsonPath('data.0.access_state', 'public')
            ->assertJsonPath(
                'data.0.document_url',
                app(DocumentService::class)->publicUrl(
                    DocumentService::TYPE_INCOMING,
                    $public
                )
            );
        $this->assertArrayNotHasKey('url', $publicResponse->json('data.0'));

        $restrictedOutcomingResponse = $this->get(route('guest.keluar', ['pencarian' => 'GUEST-OUT-RESTRICTED']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('data.0.id', $restrictedOutcoming->id)
            ->assertJsonPath('data.0.requires_mfa', true)
            ->assertJsonPath('data.0.access_state', 'undefined');
        $this->assertArrayNotHasKey('url', $restrictedOutcomingResponse->json('data.0'));
    }

    public function test_guest_portal_pages_render_the_public_archive_layout()
    {
        $this->get(route('guest'))
            ->assertOk()
            ->assertSee('Portal Arsip Publik')
            ->assertSee('guest-portal.min.css')
            ->assertSee('mfa-code-input.min.css')
            ->assertSee('mfa-code-input.min.js');

        $this->get(route('guest.masuk'))
            ->assertOk()
            ->assertSee('guest-document-grid');

        $this->get(route('guest.keluar'))
            ->assertOk()
            ->assertSee('guest-document-grid');

        $this->get(route('guest.digital'))
            ->assertOk()
            ->assertSee('guest-document-grid')
            ->assertDontSee('Format');
    }

    public function test_incoming_in_alih_media_process_cannot_be_edited_moved_or_deleted()
    {
        $this->actingAs($this->user);
        $this->filelist->update(['alih_media_status_id' => Filelist::ALIH_MEDIA_PROCESSING]);
        $surat = $this->makeIncoming([
            'nomor_surat' => 'LOCKED-IN',
            'filelist_id' => $this->filelist->id,
        ]);

        $this->get(route('masuk.edit', $surat->id))
            ->assertRedirect(route('surat.masuk'));

        $this->post(route('masuk.edit', $surat->id), $this->incomingPayload([
            'nomorSurat' => 'CHANGED-IN',
        ]))->assertRedirect(route('surat.masuk'));

        $this->post(route('berkas.gantiLokasiBulk'), [
            'berkas_asal' => $this->filelist->id,
            'pemberkasan' => $this->targetFilelist->id,
            'items' => ['masuk:'.$surat->id],
        ])->assertRedirect();

        $this->delete(route('masuk.hapus', $surat->id), [
            'alasan_penghapusan' => 'Memverifikasi surat terkunci tidak dapat dihapus',
        ])
            ->assertRedirect(route('surat.masuk'));

        $surat->refresh();
        $this->assertSame('LOCKED-IN', $surat->nomor_surat);
        $this->assertSame($this->filelist->id, $surat->filelist_id);
        $this->assertNull($surat->deleted_at);
    }

    public function test_incoming_and_digital_pdf_replacements_keep_a_valid_new_file()
    {
        $this->actingAs($this->user);
        Storage::disk('documents')->put('dokumen/masuk/lama.pdf', 'lama masuk');
        Storage::disk('documents')->put('dokumen/digital/lama.pdf', 'lama digital');

        $incoming = $this->makeIncoming(['url' => 'dokumen/masuk/lama.pdf']);
        $digital = Digital::create([
            'perihal' => 'Digital Lama',
            'url' => 'dokumen/digital/lama.pdf',
        ]);

        $this->post(route('masuk.edit', $incoming->id), $this->incomingPayload())
            ->assertRedirect(route('surat.masuk'));

        $this->post(route('digital.edit', $digital->id), [
            'perihal' => 'Digital Baru',
            'berkas' => $this->fakePdf('digital-baru.pdf'),
        ])->assertRedirect(route('surat.digital'));

        $incoming->refresh();
        $digital->refresh();

        Storage::disk('documents')->assertMissing('dokumen/masuk/lama.pdf');
        Storage::disk('documents')->assertMissing('dokumen/digital/lama.pdf');
        Storage::disk('documents')->assertExists($incoming->url);
        Storage::disk('documents')->assertExists($digital->url);
        $this->assertSame('Digital Baru', $digital->perihal);
    }

    public function test_digital_schema_and_forms_no_longer_contain_file_location()
    {
        $this->assertFalse(Schema::hasColumn('digitals', 'lokasi_berkas'));

        $digital = Digital::create([
            'perihal' => 'Digital Tanpa Lokasi',
            'url' => 'dokumen/digital/tanpa-lokasi.pdf',
        ]);

        $this->actingAs($this->user)
            ->get(route('digital.tambah'))
            ->assertOk()
            ->assertDontSee('lokasiBerkas', false);

        $this->get(route('digital.edit', $digital->id))
            ->assertOk()
            ->assertDontSee('lokasiBerkas', false);

        $this->get(route('surat.digital'))
            ->assertOk()
            ->assertDontSee('lokasi_berkas', false);
    }

    public function test_incoming_schema_and_forms_use_srikandi_flag_instead_of_file_location()
    {
        $this->assertFalse(Schema::hasColumn('incomings', 'lokasi_berkas'));
        $this->assertTrue(Schema::hasColumn('incomings', 'is_srikandi'));

        $this->actingAs($this->user)
            ->get(route('masuk.tambah'))
            ->assertOk()
            ->assertSee('isSrikandi', false)
            ->assertDontSee('lokasiBerkas', false);

        $incoming = $this->makeIncoming();

        $this->get(route('masuk.edit', $incoming->id))
            ->assertOk()
            ->assertSee('isSrikandi', false)
            ->assertDontSee('lokasiBerkas', false);

        $this->get(route('surat.masuk'))
            ->assertOk()
            ->assertDontSee('lokasi_berkas', false);
    }

    public function test_incoming_srikandi_is_boolean_and_forces_agenda_and_filelist_to_null()
    {
        $this->actingAs($this->user);

        $this->post(route('masuk.tambah'), $this->incomingPayload([
            'isSrikandi' => 1,
            'nomorAgenda' => 9876,
            'nomorSurat' => 'IN/SRIKANDI',
            'pemberkasan' => $this->filelist->id,
        ]))->assertRedirect(route('surat.masuk'));

        $srikandi = Incoming::where('nomor_surat', 'IN/SRIKANDI')->firstOrFail();
        $this->assertTrue($srikandi->is_srikandi);
        $this->assertSame(1, (int) $srikandi->getRawOriginal('is_srikandi'));
        $this->assertNull($srikandi->nomor_agenda);
        $this->assertNull($srikandi->filelist_id);

        $manual = $this->makeIncoming([
            'filelist_id' => $this->filelist->id,
        ]);

        $this->post(route('masuk.edit', $manual->id), $this->incomingPayload([
            'isSrikandi' => 1,
            'nomorAgenda' => 9877,
            'nomorSurat' => 'IN/UPDATED-SRIKANDI',
            'pemberkasan' => $this->targetFilelist->id,
        ]))->assertRedirect(route('surat.masuk'));

        $manual->refresh();
        $this->assertTrue($manual->is_srikandi);
        $this->assertNull($manual->nomor_agenda);
        $this->assertNull($manual->filelist_id);

        $this->from(route('masuk.tambah'))
            ->post(route('masuk.tambah'), $this->incomingPayload([
                'isSrikandi' => 2,
                'nomorSurat' => 'IN/INVALID-FLAG',
            ]))
            ->assertRedirect(route('masuk.tambah'))
            ->assertSessionHasErrors('isSrikandi');

        $this->assertDatabaseMissing('incomings', [
            'nomor_surat' => 'IN/INVALID-FLAG',
        ]);

        $this->from(route('masuk.tambah'))
            ->post(route('masuk.tambah'), $this->incomingPayload([
                'isSrikandi' => 0,
                'nomorAgenda' => null,
                'nomorSurat' => 'IN/MANUAL-NO-AGENDA',
            ]))
            ->assertRedirect(route('masuk.tambah'))
            ->assertSessionHasErrors('nomorAgenda');
    }

    public function test_watermarked_outcoming_cannot_be_moved_between_filelists()
    {
        $this->actingAs($this->user);
        $surat = $this->makeOutcoming([
            'nomor_surat' => 'LOCKED-MOVE',
            'filelist_id' => $this->filelist->id,
            'url_watermarked' => 'dokumen/alih-media/locked.pdf',
        ]);

        $this->post(route('berkas.gantiLokasiBulk'), [
            'berkas_asal' => $this->filelist->id,
            'pemberkasan' => $this->targetFilelist->id,
            'items' => ['keluar:'.$surat->id],
        ])->assertRedirect();

        $surat->refresh();
        $this->assertSame($this->filelist->id, $surat->filelist_id);
        $this->assertSame('dokumen/alih-media/locked.pdf', $surat->url_watermarked);
    }

    public function test_dashboard_excludes_srikandi_letters_from_unfiled_count()
    {
        $this->actingAs($this->user);

        $this->makeIncoming(['nomor_surat' => 'IN-NORMAL']);
        $this->makeIncoming([
            'nomor_surat' => 'IN-SRIKANDI',
            'nomor_agenda' => null,
            'is_srikandi' => true,
        ]);
        $this->makeOutcoming(['nomor_surat' => 'OUT-NORMAL']);
        $this->makeOutcoming([
            'nomor_surat' => 'OUT-SRIKANDI',
            'is_digital' => 1,
            'is_srikandi' => 1,
        ]);

        $this->get('/app')
            ->assertOk()
            ->assertViewHas('suratBelumBerkas', 2);
    }

    public function test_watermark_is_complete_only_when_the_physical_file_exists()
    {
        $surat = $this->makeIncoming([
            'url_watermarked' => 'dokumen/alih-media/hasil.pdf',
        ]);

        $this->assertFalse($surat->hasExistingWatermarkedFile());

        Storage::disk('documents')->put('dokumen/alih-media/hasil.pdf', 'pdf');

        $this->assertTrue($surat->hasExistingWatermarkedFile());
    }

    public function test_mfa_qr_is_rendered_locally_without_external_qr_service()
    {
        $this->actingAs($this->user)
            ->get(route('mfa.index'))
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertDontSee('api.qrserver.com', false)
            ->assertSee('mfa-code-input.min.css')
            ->assertSee('mfa-code-input.min.js');
    }

    public function test_authenticated_mfa_actions_require_exactly_six_numeric_digits()
    {
        $this->actingAs($this->user);

        $this->from(route('surat.berkas'))
            ->post(route('berkas.pindah', [
                $this->filelist->id,
                $this->proposedTransferStatus->id,
            ]), [
                'password_status_berkas' => '12345',
            ])->assertSessionHasErrors('password_status_berkas');

        $this->from(route('alih-media.penyeleksian'))
            ->post(route('alih-media.penyeleksian.proses', $this->filelist->id), [
                'passcode_access' => '12ab56',
            ])->assertSessionHasErrors('passcode_access');

        $this->assertSame($this->activeStatus->id, $this->filelist->fresh()->status_id);
        $this->assertNull($this->filelist->fresh()->alih_media_status_id);
    }

    public function test_authenticated_mfa_dialogs_use_the_shared_six_digit_component()
    {
        $this->actingAs($this->user);

        $this->get(route('surat.berkas'))
            ->assertOk()
            ->assertSee('MfaCodeInput.prompt', false)
            ->assertDontSee("input: 'password'", false);

        $this->get(route('alih-media.penyeleksian'))
            ->assertOk()
            ->assertSee('MfaCodeInput.prompt', false)
            ->assertDontSee("input: 'password'", false);
    }

    public function test_filelist_status_cannot_skip_the_allowed_workflow()
    {
        $this->actingAs($this->user);

        $this->post(route('berkas.pindah', [
            $this->filelist->id,
            $this->permanentStatus->id,
        ]), [
            'password_status_berkas' => $this->currentOtp(),
        ])->assertRedirect(route('surat.berkas'));

        $this->assertSame($this->activeStatus->id, $this->filelist->fresh()->status_id);

        $this->post(route('berkas.pindah', [
            $this->filelist->id,
            $this->proposedTransferStatus->id,
        ]), [
            'password_status_berkas' => $this->currentOtp(),
        ])->assertRedirect(route('surat.berkas'));

        $this->assertSame($this->proposedTransferStatus->id, $this->filelist->fresh()->status_id);
    }

    public function test_filelist_in_alih_media_cannot_change_metadata_status_or_be_deleted()
    {
        $this->actingAs($this->user);
        $this->filelist->update(['alih_media_status_id' => Filelist::ALIH_MEDIA_PROCESSING]);

        $this->post(route('berkas.edit', $this->filelist->id), [
            'kodeKlasifikasi' => $this->filelist->classification_id,
            'namaBerkas' => 'Nama Yang Dimanipulasi',
            'retensiAktif' => 5,
            'retensiInaktif' => 10,
            'keteranganAkhir' => 'Permanen',
        ])->assertRedirect();

        $this->post(route('berkas.pindah', [
            $this->filelist->id,
            $this->proposedTransferStatus->id,
        ]), [
            'password_status_berkas' => $this->currentOtp(),
        ])->assertRedirect(route('surat.berkas'));

        $this->delete(route('berkas.hapus', $this->filelist->id), [
            'alasan_penghapusan' => 'Memverifikasi berkas terkunci tidak dapat dihapus',
        ])
            ->assertRedirect(route('surat.berkas'));

        $this->filelist->refresh();
        $this->assertSame('Berkas Asal', $this->filelist->nama_berkas);
        $this->assertSame($this->activeStatus->id, $this->filelist->status_id);
        $this->assertNull($this->filelist->deleted_at);
    }

    public function test_pindah_tahun_only_accepts_safe_internal_paths()
    {
        $this->actingAs($this->user);
        $maliciousRedirect = url('/').'@evil.example/surat/masuk/edit/1';

        $this->post(route('pindah-tahun', ['tahun' => 2025]), [
            'redirect_to' => $maliciousRedirect,
        ])->assertRedirect(route('dashboard'));

        $dashboardPath = route('dashboard', absolute: false).'?bagian=surat-terbaru';
        $this->post(route('pindah-tahun', ['tahun' => 2026]), [
            'redirect_to' => $dashboardPath,
        ])->assertRedirect($dashboardPath);

        $validPath = route('masuk.edit', 123, false);
        $this->post(route('pindah-tahun', ['tahun' => 2025]), [
            'redirect_to' => $validPath,
        ])->assertRedirect($validPath);
    }

    public function test_pindah_tahun_rejects_get_and_out_of_range_years()
    {
        $this->actingAs($this->user);

        $this->get(route('pindah-tahun', ['tahun' => 2025]))
            ->assertStatus(405);

        $this->post(route('pindah-tahun', ['tahun' => 9999]))
            ->assertNotFound();

        $this->assertSame(2026, session('active_year'));
    }

    public function test_dispatch_failure_restores_alih_media_status()
    {
        $this->actingAs($this->user);
        $this->filelist->update(['keterangan_akhir' => 'Permanen']);
        $this->makeIncoming(['filelist_id' => $this->filelist->id]);

        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->andThrow(new RuntimeException('Queue unavailable'));
        });

        $this->post(route('alih-media.penyeleksian.proses', $this->filelist->id), [
            'passcode_access' => $this->currentOtp(),
        ])->assertRedirect(route('alih-media.penyeleksian'));

        $this->assertNull($this->filelist->fresh()->alih_media_status_id);
    }

    public function test_database_rejects_duplicate_agenda_within_the_same_year()
    {
        $agenda = 9001;
        $this->makeIncoming(['nomor_agenda' => $agenda]);

        $this->expectException(QueryException::class);
        $this->makeIncoming(['nomor_agenda' => $agenda]);
    }

    private function makeIncoming(array $overrides = []): Incoming
    {
        return Incoming::create(array_merge([
            'nomor_agenda' => $this->nextAgenda++,
            'tanggal_diterima' => '2026-07-23',
            'nomor_surat' => 'IN/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-07-22',
            'perihal' => 'Perihal Masuk',
            'url' => 'dokumen/masuk/surat.pdf',
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
            'url' => 'dokumen/keluar/surat.pdf',
            'tahun' => 2026,
            'access_id' => $this->access->id,
            'filelist_id' => null,
        ], $overrides));
    }

    private function incomingPayload(array $overrides = []): array
    {
        return array_merge([
            'nomorAgenda' => 1,
            'tanggalDiterima' => '2026-07-23',
            'nomorSurat' => 'IN/UPDATED',
            'pengirim' => 'Pengirim Baru',
            'tanggalSurat' => '2026-07-22',
            'perihal' => 'Perihal Baru',
            'sifat' => $this->access->id,
            'pemberkasan' => 'null',
            'berkas' => $this->fakePdf('masuk-baru.pdf'),
        ], $overrides);
    }

    private function fakePdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );
    }

    private function currentOtp(): string
    {
        return (new Google2FA)->getCurrentOtp(config('services.mfa.secret'));
    }
}
