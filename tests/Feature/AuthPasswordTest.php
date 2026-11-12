<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('authenticated users can change their own password and revoke other sessions', function () {
    $user = User::factory()->create(['password' => 'CurrentPassword123!']);
    $currentToken = $user->createToken('current-device');
    $user->createToken('other-device');

    $this->withToken($currentToken->plainTextToken)
        ->putJson('/api/auth/password', [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Password berhasil diperbarui.');

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(1);
});

test('users cannot change their password without the correct current password', function () {
    $user = User::factory()->create(['password' => 'CurrentPassword123!']);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/password', [
        'current_password' => 'WrongPassword123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('CurrentPassword123!', $user->fresh()->password))->toBeTrue();
});

test('users must confirm their new password', function () {
    $user = User::factory()->create(['password' => 'CurrentPassword123!']);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/password', [
        'current_password' => 'CurrentPassword123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'DifferentPassword123!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(Hash::check('CurrentPassword123!', $user->fresh()->password))->toBeTrue();
});
