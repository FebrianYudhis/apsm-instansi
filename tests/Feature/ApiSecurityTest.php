<?php

use App\Models\Access;
use App\Models\AlihMediaStatus;
use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('rejects expired and revoked personal access tokens', function () {
    $user = User::factory()->create();
    $expiredToken = $user
        ->createToken(
            'Kedaluwarsa',
            ['surat:create'],
            now()->subMinute()
        )
        ->plainTextToken;

    $this->withToken($expiredToken)
        ->getJson(route('api.v1.me'))
        ->assertUnauthorized();

    $revokedToken = $user->createToken('Dicabut', ['surat:create']);
    $plainTextToken = $revokedToken->plainTextToken;
    $revokedToken->accessToken->delete();

    $this->withToken($plainTextToken)
        ->getJson(route('api.v1.me'))
        ->assertUnauthorized();
});

it('rejects invalid booleans foreign keys and years without storing a document', function (string $letterType) {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $token = $user->createToken('Validasi Dasar', ['surat:create'])->plainTextToken;

    $invalidInputs = [
        'boolean tidak valid' => ['is_srikandi' => 'bukan-boolean'],
        'sifat akses tidak ada' => ['access_id' => PHP_INT_MAX],
        'tahun masa depan' => ['tahun' => now()->addYear()->year],
    ];

    foreach ($invalidInputs as $expectedField => $overrides) {
        $field = match ($expectedField) {
            'boolean tidak valid' => 'is_srikandi',
            'sifat akses tidak ada' => 'access_id',
            'tahun masa depan' => 'tahun',
        };

        $this->withToken($token)
            ->post(
                securityApiRoute($letterType),
                securityApiPayload($letterType, $access, $overrides),
                ['Accept' => 'application/json']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    expect(securityApiModel($letterType)::query()->count())->toBe(0)
        ->and(Storage::disk('documents')->allFiles())->toBeEmpty();
})->with(['incoming', 'outgoing']);

it('rejects inactive locked and deleted filelists', function (string $letterType) {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $activeStatus = Status::create(['nama_status' => Status::ACTIVE]);
    $inactiveStatus = Status::create(['nama_status' => Status::INACTIVE]);
    $alihMediaStatus = AlihMediaStatus::create(['nama_status' => 'Diproses']);
    $classification = Classification::create([
        'kode_klasifikasi' => 'SEC.01',
        'keterangan' => 'Pengujian keamanan API',
    ]);
    $inactiveFilelist = Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Berkas Inaktif',
        'status_id' => $inactiveStatus->getKey(),
    ]);
    $lockedFilelist = Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Berkas Alih Media',
        'status_id' => $activeStatus->getKey(),
        'alih_media_status_id' => $alihMediaStatus->getKey(),
    ]);
    $deletedFilelist = Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Berkas Dihapus',
        'status_id' => $activeStatus->getKey(),
    ]);
    $deletedFilelist->delete();
    $user = User::factory()->create();
    $token = $user->createToken('Validasi Berkas', ['surat:create'])->plainTextToken;

    foreach ([$inactiveFilelist, $lockedFilelist, $deletedFilelist] as $filelist) {
        $this->withToken($token)
            ->post(
                securityApiRoute($letterType),
                securityApiPayload($letterType, $access, [
                    'filelist_id' => $filelist->getKey(),
                ]),
                ['Accept' => 'application/json']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filelist_id');
    }

    expect(securityApiModel($letterType)::query()->count())->toBe(0)
        ->and(Storage::disk('documents')->allFiles())->toBeEmpty();
})->with(['incoming', 'outgoing']);

