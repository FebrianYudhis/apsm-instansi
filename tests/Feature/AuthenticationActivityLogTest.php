<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('login and logout are each recorded once', function () {
    $user = User::factory()->create();
    Activity::query()->delete();

    $this->post(route('login'), [
        'username' => $user->username,
        'password' => 'password',
        'tahun' => now()->year,
    ])->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
    expect(Activity::query()
        ->where('log_name', 'auth')
        ->where('description', 'User successfully logged in')
        ->where('causer_id', $user->id)
        ->count())->toBe(1);

    $this->post(route('logout'))->assertRedirect();

    $this->assertGuest();
    expect(Activity::query()
        ->where('log_name', 'auth')
        ->where('description', 'User successfully logged out')
        ->where('causer_id', $user->id)
        ->count())->toBe(1);
});

test('authentication redirects preserve the application subpath', function () {
    $user = User::factory()->create();

    $this->withServerVariables([
        'SCRIPT_NAME' => '/apsm/index.php',
        'SCRIPT_FILENAME' => public_path('index.php'),
        'REQUEST_URI' => '/apsm/login',
    ]);

    $loginResponse = $this->post('/apsm/login', [
        'username' => $user->username,
        'password' => 'password',
        'tahun' => now()->year,
    ])->assertRedirect();

    $authenticatedResponse = $this->get('/login')
        ->assertRedirect();

    expect(parse_url($loginResponse->headers->get('Location'), PHP_URL_PATH))
        ->toBe('/apsm/app')
        ->and(parse_url($authenticatedResponse->headers->get('Location'), PHP_URL_PATH))
        ->toBe('/apsm/app');
});
