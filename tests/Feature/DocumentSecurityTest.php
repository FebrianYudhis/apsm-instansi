<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\Digital;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $publicAccess;

    private $restrictedAccess;

    private $nextAgenda = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
        Storage::fake('public');
        config([
            'services.mfa.secret' => 'JBSWY3DPEHPK3PXP',
            'documents.guest_link_minutes' => 2,
        ]);

        $this->publicAccess = Access::create(['sifat_akses' => 'Biasa']);
        $this->restrictedAccess = Access::create(['sifat_akses' => 'Terbatas']);
    }

    public function test_public_route_only_serves_public_documents_from_private_storage()
    {
        $public = $this->makeIncoming([
            'nomor_surat' => 'PUBLIK/001',
            'access_id' => $this->publicAccess->id,
            'url' => 'dokumen/masuk/publik.pdf',
        ]);
        $restricted = $this->makeIncoming([
            'nomor_surat' => 'RAHASIA/001',
            'access_id' => $this->restrictedAccess->id,
            'url' => 'dokumen/masuk/rahasia.pdf',
        ]);
        $undefined = $this->makeIncoming([
            'nomor_surat' => 'NULL/001',
            'access_id' => null,
            'url' => 'dokumen/masuk/tanpa-akses.pdf',
        ]);
        $legacyPublicOnly = $this->makeIncoming([
            'nomor_surat' => 'LEGACY/001',
            'access_id' => $this->publicAccess->id,
            'url' => 'dokumen/masuk/legacy-public.pdf',
        ]);

        Storage::disk('documents')->put($public->url, $this->pdfContent());
        Storage::disk('documents')->put($restricted->url, $this->pdfContent());
        Storage::disk('documents')->put($undefined->url, $this->pdfContent());
        Storage::disk('public')->put($legacyPublicOnly->url, $this->pdfContent());

        $this->get(route('document.public', ['jenis' => 'masuk', 'id' => $public->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('document.public', ['jenis' => 'masuk', 'id' => $restricted->id]))
            ->assertForbidden();
        $this->get(route('document.public', ['jenis' => 'masuk', 'id' => $undefined->id]))
            ->assertForbidden();
        $this->get(route('document.public', ['jenis' => 'masuk', 'id' => $legacyPublicOnly->id]))
            ->assertNotFound();
    }

    public function test_authenticated_route_serves_restricted_document_but_rejects_unsafe_path()
    {
        $user = User::factory()->create();
        $restricted = $this->makeIncoming([
            'access_id' => $this->restrictedAccess->id,
            'url' => 'dokumen/masuk/rahasia.pdf',
        ]);
        $unsafe = $this->makeIncoming([
            'access_id' => $this->restrictedAccess->id,
            'url' => '../rahasia.pdf',
        ]);
        Storage::disk('documents')->put($restricted->url, $this->pdfContent());

        $this->get(route('document.admin', [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'versi' => 'asli',
        ]))->assertRedirect(route('login'));

        $this->actingAs($user)->get(route('document.admin', [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'versi' => 'asli',
        ]))->assertOk();

        $this->actingAs($user)->get(route('document.admin', [
            'jenis' => 'masuk',
            'id' => $unsafe->id,
            'versi' => 'asli',
        ]))->assertNotFound();
    }

    public function test_mfa_returns_a_short_lived_signed_url_bound_to_the_session()
    {
        $restricted = $this->makeIncoming([
            'access_id' => $this->restrictedAccess->id,
            'url' => 'dokumen/masuk/rahasia.pdf',
        ]);
        Storage::disk('documents')->put($restricted->url, $this->pdfContent());

        $response = $this->postJson(route('guest.buka'), [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'password' => $this->currentOtp(),
        ])->assertOk()
            ->assertJsonPath('isSuccess', true);

        $temporaryUrl = $response->json('url');
        $this->assertTrue(URL::hasValidSignature(
            Request::create($temporaryUrl)
        ));

        $documentResponse = $this->get($temporaryUrl)->assertOk();
        $cacheControl = $documentResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        $signedWithoutGrant = URL::temporarySignedRoute(
            'document.temporary',
            now()->addMinutes(2),
            ['jenis' => 'masuk', 'id' => $restricted->id]
        );
        $this->app['session']->flush();
        $this->get($signedWithoutGrant)->assertForbidden();

        $this->get($temporaryUrl.'&id=999999')->assertForbidden();
    }

    public function test_guest_prefers_watermarked_pdf_for_public_and_mfa_access()
    {
        $originalContent = $this->pdfContent()."\nORIGINAL";
        $watermarkedContent = $this->pdfContent()."\nWATERMARKED";

        $public = $this->makeIncoming([
            'access_id' => $this->publicAccess->id,
            'url' => 'dokumen/masuk/publik-asli.pdf',
            'url_watermarked' => 'dokumen/alih-media/publik-watermarked.pdf',
        ]);
        $restricted = $this->makeIncoming([
            'access_id' => $this->restrictedAccess->id,
            'url' => 'dokumen/masuk/rahasia-asli.pdf',
            'url_watermarked' => 'dokumen/alih-media/rahasia-watermarked.pdf',
        ]);

        foreach ([$public, $restricted] as $document) {
            Storage::disk('documents')->put($document->url, $originalContent);
            Storage::disk('documents')->put($document->url_watermarked, $watermarkedContent);
        }

        $publicResponse = $this->get(route('document.public', [
            'jenis' => 'masuk',
            'id' => $public->id,
        ]))->assertOk();
        $this->assertSame(
            $watermarkedContent,
            file_get_contents($publicResponse->baseResponse->getFile()->getPathname())
        );

        $mfaResponse = $this->postJson(route('guest.buka'), [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'password' => $this->currentOtp(),
        ])->assertOk()
            ->assertJsonPath('isSuccess', true);

        $restrictedResponse = $this->get($mfaResponse->json('url'))->assertOk();
        $this->assertSame(
            $watermarkedContent,
            file_get_contents($restrictedResponse->baseResponse->getFile()->getPathname())
        );
    }

    public function test_invalid_mfa_and_missing_files_fail_closed()
    {
        $restricted = $this->makeIncoming([
            'access_id' => null,
            'url' => 'dokumen/masuk/hilang.pdf',
        ]);

        $this->postJson(route('guest.buka'), [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'password' => '12ab',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $invalidOtp = $this->currentOtp() === '000000' ? '000001' : '000000';
        $this->postJson(route('guest.buka'), [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'password' => $invalidOtp,
        ])->assertOk()
            ->assertJsonPath('isSuccess', false);

        $this->postJson(route('guest.buka'), [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'password' => $this->currentOtp(),
        ])->assertNotFound()
            ->assertJsonPath('isSuccess', false);
    }

    public function test_guest_search_never_returns_internal_storage_paths()
    {
        $public = $this->makeIncoming([
            'nomor_surat' => 'SEARCH-PUBLIC',
            'access_id' => $this->publicAccess->id,
            'url' => 'dokumen/masuk/publik.pdf',
        ]);
        Storage::disk('documents')->put($public->url, $this->pdfContent());

        $response = $this->get(route('guest.masuk', ['pencarian' => 'SEARCH-PUBLIC']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('data.0.document_url', route('document.public', [
                'jenis' => 'masuk',
                'id' => $public->id,
            ]));

        $json = $response->json('data.0');
        $this->assertArrayNotHasKey('url', $json);
        $this->assertArrayNotHasKey('url_watermarked', $json);
    }

    public function test_guest_search_only_returns_fields_used_by_result_cards()
    {
        $incoming = $this->makeIncoming([
            'nomor_surat' => 'WHITELIST-INCOMING',
            'url' => 'dokumen/masuk/whitelist.pdf',
        ]);
        $outgoing = Outcoming::create([
            'tanggal_surat' => '2026-07-26',
            'nomor_surat' => 'WHITELIST-OUTGOING',
            'tujuan' => 'Tujuan',
            'perihal' => 'Dokumen keluar',
            'url' => 'dokumen/keluar/whitelist.pdf',
            'tahun' => 2026,
            'is_digital' => true,
            'is_srikandi' => false,
            'access_id' => $this->publicAccess->id,
        ]);
        $digital = Digital::create([
            'perihal' => 'WHITELIST-DIGITAL',
            'url' => 'dokumen/digital/whitelist.pdf',
        ]);

        foreach ([$incoming, $outgoing, $digital] as $document) {
            Storage::disk('documents')->put($document->url, $this->pdfContent());
        }

        $incomingJson = $this->get(route('guest.masuk', [
            'pencarian' => 'WHITELIST-INCOMING',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data.0');
        $outgoingJson = $this->get(route('guest.keluar', [
            'pencarian' => 'WHITELIST-OUTGOING',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data.0');
        $digitalJson = $this->get(route('guest.digital', [
            'pencarian' => 'WHITELIST-DIGITAL',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data.0');

        $this->assertSame([
            'access_state',
            'document_url',
            'id',
            'is_srikandi',
            'nomor_agenda',
            'nomor_surat',
            'pengirim',
            'perihal',
            'requires_mfa',
            'tahun',
            'tanggal_diterima',
            'tanggal_surat',
        ], $this->sortedKeys($incomingJson));
        $this->assertSame([
            'access_state',
            'document_url',
            'id',
            'is_digital',
            'is_srikandi',
            'nomor_surat',
            'perihal',
            'requires_mfa',
            'tahun',
            'tanggal_surat',
            'tujuan',
        ], $this->sortedKeys($outgoingJson));
        $this->assertSame([
            'document_url',
            'perihal',
        ], $this->sortedKeys($digitalJson));
    }

    public function test_guest_search_paginates_all_matches_in_groups_of_fifty()
    {
        for ($number = 1; $number <= 51; $number++) {
            $this->makeIncoming([
                'nomor_surat' => 'PAGINASI-'.$number,
                'perihal' => 'Pencarian Paginasi',
            ]);
        }

        $firstPage = $this->get(route('guest.masuk', [
            'pencarian' => 'Pencarian Paginasi',
            'page' => 1,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('total', 51)
            ->assertJsonCount(50, 'data');

        $secondPage = $this->get(route('guest.masuk', [
            'pencarian' => 'Pencarian Paginasi',
            'page' => 2,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 51)
            ->assertJsonCount(1, 'data');

        $this->assertNotSame(
            $firstPage->json('data.0.id'),
            $secondPage->json('data.0.id')
        );
    }

    public function test_upload_rejects_a_pdf_extension_with_invalid_contents()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('digital.tambah'), [
            'perihal' => 'Dokumen Palsu',
            'berkas' => UploadedFile::fake()->createWithContent(
                'dokumen.pdf',
                'this is not a pdf'
            ),
        ])->assertSessionHasErrors('berkas');

        $this->assertSame(0, Digital::count());
        Storage::disk('documents')->assertMissing('dokumen/digital');
    }

    public function test_upload_rejects_a_pdf_above_the_configured_size_limit()
    {
        config(['documents.max_upload_kb' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('digital.tambah'), [
            'perihal' => 'Dokumen Terlalu Besar',
            'berkas' => UploadedFile::fake()->createWithContent(
                'dokumen.pdf',
                "%PDF-1.4\n".str_repeat('A', 2048)
            ),
        ])->assertSessionHasErrors('berkas');

        $this->assertSame(0, Digital::count());
    }

    public function test_mfa_verification_is_rate_limited()
    {
        $restricted = $this->makeIncoming([
            'access_id' => $this->restrictedAccess->id,
            'url' => 'dokumen/masuk/rahasia.pdf',
        ]);
        $invalidOtp = $this->currentOtp() === '000000' ? '000001' : '000000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('guest.buka'), [
                'jenis' => 'masuk',
                'id' => $restricted->id,
                'password' => $invalidOtp,
            ])->assertOk();
        }

        $this->postJson(route('guest.buka'), [
            'jenis' => 'masuk',
            'id' => $restricted->id,
            'password' => $invalidOtp,
        ])->assertStatus(429);
    }

    private function makeIncoming(array $overrides = []): Incoming
    {
        return Incoming::create(array_merge([
            'nomor_agenda' => $this->nextAgenda++,
            'tanggal_diterima' => '2026-07-26',
            'nomor_surat' => 'DOC/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-07-26',
            'perihal' => 'Dokumen',
            'url' => 'dokumen/masuk/dokumen.pdf',
            'tahun' => 2026,
            'is_srikandi' => false,
            'access_id' => $this->publicAccess->id,
        ], $overrides));
    }

    private function currentOtp(): string
    {
        return (new Google2FA)->getCurrentOtp(config('services.mfa.secret'));
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";
    }

    private function sortedKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);

        return $keys;
    }
}
