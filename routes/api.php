<?php

use App\Http\Controllers\Api\V1\AuthenticatedUserController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use App\Http\Controllers\Api\V1\StoreIncomingController;
use App\Http\Controllers\Api\V1\StoreOutgoingController;
use App\Http\Middleware\RequirePersonalAccessToken;
use App\Http\Middleware\SetActivityLogCauser;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        'auth:sanctum',
        RequirePersonalAccessToken::class,
        'abilities:surat:create',
        SetActivityLogCauser::class,
        'throttle:api',
    ])
    ->group(function (): void {
        Route::get('me', AuthenticatedUserController::class)->name('api.v1.me');
        Route::get('referensi/sifat-akses', [ReferenceDataController::class, 'accesses'])
            ->name('api.v1.references.accesses');
        Route::get('referensi/berkas-aktif', [ReferenceDataController::class, 'activeFilelists'])
            ->name('api.v1.references.active-filelists');
        Route::post('surat/masuk', StoreIncomingController::class)
            ->name('api.v1.surat.masuk.store');
        Route::post('surat/keluar', StoreOutgoingController::class)
            ->name('api.v1.surat.keluar.store');
    });
