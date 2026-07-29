<?php

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
    $this->status = Status::create([
        'id' => 1,
        'nama_status' => Status::ACTIVE,
    ]);
    $this->classification = Classification::create([
        'kode_klasifikasi' => 'TU.01',
        'keterangan' => 'Tata Usaha',
    ]);

    $this->withActiveYear(2026)->actingAs($this->user);
});

test('dashboard attention links open scoped data', function () {
    Incoming::create([
        'nomor_agenda' => 1,
        'tanggal_diterima' => '2026-07-20',
        'nomor_surat' => 'IN-PENDING',
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-07-19',
        'perihal' => 'Surat masuk belum diberkaskan',
        'url' => 'dokumen/masuk/in-pending.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
    ]);
    Outcoming::create([
        'tanggal_surat' => '2026-07-20',
        'nomor_surat' => 'OUT-PENDING',
        'tujuan' => 'Tujuan',
        'perihal' => 'Surat keluar belum diberkaskan',
        'url' => 'dokumen/keluar/out-pending.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
    ]);
    Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Kosong',
        'status_id' => $this->status->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('suratBelumBerkas', 2)
        ->assertViewHas('berkasTanpaIsi', 1)
        ->assertSee(route('surat.belum-diberkaskan'), false)
        ->assertSee(route('surat.berkas', ['isi' => 'kosong']), false);
});

test('pending filing page only returns matching letters from the active year', function () {
    $filelist = Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Terisi',
        'status_id' => $this->status->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
    ]);

    Incoming::create([
        'nomor_agenda' => 1,
        'tanggal_diterima' => '2026-07-20',
        'nomor_surat' => 'IN-PENDING',
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-07-19',
        'perihal' => 'Surat masuk belum diberkaskan',
        'url' => 'dokumen/masuk/in-pending.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
    ]);
    Outcoming::create([
        'tanggal_surat' => '2026-07-20',
        'nomor_surat' => 'OUT-PENDING',
        'tujuan' => 'Tujuan',
        'perihal' => 'Surat keluar belum diberkaskan',
        'url' => 'dokumen/keluar/out-pending.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
    ]);
    Incoming::create([
        'nomor_agenda' => 2,
        'tanggal_diterima' => '2026-07-20',
        'nomor_surat' => 'IN-FILED',
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-07-19',
        'perihal' => 'Sudah diberkaskan',
        'url' => 'dokumen/masuk/in-filed.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'filelist_id' => $filelist->id,
    ]);
    Incoming::create([
        'tanggal_diterima' => '2026-07-20',
        'nomor_surat' => 'IN-SRIKANDI',
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-07-19',
        'perihal' => 'Surat SRIKANDI',
        'url' => 'dokumen/masuk/in-srikandi.pdf',
        'tahun' => 2026,
        'is_srikandi' => true,
    ]);
    Outcoming::create([
        'tanggal_surat' => '2025-07-20',
        'nomor_surat' => 'OUT-OTHER-YEAR',
        'tujuan' => 'Tujuan',
        'perihal' => 'Tahun lain',
        'url' => 'dokumen/keluar/out-other-year.pdf',
        'tahun' => 2025,
        'is_srikandi' => false,
    ]);

    $this->get(
        route('surat.belum-diberkaskan'),
        ['X-Requested-With' => 'XMLHttpRequest']
    )
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 2)
        ->assertJsonCount(2, 'data')
        ->assertSee('IN-PENDING')
        ->assertSee('OUT-PENDING')
        ->assertDontSee('IN-FILED')
        ->assertDontSee('IN-SRIKANDI')
        ->assertDontSee('OUT-OTHER-YEAR');
});

test('empty contents filter only returns filelists without active letters', function () {
    $emptyFilelist = Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Kosong',
        'status_id' => $this->status->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
    ]);
    $filledFilelist = Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Terisi',
        'status_id' => $this->status->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
    ]);
    Incoming::create([
        'nomor_agenda' => 1,
        'tanggal_diterima' => '2026-07-20',
        'nomor_surat' => 'IN-FILED',
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-07-19',
        'perihal' => 'Isi berkas',
        'url' => 'dokumen/masuk/in-filed.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'filelist_id' => $filledFilelist->id,
    ]);

    $this->get(
        route('surat.berkas', ['isi' => 'kosong']),
        ['X-Requested-With' => 'XMLHttpRequest']
    )
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $emptyFilelist->id)
        ->assertSee('Berkas Kosong')
        ->assertDontSee('Berkas Terisi');
});

test('empty contents filter is preserved when exporting filelists', function () {
    Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Kosong',
        'status_id' => $this->status->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
    ]);
    $filledFilelist = Filelist::create([
        'classification_id' => $this->classification->id,
        'nama_berkas' => 'Berkas Terisi',
        'status_id' => $this->status->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
    ]);
    Incoming::create([
        'nomor_agenda' => 1,
        'tanggal_diterima' => '2026-07-20',
        'nomor_surat' => 'IN-FILED',
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-07-19',
        'perihal' => 'Isi berkas',
        'url' => 'dokumen/masuk/in-filed.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
        'filelist_id' => $filledFilelist->id,
    ]);

    $response = $this->get(route('surat.berkas.export', [
        'jenis_export' => 'daftar_berkas',
        'isi' => 'kosong',
    ]))->assertOk();

    $temporaryFile = tempnam(sys_get_temp_dir(), 'apsm-export-');
    file_put_contents($temporaryFile, $response->streamedContent());
    $exportedRows = IOFactory::load($temporaryFile)
        ->getActiveSheet()
        ->toArray();
    unlink($temporaryFile);

    $exportedContent = json_encode($exportedRows);

    expect($exportedContent)
        ->toContain('Berkas Kosong')
        ->not->toContain('Berkas Terisi');
});
