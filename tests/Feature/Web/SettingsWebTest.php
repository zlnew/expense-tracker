<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Maulana Aprizqy',
        'email' => 'maul@example.com',
        'password' => Hash::make('SecretPassword123!'),
    ]);
    $this->actingAs($this->user);
});

test('profile page is displayed', function () {
    $response = $this->get(route('profile.edit'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->has('mustVerifyEmail')
        );
});

test('profile information and discord webhook url can be updated', function () {
    $response = $this->patch(route('profile.update'), [
        'name' => 'Maulana Updated',
        'email' => 'maulana.new@example.com',
        'discord_webhook_url' => 'https://discord.com/api/webhooks/123/xyz',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $fresh = $this->user->fresh();
    expect($fresh->name)->toBe('Maulana Updated')
        ->and($fresh->email)->toBe('maulana.new@example.com')
        ->and($fresh->discord_webhook_url)->toBe('https://discord.com/api/webhooks/123/xyz');
});

test('security settings page is displayed', function () {
    $response = $this->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->has('canManageTwoFactor')
        );
});

test('password can be updated with correct current password', function () {
    $response = $this->put(route('user-password.update'), [
        'current_password' => 'SecretPassword123!',
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ]);

    $response->assertRedirect();
    expect(Hash::check('NewPassword456!', $this->user->fresh()->password))->toBeTrue();
});

test('password update fails with incorrect current password', function () {
    $response = $this->put(route('user-password.update'), [
        'current_password' => 'WrongPassword!',
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ]);

    $response->assertSessionHasErrors();
    expect(Hash::check('SecretPassword123!', $this->user->fresh()->password))->toBeTrue();
});

test('appearance page is displayed', function () {
    $response = $this->get(route('appearance.edit'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Appearance')
        );
});

test('user account can be deleted with valid password', function () {
    $response = $this->delete(route('profile.destroy'), [
        'password' => 'SecretPassword123!',
    ]);

    $response->assertRedirect('/');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
});
