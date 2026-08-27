<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('shows only tokens owned by the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $user->createToken('Token Milik Sendiri', ['surat:create']);
    $otherUser->createToken('Token Milik Orang Lain', ['surat:create']);

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertSuccessful()
        ->assertSee('Token Milik Sendiri')
        ->assertSee('Buka panduan pengisian')
        ->assertSee('Panduan Pengisian API Surat')
        ->assertSee('Surat Masuk SRIKANDI')
        ->assertSee('Surat Masuk Non-SRIKANDI')
        ->assertSee('Surat Keluar SRIKANDI')
        ->assertSee('Surat Keluar Non-SRIKANDI')
        ->assertSee('id="apiGuideModal"', false)
        ->assertSee('data-target="#apiGuideModal"', false)
        ->assertSee('data-target="#apiGuideIncoming"', false)
        ->assertSee('data-target="#apiGuideIncomingSrikandi"', false)
        ->assertSee('data-target="#apiGuideIncomingManual"', false)
        ->assertSee('data-target="#apiGuideIncomingCekAgenda"', false)
        ->assertSee('data-target="#apiGuideOutgoing"', false)
        ->assertSee('data-target="#apiGuideOutgoingSrikandi"', false)
        ->assertSee('data-target="#apiGuideOutgoingManual"', false)
        ->assertDontSee('Token Milik Orang Lain');
});

it('prevents the token management page from being cached', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertSuccessful();

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('max-age=0');
});

it('allows a user to create a personal access token for their own account', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password-aman'),
    ]);

    $response = $this->actingAs($user)->post(route('api-tokens.store'), [
        'name' => 'Integrasi SRIKANDI',
        'current_password' => 'password-aman',
        'expires_in_days' => 90,
    ]);

    $response
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHas('plain_text_token');

    $plainTextToken = $response->getSession()->get('plain_text_token');
    $token = PersonalAccessToken::findToken($plainTextToken);
    $activity = Activity::query()
        ->where('log_name', 'api-token')
        ->where('event', 'created')
        ->firstOrFail();

    expect($token)
        ->not->toBeNull()
        ->and($token->tokenable_id)->toBe($user->getKey())
        ->and($token->name)->toBe('Integrasi SRIKANDI')
        ->and($token->abilities)->toBe(['surat:create'])
        ->and($token->token)->not->toBe($plainTextToken)
        ->and($token->expires_at)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->getKey())
        ->and($activity->properties->toJson())->not->toContain($plainTextToken);

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertSuccessful()
        ->assertSee('autocomplete="off"', escape: false)
        ->assertSee('spellcheck="false"', escape: false);
});

it('requires the current password before creating a token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password-aman'),
    ]);

    $this->actingAs($user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), [
            'name' => 'Integrasi',
            'current_password' => 'password-salah',
            'expires_in_days' => 365,
        ])
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHasErrors('current_password');

    expect($user->tokens()->count())->toBe(0);
});

it('rate limits token creation and revocation routes', function () {
    expect(Route::getRoutes()->getByName('api-tokens.store')?->gatherMiddleware())
        ->toContain('throttle:api-token-management')
        ->and(Route::getRoutes()->getByName('api-tokens.destroy')?->gatherMiddleware())
        ->toContain('throttle:api-token-management');
});

it('enforces the token management rate limit', function () {
    $user = User::factory()->create([
        'id' => 987654,
        'password' => Hash::make('password-aman'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)
            ->post(route('api-tokens.store'), [
                'name' => "Integrasi {$attempt}",
                'current_password' => 'password-aman',
                'expires_in_days' => 30,
            ])
            ->assertRedirect(route('api-tokens.index'));
    }

    $this->actingAs($user)
        ->post(route('api-tokens.store'), [
            'name' => 'Integrasi Keenam',
            'current_password' => 'password-aman',
            'expires_in_days' => 30,
        ])
        ->assertTooManyRequests();

    expect($user->tokens()->count())->toBe(5);
});

it('escapes token names rendered in the management page', function () {
    $user = User::factory()->create();
    $unsafeName = '<img src=x onerror=alert(1)>';
    $user->createToken($unsafeName, ['surat:create']);

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertSuccessful()
        ->assertSee(
            '&lt;img src=x onerror=alert(1)&gt;',
            escape: false
        )
        ->assertDontSee($unsafeName, escape: false);
});

it('allows a user to revoke only their own token', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $owner->createToken('Integrasi', ['surat:create'])->accessToken;

    $this->actingAs($otherUser)
        ->delete(route('api-tokens.destroy', $token->getKey()))
        ->assertNotFound();

    expect($token->fresh())->not->toBeNull();

    $this->actingAs($owner)
        ->delete(route('api-tokens.destroy', $token->getKey()))
        ->assertRedirect(route('api-tokens.index'));

    expect($token->fresh())->toBeNull();
});
