<?php

use App\Models\Access;
use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withActiveYear(2026)->actingAs($this->user);

    $this->activeStatus = Status::forceCreate(['id' => 1, 'nama_status' => Status::ACTIVE]);
    $this->inactiveStatus = Status::forceCreate(['id' => 3, 'nama_status' => Status::INACTIVE]);
    $this->classification = Classification::create([
        'kode_klasifikasi' => 'TU.01',
        'keterangan' => 'Tata Usaha',
    ]);
    $this->access = Access::create(['sifat_akses' => 'Biasa']);
});

test('export daftar isi berkas exports full nineteen columns', function () {
    $filelist = Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Aktif Lengkap',
        'status_id' => $this->activeStatus->id,
        'retensi_aktif' => 2,
        'retensi_inaktif' => 5,
        'keterangan_akhir' => 'Permanen',
    ]);

    Incoming::create([
        'nomor_agenda' => 1,
        'tanggal_diterima' => '2026-02-10',
        'tanggal_surat' => '2026-02-09',
        'nomor_surat' => 'IN/001',
        'pengirim' => 'Instansi Mitra',
        'perihal' => 'Kerjasama Arsip',
        'url' => 'dokumen/masuk/in001.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => $this->access->id,
        'filelist_id' => $filelist->id,
    ]);

    Outcoming::create([
        'tanggal_surat' => '2026-02-15',
        'nomor_surat' => 'OUT/001',
        'tujuan' => 'Instansi Mitra',
        'perihal' => 'Balasan Kerjasama',
        'url' => 'dokumen/keluar/out001.pdf',
        'tahun' => 2026,
        'is_digital' => false,
        'is_srikandi' => false,
        'access_id' => $this->access->id,
        'filelist_id' => $filelist->id,
    ]);

    $response = $this->get(route('surat.berkas.export', [
        'jenis_export' => 'daftar_isi_berkas',
    ]))
        ->assertOk()
        ->assertDownload();

    $temporaryPath = tempnam(sys_get_temp_dir(), 'berkas-export-');
    file_put_contents($temporaryPath, $response->streamedContent());

    try {
        $workbook = IOFactory::load($temporaryPath);
        $sheet = $workbook->getActiveSheet();

        expect($sheet->getCell('A1')->getValue())->toBe('DAFTAR ISI BERKAS')
            ->and($sheet->getCell('A7')->getValue())->toBe('Nomor Urut')
            ->and($sheet->getCell('B7')->getValue())->toBe('Kode Klasifikasi')
            ->and($sheet->getCell('C7')->getValue())->toBe('Unit Pengolah')
            ->and($sheet->getCell('D7')->getValue())->toBe('Nama Berkas')
            ->and($sheet->getCell('S7')->getValue())->toBe('SKKAD')
            ->and((int) $sheet->getCell('A8')->getValue())->toBe(1)
            ->and($sheet->getCell('B8')->getValue())->toBe('TU.01')
            ->and($sheet->getCell('D8')->getValue())->toBe('Berkas Aktif Lengkap')
            ->and((int) $sheet->getCell('E8')->getValue())->toBe(2)
            ->and((int) $sheet->getCell('J8')->getValue())->toBe(1)
            ->and($sheet->getCell('K8')->getValue())->toBe('Instansi Mitra')
            ->and($sheet->getCell('M8')->getValue())->toBe('IN/001')
            ->and((int) $sheet->getCell('J9')->getValue())->toBe(2)
            ->and($sheet->getCell('M9')->getValue())->toBe('OUT/001');

        $workbook->disconnectWorksheets();
    } finally {
        @unlink($temporaryPath);
    }
});

test('export daftar berkas summary exports nine columns', function () {
    Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Ringkasan Inaktif',
        'status_id' => $this->inactiveStatus->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 3,
        'keterangan_akhir' => 'Musnah',
    ]);

    $response = $this->get(route('surat.berkas.export', [
        'jenis_export' => 'daftar_berkas',
        'status_id' => 3,
    ]))
        ->assertOk()
        ->assertDownload();

    $temporaryPath = tempnam(sys_get_temp_dir(), 'berkas-export-');
    file_put_contents($temporaryPath, $response->streamedContent());

    try {
        $workbook = IOFactory::load($temporaryPath);
        $sheet = $workbook->getActiveSheet();

        expect($sheet->getCell('A1')->getValue())->toBe('DAFTAR BERKAS')
            ->and($sheet->getCell('C7')->getValue())->toBe('Unit Kearsipan')
            ->and($sheet->getCell('F7')->getValue())->toBe('No Box')
            ->and($sheet->getCell('I7')->getValue())->toBe('Nasib Akhir')
            ->and((int) $sheet->getCell('A8')->getValue())->toBe(1)
            ->and($sheet->getCell('B8')->getValue())->toBe('TU.01')
            ->and($sheet->getCell('D8')->getValue())->toBe('Berkas Ringkasan Inaktif')
            ->and((string) $sheet->getCell('F8')->getValue())->toBe('1')
            ->and($sheet->getCell('I8')->getValue())->toBe('Musnah');

        $workbook->disconnectWorksheets();
    } finally {
        @unlink($temporaryPath);
    }
});

