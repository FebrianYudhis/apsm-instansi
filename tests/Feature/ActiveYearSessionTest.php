<?php

use App\Models\Access;
use App\Models\Incoming;
use App\Models\User;
use App\Services\ActiveYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.start_year' => 2025]);
    Storage::fake('documents');
});

test('login stores the selected active year in the session', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'username' => $user->username,
        'password' => 'password',
        'tahun' => 2025,
    ])->assertRedirect('/app');

    $this->assertAuthenticatedAs($user);
    $this->assertSame(2025, session(ActiveYear::SESSION_KEY));
    $this->assertFalse(Schema::hasColumn('users', 'tahun'));
});

test('the active year session controls dashboard data and newly stored letters', function () {
    $user = User::factory()->create();
    $access = Access::create(['sifat_akses' => 'Biasa']);

    Incoming::create([
        'nomor_agenda' => 1,
        'tanggal_diterima' => '2026-01-02',
        'nomor_surat' => 'TAHUN/2026',
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-01-01',
        'perihal' => 'Surat tahun lain',
        'url' => 'dokumen/masuk/tahun-2026.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => $access->id,
    ]);

    $this->withActiveYear(2025)->actingAs($user);

    $this->get('/app')
        ->assertOk()
        ->assertViewHas('tahun', 2025)
        ->assertViewHas('suratMasuk', 0);

    $this->post(route('masuk.tambah'), [
        'isSrikandi' => 0,
        'nomorAgenda' => 1,
        'tanggalDiterima' => '2025-02-02',
        'tanggalSurat' => '2025-02-01',
        'nomorSurat' => 'TAHUN/2025',
        'pengirim' => 'Pengirim',
        'perihal' => 'Mengikuti tahun aktif',
        'sifat' => $access->id,
        'pemberkasan' => '',
        'berkas' => UploadedFile::fake()->createWithContent(
            'surat.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        ),
    ])->assertRedirect(route('surat.masuk'));

    $this->assertDatabaseHas('incomings', [
        'nomor_surat' => 'TAHUN/2025',
        'tahun' => 2025,
    ]);

    $this->get('/app')
        ->assertOk()
        ->assertViewHas('suratMasuk', 1);
});
