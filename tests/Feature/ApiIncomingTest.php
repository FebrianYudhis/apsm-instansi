<?php

use App\Models\Access;
use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('rejects unauthenticated and unauthorized incoming API requests', function () {
    $this->postJson(route('api.v1.surat.masuk.store'))
        ->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('Tanpa akses', ['profil:read'])->plainTextToken;

    $this->withToken($token)
        ->postJson(route('api.v1.surat.masuk.store'))
        ->assertForbidden();
});

it('requires a bearer personal access token instead of a web session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.me'))
        ->assertUnauthorized();
});

it('creates an incoming letter and records the token owner in activity log', function () {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $newToken = $user->createToken('SRIKANDI Masuk', ['surat:create']);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->withToken($newToken->plainTextToken)
        ->post(route('api.v1.surat.masuk.store'), [
            'is_srikandi' => true,
            'tanggal_diterima' => now()->toDateString(),
            'tanggal_surat' => now()->subDay()->toDateString(),
            'nomor_surat' => 'SRK/M/001',
            'pengirim' => 'SRIKANDI',
            'perihal' => 'Pengujian API surat masuk',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'berkas' => validApiPdf('surat-masuk.pdf'),
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.is_srikandi', true)
        ->assertJsonPath('data.nomor_agenda', null)
        ->assertJsonPath('data.filelist_id', null);

    $incoming = Incoming::findOrFail($response->json('data.id'));
    Storage::disk('documents')->assertExists($incoming->url);

    $activity = Activity::query()
        ->where('subject_type', Incoming::class)
        ->where('subject_id', $incoming->getKey())
        ->where('event', 'created')
        ->firstOrFail();

    expect($activity->causer_id)
        ->toBe($user->getKey())
        ->and($activity->properties->get('channel'))->toBe('api')
        ->and(data_get($activity->properties, 'api_token.id'))
        ->toBe($newToken->accessToken->getKey())
        ->and(data_get($activity->properties, 'api_token.name'))
        ->toBe('SRIKANDI Masuk')
        ->and($activity->properties->toJson())
        ->not->toContain($newToken->plainTextToken);
});

it('rejects non SRIKANDI fields on a SRIKANDI incoming letter', function () {
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $token = $user->createToken('Validasi Masuk', ['surat:create'])->plainTextToken;

    $this
        ->withHeader('Accept', 'application/json')
        ->withToken($token)
        ->post(route('api.v1.surat.masuk.store'), [
            'is_srikandi' => true,
            'nomor_agenda' => 999,
            'tanggal_diterima' => now()->toDateString(),
            'nomor_surat' => 'SRK/M/INVALID',
            'pengirim' => 'SRIKANDI',
            'perihal' => 'Payload kontradiktif',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'filelist_id' => 999,
            'berkas' => validApiPdf('surat-masuk-invalid.pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nomor_agenda', 'filelist_id'])
        ->assertJsonPath(
            'errors.nomor_agenda.0',
            'Nomor agenda tidak boleh dikirim untuk surat SRIKANDI.'
        )
        ->assertJsonPath(
            'errors.filelist_id.0',
            'Berkas tujuan tidak boleh dikirim untuk surat SRIKANDI.'
        );

    expect(Incoming::query()->count())->toBe(0);
});

it('requires an agenda number for non SRIKANDI incoming letters', function () {
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $token = $user->createToken('Manual Masuk', ['surat:create'])->plainTextToken;

    $this
        ->withHeader('Accept', 'application/json')
        ->withToken($token)
        ->post(route('api.v1.surat.masuk.store'), [
            'is_srikandi' => false,
            'tanggal_diterima' => now()->toDateString(),
            'nomor_surat' => 'MAN/M/001',
            'pengirim' => 'Pengirim',
            'perihal' => 'Tanpa agenda',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'berkas' => validApiPdf('surat-masuk.pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nomor_agenda');
});

it('returns reference data needed to create a letter', function () {
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $activeStatus = Status::create(['nama_status' => Status::ACTIVE]);
    $inactiveStatus = Status::create(['nama_status' => Status::INACTIVE]);
    $classification = Classification::create([
        'kode_klasifikasi' => 'API.01',
        'keterangan' => 'Integrasi API',
    ]);
    $activeFilelist = Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Berkas Aktif API',
        'status_id' => $activeStatus->getKey(),
    ]);
    Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Berkas Inaktif API',
        'status_id' => $inactiveStatus->getKey(),
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('Referensi', ['surat:create'])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.references.accesses'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $access->getKey())
        ->assertJsonPath('data.0.name', 'Biasa');

    $this->withToken($token)
        ->getJson(route('api.v1.references.active-filelists'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $activeFilelist->getKey())
        ->assertJsonPath('data.0.classification.code', 'API.01');
});

it('checks agenda number availability via API and returns true when free', function () {
    $user = User::factory()->create();
    $token = $user->createToken('API Check', ['surat:create'])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.surat.masuk.cek-agenda', ['nomor_agenda' => 77, 'tahun' => 2026]))
        ->assertSuccessful()
        ->assertJson([
            'available' => true,
        ]);
});

it('returns false with details when agenda number is used via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('API Check', ['surat:create'])->plainTextToken;
    $access = Access::create(['sifat_akses' => 'Biasa']);

    $surat = Incoming::create([
        'nomor_agenda' => 88,
        'tanggal_diterima' => '2026-08-12',
        'nomor_surat' => 'API-001/2026',
        'pengirim' => 'Pusat Data',
        'tanggal_surat' => '2026-08-10',
        'perihal' => 'Pengujian API Check',
        'url' => 'dokumen/masuk/api-test.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => $access->getKey(),
    ]);

    $this->withToken($token)
        ->getJson(route('api.v1.surat.masuk.cek-agenda', ['nomor_agenda' => 88, 'tahun' => 2026]))
        ->assertSuccessful()
        ->assertJson([
            'available' => false,
            'data' => [
                'id' => $surat->id,
                'nomor_agenda' => 88,
                'nomor_surat' => 'API-001/2026',
                'pengirim' => 'Pusat Data',
                'perihal' => 'Pengujian API Check',
                'is_deleted' => false,
                'detail_url' => route('surat.detailItem', ['masuk', $surat->id]),
            ],
        ]);

    $surat->delete();

    $this->withToken($token)
        ->getJson(route('api.v1.surat.masuk.cek-agenda', ['nomor_agenda' => 88, 'tahun' => 2026]))
        ->assertSuccessful()
        ->assertJson([
            'available' => false,
            'data' => [
                'id' => $surat->id,
                'is_deleted' => true,
                'detail_url' => null,
            ],
        ]);
});

it('validates agenda number and year on API check', function () {
    $user = User::factory()->create();
    $token = $user->createToken('API Check', ['surat:create'])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.surat.masuk.cek-agenda'))
        ->assertJsonValidationErrors(['nomor_agenda']);

    $this->withToken($token)
        ->getJson(route('api.v1.surat.masuk.cek-agenda', ['nomor_agenda' => 1, 'tahun' => 2000]))
        ->assertJsonValidationErrors(['tahun']);
});

function validApiPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}
