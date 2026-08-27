<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AlihMediaController;
use App\Http\Controllers\AlihMediaExportController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BerkasContentController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\BerkasExportController;
use App\Http\Controllers\BerkasStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\KlasifikasiController;
use App\Http\Controllers\KlasifikasiExportController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\PersonalAccessTokenController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Surat\SuratDigitalController;
use App\Http\Controllers\Surat\SuratKeluarController;
use App\Http\Controllers\Surat\SuratMasukController;
use App\Http\Controllers\SuratListController;
use App\Http\Controllers\YearController;
use App\Http\Middleware\EnsureActiveYear;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'showLoginForm']);

Auth::routes(['register' => false, 'reset' => false]);

Route::middleware(['auth', EnsureActiveYear::class])->group(function () {
    Route::get('dokumen/{jenis}/{id}/{versi?}/{nama?}', [DocumentController::class, 'admin'])
        ->name('document.admin')
        ->where([
            'jenis' => 'masuk|keluar|digital',
            'id' => '[0-9]+',
            'versi' => 'tampil|asli|watermark',
            'nama' => '[A-Za-z0-9-]+\.pdf',
        ]);

    Route::get('aktivitas/ringkasan', [ActivityLogController::class, 'ringkasan'])->name('activity-log.ringkasan');
    Route::get('log-aktivitas', [ActivityLogController::class, 'index'])->name('activity-log');

    Route::get('profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::post('profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::get('token-api', [PersonalAccessTokenController::class, 'index'])
        ->middleware(SetCacheHeaders::using([
            'private' => true,
            'no_store' => true,
            'no_cache' => true,
            'must_revalidate' => true,
            'max_age' => 0,
        ]))
        ->name('api-tokens.index');
    Route::post('token-api', [PersonalAccessTokenController::class, 'store'])
        ->middleware('throttle:api-token-management')
        ->name('api-tokens.store');
    Route::delete('token-api/{token}', [PersonalAccessTokenController::class, 'destroy'])
        ->middleware('throttle:api-token-management')
        ->name('api-tokens.destroy')
        ->whereNumber('token');

    Route::get('mfa', [MfaController::class, 'index'])->name('mfa.index');

    Route::post('pindah-tahun/{tahun}', [YearController::class, 'switch'])
        ->name('pindah-tahun')->whereNumber('tahun');

    Route::get('app', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('surat/belum-diberkaskan', [SuratListController::class, 'belumDiberkaskan'])
        ->name('surat.belum-diberkaskan');
    Route::get('surat/masuk', [SuratListController::class, 'masuk'])->name('surat.masuk');
    Route::get('surat/masuk/export/pencatatan', [SuratMasukController::class, 'exportPencatatanExcel'])->name('surat.masuk.export-pencatatan');
    Route::get('surat/keluar', [SuratListController::class, 'keluar'])->name('surat.keluar');
    Route::get('surat/keluar/export/pencatatan', [SuratKeluarController::class, 'exportPencatatanExcel'])->name('surat.keluar.export-pencatatan');
    Route::get('surat/digital', [AppController::class, 'digital'])->name('surat.digital');
    Route::get('surat/klasifikasi', [AppController::class, 'klasifikasi'])->name('surat.klasifikasi');
    Route::get('surat/klasifikasi/export/excel', [KlasifikasiExportController::class, 'exportExcel'])->name('surat.klasifikasi.export');
    Route::get('surat/berkas', [AppController::class, 'berkas'])->name('surat.berkas');
    Route::get('surat/detail-item/{jenis}/{idSurat}', [AppController::class, 'detailItem'])
        ->where([
            'jenis' => 'masuk|keluar|digital',
            'idSurat' => '[0-9]+',
        ])
        ->name('surat.detailItem');
    Route::get('surat/berkas/export/excel', [BerkasExportController::class, 'exportBerkasExcel'])->name('surat.berkas.export');
    Route::get('surat/alih-media/penyeleksian', [AppController::class, 'alihMediaPenyeleksian'])->name('alih-media.penyeleksian');
    Route::get('surat/alih-media/diproses', [AppController::class, 'alihMediaDiproses'])->name('alih-media.diproses');
    Route::get('surat/alih-media/selesai', [AppController::class, 'alihMediaSelesai'])->name('alih-media.selesai');
    Route::get('surat/alih-media/diproses/export/daftar-arsip', [AlihMediaExportController::class, 'exportDaftarArsipExcel'])->name('alih-media.diproses.export-daftar-arsip');
    Route::get('surat/alih-media/selesai/export/daftar-arsip', [AlihMediaExportController::class, 'exportDaftarArsipSelesaiExcel'])->name('alih-media.selesai.export-daftar-arsip');
    Route::post('surat/alih-media/penyeleksian/proses/{id}', [AlihMediaController::class, 'proses'])->name('alih-media.penyeleksian.proses');
    Route::post('surat/alih-media/diproses/ulangi/{id}', [AlihMediaController::class, 'ulangi'])->name('alih-media.diproses.ulangi');
    Route::post('surat/alih-media/diproses/tutup-semua', [AlihMediaController::class, 'tutupSemua'])->name('alih-media.diproses.tutup-semua');

    Route::get('surat/masuk/cek-agenda', [SuratMasukController::class, 'cekAgenda'])->name('masuk.cek-agenda');
    Route::get('surat/masuk/tambah', [SuratMasukController::class, 'tambah'])->name('masuk.tambah');
    Route::post('surat/masuk/tambah', [SuratMasukController::class, 'store']);
    Route::get('surat/masuk/edit/{id}', [SuratMasukController::class, 'edit'])->name('masuk.edit');
    Route::post('surat/masuk/edit/{id}', [SuratMasukController::class, 'update']);
    Route::delete('surat/masuk/hapus/{id}', [SuratMasukController::class, 'hapus'])->name('masuk.hapus');

    Route::get('surat/keluar/tambah', [SuratKeluarController::class, 'tambah'])->name('keluar.tambah');
    Route::post('surat/keluar/tambah', [SuratKeluarController::class, 'store']);
    Route::get('surat/keluar/edit/{id}', [SuratKeluarController::class, 'edit'])->name('keluar.edit');
    Route::post('surat/keluar/edit/{id}', [SuratKeluarController::class, 'update']);
    Route::delete('surat/keluar/hapus/{id}', [SuratKeluarController::class, 'hapus'])->name('keluar.hapus');

    Route::get('surat/digital/tambah', [SuratDigitalController::class, 'tambah'])->name('digital.tambah');
    Route::post('surat/digital/tambah', [SuratDigitalController::class, 'store']);
    Route::get('surat/digital/edit/{id}', [SuratDigitalController::class, 'edit'])->name('digital.edit');
    Route::post('surat/digital/edit/{id}', [SuratDigitalController::class, 'update']);
    Route::delete('surat/digital/hapus/{id}', [SuratDigitalController::class, 'hapus'])->name('digital.hapus');

    Route::get('surat/klasifikasi/tambah', [KlasifikasiController::class, 'tambah'])->name('klasifikasi.tambah');
    Route::post('surat/klasifikasi/tambah', [KlasifikasiController::class, 'store']);
    Route::get('surat/klasifikasi/edit/{id}', [KlasifikasiController::class, 'edit'])->name('klasifikasi.edit');
    Route::post('surat/klasifikasi/edit/{id}', [KlasifikasiController::class, 'update']);
    Route::delete('surat/klasifikasi/hapus/{id}', [KlasifikasiController::class, 'hapus'])->name('klasifikasi.hapus');

    Route::get('surat/berkas/buka/{id}', [BerkasContentController::class, 'buka'])
        ->whereNumber('id')
        ->name('berkas.buka');
    Route::post('surat/berkas/lampirkan/{id}', [BerkasContentController::class, 'lampirkanBulk'])
        ->whereNumber('id')
        ->name('berkas.lampirkanBulk');
    Route::get('surat/berkas/aktif/list', [BerkasContentController::class, 'daftarBerkasAktif'])->name('berkas.aktif.list');
    Route::post('surat/berkas/keluarkan/{idBerkas}/{jenis}/{idSurat}', [BerkasContentController::class, 'keluarkan'])
        ->whereNumber('idBerkas')
        ->whereNumber('idSurat')
        ->name('berkas.keluarkan');
    Route::post('surat/berkas/ganti-lokasi-bulk', [BerkasContentController::class, 'gantiLokasiBulk'])->name('berkas.gantiLokasiBulk');
    Route::post('surat/berkas/pindah/{id}/{status}', [BerkasStatusController::class, 'pindah'])
        ->whereNumber('id')
        ->name('berkas.pindah');
    Route::get('surat/berkas/tambah', [BerkasController::class, 'tambah'])->name('berkas.tambah');
    Route::post('surat/berkas/tambah', [BerkasController::class, 'store']);
    Route::get('surat/berkas/edit/{id}', [BerkasController::class, 'edit'])->name('berkas.edit');
    Route::post('surat/berkas/edit/{id}', [BerkasController::class, 'update']);
    Route::delete('surat/berkas/hapus/{id}', [BerkasController::class, 'hapus'])->name('berkas.hapus');
});

Route::get('guest', [GuestController::class, 'index'])->name('guest');
Route::get('guest/masuk', [GuestController::class, 'masuk'])->name('guest.masuk');
Route::get('guest/keluar', [GuestController::class, 'keluar'])->name('guest.keluar');
Route::get('guest/digital', [GuestController::class, 'digital'])->name('guest.digital');
Route::get('guest/dokumen/{jenis}/{id}/{nama?}', [DocumentController::class, 'public'])
    ->middleware('throttle:guest-documents')
    ->name('document.public')
    ->where([
        'jenis' => 'masuk|keluar|digital',
        'id' => '[0-9]+',
        'nama' => '[A-Za-z0-9-]+\.pdf',
    ]);
Route::get('guest/dokumen-terbatas/{jenis}/{id}/{nama?}', [DocumentController::class, 'temporary'])
    ->middleware(['signed', 'throttle:guest-documents'])
    ->name('document.temporary')
    ->where([
        'jenis' => 'masuk|keluar',
        'id' => '[0-9]+',
        'nama' => '[A-Za-z0-9-]+\.pdf',
    ]);
Route::post('guest/buka', [GuestController::class, 'bukaSurat'])
    ->middleware('throttle:guest-document-mfa')
    ->name('guest.buka');
