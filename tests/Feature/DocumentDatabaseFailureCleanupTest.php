<?php

use App\Models\Access;
use App\Models\User;
use App\Services\FilelistMutationLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('documents');

    $this->access = Access::create(['sifat_akses' => 'Biasa']);
    $this->withActiveYear(2026)->actingAs(User::factory()->create());
    $this->withoutExceptionHandling();

    $this->mock(FilelistMutationLock::class, function ($mock) {
        $mock->shouldReceive('lock')
            ->once()
            ->andThrow(new RuntimeException('Database transaction failed'));
    });
});

test('a newly uploaded incoming document is deleted when its database transaction fails', function () {
    try {
        $this->post(route('masuk.tambah'), incomingPayload($this->access->id));
        $this->fail('The simulated database failure was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Database transaction failed');
    }

    expect(Storage::disk('documents')->allFiles('dokumen/masuk'))->toBeEmpty();
});

test('a newly uploaded outgoing document is deleted when its database transaction fails', function () {
    try {
        $this->post(route('keluar.tambah'), outgoingPayload($this->access->id));
        $this->fail('The simulated database failure was not thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Database transaction failed');
    }

    expect(Storage::disk('documents')->allFiles('dokumen/keluar'))->toBeEmpty();
});

function incomingPayload(int $accessId): array
{
    return [
        'isSrikandi' => 0,
        'nomorAgenda' => 1,
        'tanggalDiterima' => '2026-07-23',
        'tanggalSurat' => '2026-07-22',
        'nomorSurat' => 'IN/FAIL',
        'pengirim' => 'Pengirim',
        'perihal' => 'Simulasi gagal database',
        'sifat' => $accessId,
        'pemberkasan' => '',
        'berkas' => fakePdf('incoming.pdf'),
    ];
}

function outgoingPayload(int $accessId): array
{
    return [
        'jenis' => 0,
        'tanggalSurat' => '2026-07-23',
        'nomorSurat' => 'OUT/FAIL',
        'tujuan' => 'Tujuan',
        'perihal' => 'Simulasi gagal database',
        'sifat' => $accessId,
        'pemberkasan' => '',
        'berkas' => fakePdf('outgoing.pdf'),
    ];
}

function fakePdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}
