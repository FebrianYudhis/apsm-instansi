<?php

use App\Models\Access;
use App\Models\AlihMediaStatus;
use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;

it('only allows explicitly declared attributes to be mass assigned', function (
    string $modelClass,
    array $expectedFillable
) {
    $model = new $modelClass;

    expect($model->getFillable())->toBe($expectedFillable)
        ->and($model->isFillable('id'))->toBeFalse()
        ->and($model->isFillable('created_at'))->toBeFalse()
        ->and($model->isFillable('deleted_at'))->toBeFalse();
})->with([
    'access' => [Access::class, ['sifat_akses']],
    'alih media status' => [AlihMediaStatus::class, ['nama_status']],
    'classification' => [Classification::class, ['kode_klasifikasi', 'keterangan']],
    'digital' => [Digital::class, ['perihal', 'url']],
    'filelist' => [Filelist::class, [
        'classification_id',
        'nama_berkas',
        'status_id',
        'retensi_aktif',
        'retensi_inaktif',
        'keterangan_akhir',
        'alih_media_status_id',
    ]],
    'incoming' => [Incoming::class, [
        'nomor_agenda',
        'tanggal_diterima',
        'nomor_surat',
        'pengirim',
        'tanggal_surat',
        'perihal',
        'url',
        'tahun',
        'is_srikandi',
        'url_watermarked',
        'access_id',
        'filelist_id',
    ]],
    'outcoming' => [Outcoming::class, [
        'tanggal_surat',
        'nomor_surat',
        'tujuan',
        'perihal',
        'url',
        'tahun',
        'is_digital',
        'is_srikandi',
        'url_watermarked',
        'access_id',
        'filelist_id',
    ]],
    'status' => [Status::class, ['nama_status']],
]);

it('logs only fillable changes on activity tracked models', function (string $modelClass) {
    $options = (new $modelClass)->getActivitylogOptions();

    expect($options->logFillable)->toBeTrue()
        ->and($options->logUnguarded)->toBeFalse()
        ->and($options->logOnlyDirty)->toBeTrue();
})->with([
    Classification::class,
    Digital::class,
    Filelist::class,
    Incoming::class,
    Outcoming::class,
]);
