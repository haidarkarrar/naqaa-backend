<?php

use App\Http\Middleware\AuthenticateApiUser;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->withoutMiddleware(AuthenticateApiUser::class);
    $this->seed(AccessControlSeeder::class);
});

it('enforces password separation between update and dedicated password endpoints', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $targetUser = User::factory()->create([
        'password' => 'old-password-123',
    ]);

    $this
        ->actingAs($admin)
        ->patchJson("/api/admin/users/{$targetUser->id}", [
            'display_name' => 'Updated Display Name',
            'password' => 'new-password-123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $this
        ->actingAs($admin)
        ->patchJson("/api/admin/users/{$targetUser->id}/password", [
            'password' => 'new-password-123',
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Password updated',
        ]);

    $targetUser->refresh();

    expect(Hash::check('new-password-123', $targetUser->password))->toBeTrue();
});
