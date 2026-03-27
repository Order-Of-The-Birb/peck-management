<?php

use App\Models\Officer;
use App\Models\PeckUser;
use App\Models\User;

test('api users index returns filtered records', function () {
    $matchingOfficer = PeckUser::factory()->create([
        'gaijin_id' => 800001,
        'username' => 'officer_alpha',
    ]);

    Officer::factory()->create([
        'gaijin_id' => $matchingOfficer->gaijin_id,
        'rank' => 'Commander',
    ]);

    $matchingUser = PeckUser::factory()->create([
        'gaijin_id' => 800002,
        'username' => 'api_target',
        'status' => 'member',
        'tz' => 2,
        'initiator' => $matchingOfficer->gaijin_id,
    ]);

    $nonMatchingUser = PeckUser::factory()->create([
        'gaijin_id' => 800003,
        'username' => 'api_other',
        'status' => 'unverified',
        'tz' => -3,
    ]);

    $response = $this->getJson('/api/v1/users?search=target&status=member&tz=2');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.gaijin_id', $matchingUser->gaijin_id)
        ->assertJsonPath('data.0.initiator_username', $matchingOfficer->username)
        ->assertJsonPath('data.0.initiator_rank', 'Commander');

    expect(collect($response->json('data'))->pluck('gaijin_id'))
        ->not->toContain($nonMatchingUser->gaijin_id);
});

test('api users show returns a single record', function () {
    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 810001,
        'username' => 'show_target',
    ]);

    $this->getJson('/api/v1/users/'.$peckUser->gaijin_id)
        ->assertOk()
        ->assertJsonPath('data.gaijin_id', $peckUser->gaijin_id)
        ->assertJsonPath('data.username', 'show_target');
});

test('api users store requires sanctum authentication', function () {
    $this->postJson('/api/v1/users', [
        'gaijin_id' => 820001,
        'username' => 'unauthorized_create',
        'status' => 'member',
    ])->assertUnauthorized();
});

test('api users store creates a record for authorized users', function () {
    $admin = User::query()->create([
        'name' => 'API Admin',
        'email' => 'api-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $officerUser = PeckUser::factory()->create([
        'gaijin_id' => 820002,
        'username' => 'authorized_officer',
    ]);

    Officer::factory()->create([
        'gaijin_id' => $officerUser->gaijin_id,
        'rank' => 'Executive Officer',
    ]);

    $this->actingAs($admin);

    $this->postJson('/api/v1/users', [
        'gaijin_id' => 820003,
        'username' => 'created_via_api',
        'discord_id' => 123456789012345678,
        'tz' => 1,
        'status' => 'member',
        'joindate' => '2026-03-24',
        'initiator' => $officerUser->gaijin_id,
    ])->assertCreated()
        ->assertJsonPath('data.gaijin_id', 820003)
        ->assertJsonPath('data.initiator_username', $officerUser->username)
        ->assertJsonPath('data.initiator_rank', 'Executive Officer');

    $createdUser = PeckUser::query()->find(820003);

    expect($createdUser)->not->toBeNull();
    expect($createdUser?->initiator)->toBe($officerUser->gaijin_id);
});

test('api users update only accepts officer initiators', function () {
    $admin = User::query()->create([
        'name' => 'API Editor',
        'email' => 'api-editor@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $targetUser = PeckUser::factory()->create([
        'gaijin_id' => 830001,
        'username' => 'patch_target',
        'status' => 'unverified',
    ]);

    $officerUser = PeckUser::factory()->create([
        'gaijin_id' => 830002,
        'username' => 'patch_officer',
    ]);

    Officer::factory()->create([
        'gaijin_id' => $officerUser->gaijin_id,
        'rank' => 'Recruitment Officer',
    ]);

    $nonOfficerUser = PeckUser::factory()->create([
        'gaijin_id' => 830003,
        'username' => 'patch_non_officer',
    ]);

    $this->actingAs($admin);

    $this->patchJson('/api/v1/users/'.$targetUser->gaijin_id, [
        'initiator' => $nonOfficerUser->gaijin_id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['initiator']);

    $this->patchJson('/api/v1/users/'.$targetUser->gaijin_id, [
        'username' => 'patched_user',
        'status' => 'member',
        'initiator' => $officerUser->gaijin_id,
    ])->assertOk()
        ->assertJsonPath('data.username', 'patched_user')
        ->assertJsonPath('data.initiator_rank', 'Recruitment Officer');

    $targetUser->refresh();

    expect($targetUser->username)->toBe('patched_user')
        ->and($targetUser->initiator)->toBe($officerUser->gaijin_id);
});
