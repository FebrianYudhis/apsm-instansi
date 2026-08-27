<?php

use App\Models\Access;
use App\Models\Incoming;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->access = Access::create([
        'id' => 1,
        'sifat_akses' => 'Biasa',
    ]);
});

test('guests cannot check agenda number availability', function () {
    $this->getJson(route('masuk.cek-agenda', ['nomor_agenda' => 1]))
        ->assertUnauthorized();
});

test('it returns available true when agenda number is unused in active year', function () {
    $response = $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda', ['nomor_agenda' => 10]))
        ->assertSuccessful();

    $response->assertJson([
        'available' => true,
    ]);
});

test('it returns available false with letter details when agenda number is already used in active year', function () {
    $surat = Incoming::create([
        'nomor_agenda' => 15,
        'tanggal_diterima' => '2026-08-12',
        'nomor_surat' => '001/BMKG/VIII/2026',
        'pengirim' => 'Stasiun Meteorologi',
        'tanggal_surat' => '2026-08-10',
        'perihal' => 'Laporan Iklim Bulanan',
        'url' => 'dokumen/masuk/laporan.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => 1,
    ]);

    $response = $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda', ['nomor_agenda' => 15]))
        ->assertSuccessful();

    $response->assertJson([
        'available' => false,
        'data' => [
            'id' => $surat->id,
            'nomor_agenda' => 15,
            'nomor_surat' => '001/BMKG/VIII/2026',
            'pengirim' => 'Stasiun Meteorologi',
            'perihal' => 'Laporan Iklim Bulanan',
            'is_deleted' => false,
            'detail_url' => route('surat.detailItem', ['masuk', $surat->id]),
        ],
    ]);
});

test('it returns available false with soft deleted notice when letter is in trash', function () {
    $surat = Incoming::create([
        'nomor_agenda' => 20,
        'tanggal_diterima' => '2026-08-12',
        'nomor_surat' => '002/BMKG/VIII/2026',
        'pengirim' => 'BMKG Pusat',
        'tanggal_surat' => '2026-08-10',
        'perihal' => 'Surat yang sudah dihapus',
        'url' => 'dokumen/masuk/surat-hapus.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => 1,
    ]);

    $surat->delete();

    $response = $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda', ['nomor_agenda' => 20]))
        ->assertSuccessful();

    $response->assertJson([
        'available' => false,
        'data' => [
            'id' => $surat->id,
            'nomor_agenda' => 20,
            'is_deleted' => true,
            'detail_url' => null,
        ],
    ]);
    expect($response->json('message'))->toContain('tempat sampah');
});

test('it ignores current letter id in edit mode', function () {
    $surat = Incoming::create([
        'nomor_agenda' => 25,
        'tanggal_diterima' => '2026-08-12',
        'nomor_surat' => '003/BMKG/VIII/2026',
        'pengirim' => 'Stasiun Klimatologi',
        'tanggal_surat' => '2026-08-10',
        'perihal' => 'Prakiraan Cuaca',
        'url' => 'dokumen/masuk/cuaca.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => 1,
    ]);

    // Checking same agenda number with ignore_id = surat id -> available
    $responseSelf = $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda', [
            'nomor_agenda' => 25,
            'ignore_id' => $surat->id,
        ]))
        ->assertSuccessful();

    $responseSelf->assertJson(['available' => true]);

    // Another surat exists
    $otherSurat = Incoming::create([
        'nomor_agenda' => 26,
        'tanggal_diterima' => '2026-08-12',
        'nomor_surat' => '004/BMKG/VIII/2026',
        'pengirim' => 'Stasiun Geofisika',
        'tanggal_surat' => '2026-08-10',
        'perihal' => 'Prakiraan Gempa',
        'url' => 'dokumen/masuk/gempa.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => 1,
    ]);

    // Checking agenda 26 with ignore_id = first surat id -> unavailable (taken by otherSurat)
    $responseOther = $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda', [
            'nomor_agenda' => 26,
            'ignore_id' => $surat->id,
        ]))
        ->assertSuccessful();

    $responseOther->assertJson(['available' => false]);
});

test('agenda numbers do not conflict across different years', function () {
    Incoming::create([
        'nomor_agenda' => 30,
        'tanggal_diterima' => '2025-08-12',
        'nomor_surat' => '005/BMKG/VIII/2025',
        'pengirim' => 'Stasiun',
        'tanggal_surat' => '2025-08-10',
        'perihal' => 'Surat Tahun Lalu',
        'url' => 'dokumen/masuk/tahun-lalu.pdf',
        'tahun' => 2025,
        'is_srikandi' => false,
        'access_id' => 1,
    ]);

    // In year 2026, agenda 30 is free
    $response = $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda', ['nomor_agenda' => 30]))
        ->assertSuccessful();

    $response->assertJson(['available' => true]);
});

test('validates nomor_agenda parameter', function () {
    $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda'))
        ->assertJsonValidationErrors(['nomor_agenda']);

    $this->withActiveYear(2026)
        ->actingAs($this->user)
        ->getJson(route('masuk.cek-agenda', ['nomor_agenda' => -1]))
        ->assertJsonValidationErrors(['nomor_agenda']);
});
