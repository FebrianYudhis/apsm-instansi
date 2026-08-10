<?php

use App\Models\AlihMediaStatus;
use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects unauthenticated requests and tokens without the surat create ability', function () {
    $this->getJson(route('api.v1.berkas.index'))
        ->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('Tanpa akses berkas', ['profil:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.berkas.index'))
        ->assertForbidden();
});

it('requires a bearer personal access token instead of a web session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.berkas.index'))
        ->assertUnauthorized();
});

it('returns every non deleted filelist with flat detail fields', function () {
    $activeStatus = Status::create(['nama_status' => Status::ACTIVE]);
    $inactiveStatus = Status::create(['nama_status' => Status::INACTIVE]);
    $alihMediaStatus = AlihMediaStatus::create(['nama_status' => 'Diproses']);
    $classification = Classification::create([
        'kode_klasifikasi' => 'TU.01',
        'keterangan' => 'Ketatausahaan',
    ]);

    $activeFilelist = Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Zeta Administrasi',
        'status_id' => $activeStatus->getKey(),
        'retensi_aktif' => null,
        'retensi_inaktif' => null,
        'keterangan_akhir' => null,
        'alih_media_status_id' => null,
    ]);
    $processedFilelist = Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Arsip Diproses',
        'status_id' => $inactiveStatus->getKey(),
        'retensi_aktif' => 2,
        'retensi_inaktif' => 3,
        'keterangan_akhir' => 'Musnah',
        'alih_media_status_id' => $alihMediaStatus->getKey(),
    ]);
    $deletedFilelist = Filelist::create([
        'classification_id' => $classification->getKey(),
        'nama_berkas' => 'Berkas Dihapus',
        'status_id' => $activeStatus->getKey(),
    ]);
    $deletedFilelist->delete();

    $user = User::factory()->create();
    $token = $user->createToken('Detail berkas', ['surat:create'])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.berkas.index'))
        ->assertSuccessful()
        ->assertExactJson([
            'data' => [
                [
                    'id' => $processedFilelist->getKey(),
                    'kode_klasifikasi' => 'TU.01',
                    'keterangan_klasifikasi' => 'Ketatausahaan',
                    'nama_berkas' => 'Arsip Diproses',
                    'status' => Status::INACTIVE,
                    'retensi_aktif' => 2,
                    'retensi_inaktif' => 3,
                    'keterangan_akhir' => 'Musnah',
                    'status_alih_media' => 'Diproses',
                ],
                [
                    'id' => $activeFilelist->getKey(),
                    'kode_klasifikasi' => 'TU.01',
                    'keterangan_klasifikasi' => 'Ketatausahaan',
                    'nama_berkas' => 'Zeta Administrasi',
                    'status' => Status::ACTIVE,
                    'retensi_aktif' => null,
                    'retensi_inaktif' => null,
                    'keterangan_akhir' => null,
                    'status_alih_media' => null,
                ],
            ],
        ]);
});
