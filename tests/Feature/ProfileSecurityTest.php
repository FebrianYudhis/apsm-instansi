<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_identity_can_be_updated_without_changing_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
        ]);
        $oldPasswordHash = $user->password;

        $this->actingAs($user)->post(route('profil.update'), [
            'name' => 'Nama Baru',
            'username' => 'username-baru',
        ])->assertRedirect(route('profil.edit'));

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('username-baru', $user->username);
        $this->assertSame($oldPasswordHash, $user->password);
    }

    public function test_password_change_requires_the_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('profil.edit'))
            ->post(route('profil.update'), $this->passwordPayload());

        $response->assertRedirect(route('profil.edit'));
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }

    public function test_password_change_rejects_an_incorrect_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('profil.edit'))
            ->post(route('profil.update'), $this->passwordPayload([
                'current_password' => 'password-salah',
            ]));

        $response->assertRedirect(route('profil.edit'));
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }

    public function test_password_change_accepts_the_correct_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
        ]);

        $this->actingAs($user)->post(route('profil.update'), $this->passwordPayload([
            'current_password' => 'password-lama',
        ]))->assertRedirect(route('profil.edit'));

        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
    }

    public function test_password_change_rejects_a_new_password_shorter_than_eight_characters()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('profil.edit'))
            ->post(route('profil.update'), $this->passwordPayload([
                'current_password' => 'password-lama',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ]));

        $response->assertRedirect(route('profil.edit'));
        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }

    private function passwordPayload(array $overrides = [])
    {
        return array_merge([
            'name' => 'Nama Pengguna',
            'username' => 'username-aman',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ], $overrides);
    }
}