it('ignores unvalidated sensitive attributes and hides storage metadata from responses', function (string $letterType) {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $newToken = $user->createToken('Mass Assignment', ['surat:create']);
    $maliciousPath = '../../public/dokumen-disusupi.pdf';

    $response = $this->withToken($newToken->plainTextToken)
        ->post(
            securityApiRoute($letterType),
            securityApiPayload($letterType, $access, [
                'url' => $maliciousPath,
                'url_watermarked' => $maliciousPath,
                'deleted_by_user_id' => PHP_INT_MAX,
                'deletion_reason' => 'Tidak boleh diterima dari API',
            ]),
            ['Accept' => 'application/json']
        );

    $response
        ->assertCreated()
        ->assertJsonMissingPaths([
            'data.url',
            'data.url_watermarked',
            'data.deleted_by_user_id',
            'data.deletion_reason',
        ]);

    $document = securityApiModel($letterType)::findOrFail(
        $response->json('data.id')
    );
    $activity = Activity::query()
        ->where('subject_type', $document::class)
        ->where('subject_id', $document->getKey())
        ->where('event', 'created')
        ->firstOrFail();

    expect($document->url)
        ->toStartWith('dokumen/'.securityApiStorageDirectory($letterType).'/')
        ->not->toContain('..')
        ->and($document->url_watermarked)->toBeNull()
        ->and($document->deleted_by_user_id)->toBeNull()
        ->and($document->deletion_reason)->toBeNull()
        ->and($activity->properties->toJson())->not->toContain($maliciousPath)
        ->and($activity->properties->toJson())
        ->not->toContain($newToken->plainTextToken);
})->with(['incoming', 'outgoing']);

it('rejects spoofed and oversized PDF files without leaving database or storage residue', function (
    string $letterType,
    string $fileCase
) {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $token = $user->createToken('Validasi PDF', ['surat:create'])->plainTextToken;

    if ($fileCase === 'oversized') {
        config()->set('documents.max_upload_kb', 1);
        $file = UploadedFile::fake()->createWithContent(
            'terlalu-besar.pdf',
            "%PDF-1.4\n".str_repeat('A', 2048)."\n%%EOF"
        );
    } else {
        $file = UploadedFile::fake()->createWithContent(
            'pdf-palsu.pdf',
            'Konten ini bukan PDF.'
        );
    }

    $this->withToken($token)
        ->post(
            securityApiRoute($letterType),
            securityApiPayload($letterType, $access, ['berkas' => $file]),
            ['Accept' => 'application/json']
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('berkas');

    expect(securityApiModel($letterType)::query()->count())->toBe(0)
        ->and(Storage::disk('documents')->allFiles())->toBeEmpty();
})->with(['incoming', 'outgoing'])->with(['spoofed', 'oversized']);

it('rejects duplicate incoming agenda numbers in the same year without leaving an extra file', function () {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $token = $user->createToken('Agenda Unik', ['surat:create'])->plainTextToken;
    $payload = securityApiPayload('incoming', $access, [
        'nomor_agenda' => 777,
    ]);

    $this->withToken($token)
        ->post(
            route('api.v1.surat.masuk.store'),
            $payload,
            ['Accept' => 'application/json']
        )
        ->assertCreated();

    $this->withToken($token)
        ->post(
            route('api.v1.surat.masuk.store'),
            securityApiPayload('incoming', $access, [
                'nomor_agenda' => 777,
                'nomor_surat' => 'API/SEC/DUPLIKAT',
            ]),
            ['Accept' => 'application/json']
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nomor_agenda');

    expect(Incoming::query()->count())->toBe(1)
        ->and(Storage::disk('documents')->allFiles())->toHaveCount(1);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function securityApiPayload(
    string $letterType,
    Access $access,
    array $overrides = []
): array {
    $payload = $letterType === 'incoming'
        ? [
            'is_srikandi' => false,
            'nomor_agenda' => 501,
            'tanggal_diterima' => now()->toDateString(),
            'tanggal_surat' => now()->toDateString(),
            'nomor_surat' => 'API/SEC/MASUK',
            'pengirim' => 'Pengirim Pengujian',
            'perihal' => 'Audit keamanan API',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'berkas' => securityApiPdf('audit-masuk.pdf'),
        ]
        : [
            'is_srikandi' => false,
            'is_digital' => false,
            'tanggal_surat' => now()->toDateString(),
            'nomor_surat' => 'API/SEC/KELUAR',
            'tujuan' => 'Tujuan Pengujian',
            'perihal' => 'Audit keamanan API',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'berkas' => securityApiPdf('audit-keluar.pdf'),
        ];

    return array_replace($payload, $overrides);
}

function securityApiPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}

function securityApiRoute(string $letterType): string
{
    return route(
        $letterType === 'incoming'
            ? 'api.v1.surat.masuk.store'
            : 'api.v1.surat.keluar.store'
    );
}

/**
 * @return class-string<Incoming|Outcoming>
 */
function securityApiModel(string $letterType): string
{
    return $letterType === 'incoming' ? Incoming::class : Outcoming::class;
}

function securityApiStorageDirectory(string $letterType): string
{
    return $letterType === 'incoming' ? 'masuk' : 'keluar';
}