test('export daftar isi berkas for proposed status exports fourteen columns', function () {
    $proposedStatus = Status::forceCreate(['id' => 2, 'nama_status' => Status::PROPOSE_TRANSFER]);

    $filelist = Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Usul Pindah',
        'status_id' => $proposedStatus->id,
        'retensi_aktif' => 2,
        'retensi_inaktif' => 5,
        'keterangan_akhir' => 'Permanen',
    ]);

    Incoming::create([
        'nomor_agenda' => 1,
        'tanggal_diterima' => '2026-02-10',
        'tanggal_surat' => '2026-02-09',
        'nomor_surat' => 'IN/002',
        'pengirim' => 'Instansi Mitra',
        'perihal' => 'Dokumen Usulan',
        'url' => 'dokumen/masuk/in002.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'access_id' => $this->access->id,
        'filelist_id' => $filelist->id,
    ]);

    $response = $this->get(route('surat.berkas.export', [
        'jenis_export' => 'daftar_isi_berkas',
        'status_id' => 2,
    ]))
        ->assertOk()
        ->assertDownload();

    $temporaryPath = tempnam(sys_get_temp_dir(), 'berkas-export-');
    file_put_contents($temporaryPath, $response->streamedContent());

    try {
        $workbook = IOFactory::load($temporaryPath);
        $sheet = $workbook->getActiveSheet();

        expect($sheet->getCell('A1')->getValue())->toBe('DAFTAR ISI BERKAS')
            ->and($sheet->getCell('A7')->getValue())->toBe('No Berkas')
            ->and($sheet->getCell('B7')->getValue())->toBe('Kode Klasifikasi')
            ->and($sheet->getCell('C7')->getValue())->toBe('Nama Berkas')
            ->and($sheet->getCell('D7')->getValue())->toBe('Status Berkas')
            ->and($sheet->getCell('H7')->getValue())->toBe('No Item')
            ->and($sheet->getCell('I7')->getValue())->toBe('Jenis Naskah')
            ->and($sheet->getCell('J7')->getValue())->toBe('Nomor Naskah')
            ->and($sheet->getCell('N7')->getValue())->toBe('SKKAD')
            ->and((int) $sheet->getCell('A8')->getValue())->toBe(1)
            ->and($sheet->getCell('B8')->getValue())->toBe('TU.01')
            ->and($sheet->getCell('C8')->getValue())->toBe('Berkas Usul Pindah')
            ->and($sheet->getCell('D8')->getValue())->toBe(Status::PROPOSE_TRANSFER)
            ->and((int) $sheet->getCell('H8')->getValue())->toBe(1)
            ->and($sheet->getCell('I8')->getValue())->toBe('Masuk')
            ->and($sheet->getCell('J8')->getValue())->toBe('IN/002')
            ->and($sheet->getCell('N8')->getValue())->toBe('Biasa');

        $workbook->disconnectWorksheets();
    } finally {
        @unlink($temporaryPath);
    }
});

test('export for proposed status resolves to fourteen columns format', function () {
    $proposeDestroy = Status::forceCreate(['id' => 4, 'nama_status' => Status::PROPOSE_DESTROY]);

    $filelist = Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Usul Musnah',
        'status_id' => $proposeDestroy->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 2,
        'keterangan_akhir' => 'Musnah',
    ]);

    $response = $this->get(route('surat.berkas.export', [
        'jenis_export' => 'daftar_berkas',
        'status_id' => 4,
    ]))
        ->assertOk()
        ->assertDownload();

    $temporaryPath = tempnam(sys_get_temp_dir(), 'berkas-export-');
    file_put_contents($temporaryPath, $response->streamedContent());

    try {
        $workbook = IOFactory::load($temporaryPath);
        $sheet = $workbook->getActiveSheet();

        expect($sheet->getCell('A1')->getValue())->toBe('DAFTAR ISI BERKAS')
            ->and($sheet->getCell('A7')->getValue())->toBe('No Berkas')
            ->and($sheet->getCell('B7')->getValue())->toBe('Kode Klasifikasi')
            ->and($sheet->getCell('C7')->getValue())->toBe('Nama Berkas')
            ->and($sheet->getCell('D7')->getValue())->toBe('Status Berkas')
            ->and($sheet->getCell('E7')->getValue())->toBe('Retensi Aktif')
            ->and($sheet->getCell('H7')->getValue())->toBe('No Item')
            ->and($sheet->getCell('N7')->getValue())->toBe('SKKAD')
            ->and((int) $sheet->getCell('A8')->getValue())->toBe(1)
            ->and($sheet->getCell('B8')->getValue())->toBe('TU.01')
            ->and($sheet->getCell('C8')->getValue())->toBe('Berkas Usul Musnah')
            ->and($sheet->getCell('D8')->getValue())->toBe(Status::PROPOSE_DESTROY);

        $workbook->disconnectWorksheets();
    } finally {
        @unlink($temporaryPath);
    }
});
