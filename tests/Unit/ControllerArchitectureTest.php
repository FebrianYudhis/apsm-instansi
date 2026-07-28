<?php

use App\Http\Controllers\AlihMediaController;
use App\Http\Controllers\BerkasContentController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\BerkasStatusController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\Surat\SuratKeluarController;
use App\Http\Controllers\Surat\SuratMasukController;
use App\Services\FilelistOperationService;
use App\Services\SuratPencatatanExporter;

it('separates filelist responsibilities into focused controllers and services', function () {
    expect(method_exists(BerkasController::class, 'gantiLokasiBulk'))->toBeFalse()
        ->and(method_exists(BerkasController::class, 'keluarkan'))->toBeFalse()
        ->and(method_exists(BerkasController::class, 'pindah'))->toBeFalse()
        ->and(method_exists(BerkasContentController::class, 'gantiLokasiBulk'))->toBeTrue()
        ->and(method_exists(BerkasContentController::class, 'keluarkan'))->toBeTrue()
        ->and(method_exists(BerkasStatusController::class, 'pindah'))->toBeTrue()
        ->and(class_exists(FilelistOperationService::class))->toBeTrue();
});

it('keeps spreadsheet implementation outside incoming and outgoing controllers', function (
    string $controllerClass
) {
    $source = file_get_contents((new ReflectionClass($controllerClass))->getFileName());

    expect($source)->not->toContain('PhpOffice\\PhpSpreadsheet')
        ->and($source)->toContain(SuratPencatatanExporter::class);
})->with([
    SuratMasukController::class,
    SuratKeluarController::class,
]);

it('keeps refactored controllers below the agreed size ceiling', function (
    string $controllerClass
) {
    $source = file((new ReflectionClass($controllerClass))->getFileName());

    expect(count($source))->toBeLessThanOrEqual(450);
})->with([
    BerkasController::class,
    BerkasContentController::class,
    BerkasStatusController::class,
    SuratMasukController::class,
    SuratKeluarController::class,
]);

it('uses Indonesian defaults in the example environment', function () {
    $exampleEnvironment = file_get_contents(dirname(__DIR__, 2).'/.env.example');

    expect($exampleEnvironment)->toContain('APP_LOCALE=id')
        ->and($exampleEnvironment)->toContain('APP_FALLBACK_LOCALE=id')
        ->and($exampleEnvironment)->toContain('APP_FAKER_LOCALE=id_ID');
});

it('uses one canonical MFA configuration key', function () {
    $servicesSource = file_get_contents(dirname(__DIR__, 2).'/config/services.php');

    expect($servicesSource)->toContain("'mfa' =>")
        ->not->toContain("'google2fa' =>");

    foreach ([
        AlihMediaController::class,
        BerkasStatusController::class,
        GuestController::class,
        MfaController::class,
    ] as $controllerClass) {
        $source = file_get_contents((new ReflectionClass($controllerClass))->getFileName());

        expect($source)->toContain("config('services.mfa.secret')")
            ->not->toContain("config('services.google2fa.secret')");
    }
});
