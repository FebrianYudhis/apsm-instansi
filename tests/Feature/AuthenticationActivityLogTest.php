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
    ])->assertRedirect('/app');

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
