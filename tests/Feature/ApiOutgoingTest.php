<?php

use App\Models\Access;
use App\Models\Outcoming;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('creates a SRIKANDI outgoing letter as digital and records the token owner', function () {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $newToken = $user->createToken('SRIKANDI Keluar', ['surat:create']);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->withToken($newToken->plainTextToken)
        ->post(route('api.v1.surat.keluar.store'), [
            'is_srikandi' => true,
            'tanggal_surat' => now()->toDateString(),
            'nomor_surat' => 'SRK/K/001',
            'tujuan' => 'Tujuan Surat',
            'perihal' => 'Pengujian API surat keluar',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'berkas' => outgoingApiPdf('surat-keluar.pdf'),
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.is_srikandi', true)
        ->assertJsonPath('data.is_digital', true)
        ->assertJsonPath('data.filelist_id', null);

    $outgoing = Outcoming::findOrFail($response->json('data.id'));
    Storage::disk('documents')->assertExists($outgoing->url);

    $activity = Activity::query()
        ->where('subject_type', Outcoming::class)
        ->where('subject_id', $outgoing->getKey())
        ->where('event', 'created')
        ->firstOrFail();

    expect($activity->causer_id)
        ->toBe($user->getKey())
        ->and($activity->properties->get('channel'))->toBe('api')
        ->and(data_get($activity->properties, 'api_token.name'))
        ->toBe('SRIKANDI Keluar')
        ->and($activity->properties->toJson())
        ->not->toContain($newToken->plainTextToken);
});

it('rejects non SRIKANDI fields on a SRIKANDI outgoing letter', function () {
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $token = $user->createToken('Validasi Keluar', ['surat:create'])->plainTextToken;

    $this
        ->withHeader('Accept', 'application/json')
        ->withToken($token)
        ->post(route('api.v1.surat.keluar.store'), [
            'is_srikandi' => true,
            'is_digital' => false,
            'tanggal_surat' => now()->toDateString(),
            'nomor_surat' => 'SRK/K/INVALID',
            'tujuan' => 'Tujuan Surat',
            'perihal' => 'Payload kontradiktif',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'filelist_id' => 999,
            'berkas' => outgoingApiPdf('surat-keluar-invalid.pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_digital', 'filelist_id'])
        ->assertJsonPath(
            'errors.is_digital.0',
            'Status digital tidak boleh dikirim untuk surat SRIKANDI karena selalu ditetapkan digital.'
        )
        ->assertJsonPath(
            'errors.filelist_id.0',
            'Berkas tujuan tidak boleh dikirim untuk surat SRIKANDI.'
        );

    expect(Outcoming::query()->count())->toBe(0);
});

it('keeps a non SRIKANDI outgoing letter manual when requested', function () {
    Storage::fake('documents');
    $access = Access::create(['sifat_akses' => 'Biasa']);
    $user = User::factory()->create();
    $token = $user->createToken('Manual Keluar', ['surat:create'])->plainTextToken;

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->withToken($token)
        ->post(route('api.v1.surat.keluar.store'), [
            'is_srikandi' => false,
            'is_digital' => false,
            'tanggal_surat' => now()->toDateString(),
            'nomor_surat' => 'MAN/K/001',
            'tujuan' => 'Tujuan Surat',
            'perihal' => 'Surat manual',
            'tahun' => now()->year,
            'access_id' => $access->getKey(),
            'berkas' => outgoingApiPdf('surat-keluar-manual.pdf'),
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.is_srikandi', false)
        ->assertJsonPath('data.is_digital', false);
});

function outgoingApiPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}
