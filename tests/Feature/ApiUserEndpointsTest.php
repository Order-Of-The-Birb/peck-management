<?php

use App\Models\ApiKey;
use App\Models\Officer;
use App\Models\PeckLeaveInfo;
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
        ->assertJsonPath('data.0.initiator', $matchingOfficer->gaijin_id)
        ->assertJsonMissingPath('data.0.initiator_username')
        ->assertJsonMissingPath('data.0.initiator_rank');

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

test('api users leave info show returns null or leave type', function () {
    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 810050,
        'username' => 'leave_info_show_target',
        'status' => 'ex_member',
    ]);

    $this->getJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data', null);

    PeckLeaveInfo::query()->create([
        'user_id' => $peckUser->gaijin_id,
        'type' => PeckLeaveInfo::TYPE_LEFT_SERVER,
    ]);

    $this->getJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data', PeckLeaveInfo::TYPE_LEFT_SERVER);
});

test('api users index supports page query without pagination metadata in response', function () {
    PeckUser::factory()->create([
        'gaijin_id' => 811001,
        'username' => 'page_target_1',
    ]);

    PeckUser::factory()->create([
        'gaijin_id' => 811002,
        'username' => 'page_target_2',
    ]);

    PeckUser::factory()->create([
        'gaijin_id' => 811003,
        'username' => 'page_target_3',
    ]);

    $response = $this->getJson('/api/v1/users?sort_by=gaijin_id&sort_direction=asc&per_page=2&page=2');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.gaijin_id', 811003)
        ->assertJsonMissingPath('links')
        ->assertJsonMissingPath('meta');
});

test('api users store requires api key authentication', function () {
    $this->postJson('/api/v1/users', [
        'gaijin_id' => 820001,
        'username' => 'unauthorized_create',
        'status' => 'member',
    ])->assertUnauthorized();
});

test('api users store creates a record for valid api key users', function () {
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

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->postJson('/api/v1/users', [
        'token' => $apiToken,
        'gaijin_id' => 820003,
        'username' => 'created_via_api',
        'discord_id' => 123456789012345678,
        'tz' => 1,
        'status' => 'member',
        'joindate' => '2026-03-24',
        'initiator' => $officerUser->gaijin_id,
    ])->assertCreated()
        ->assertJsonPath('data.gaijin_id', 820003)
        ->assertJsonPath('data.initiator', $officerUser->gaijin_id)
        ->assertJsonMissingPath('data.initiator_username')
        ->assertJsonMissingPath('data.initiator_rank');

    $createdUser = PeckUser::query()->find(820003);

    expect($createdUser)->not->toBeNull();
    expect($createdUser?->initiator)->toBe($officerUser->gaijin_id);
});

test('api users store derives unverified status when member has no discord id', function () {
    $admin = User::query()->create([
        'name' => 'API Status Derivation Admin',
        'email' => 'api-status-derivation-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->postJson('/api/v1/users', [
        'token' => $apiToken,
        'gaijin_id' => 820004,
        'username' => 'created_without_discord',
        'discord_id' => null,
        'status' => 'member',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'unverified');

    expect(PeckUser::query()->find(820004)?->status)->toBe('unverified');
});

test('api users store rejects alt status', function () {
    $admin = User::query()->create([
        'name' => 'API Alt Validation Admin',
        'email' => 'api-alt-validation-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->postJson('/api/v1/users', [
        'token' => $apiToken,
        'gaijin_id' => 820099,
        'username' => 'alt_status_attempt',
        'status' => 'alt',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('api users leave info upsert endpoint creates and updates leave info', function () {
    $admin = User::query()->create([
        'name' => 'API Leave Info Admin',
        'email' => 'api-leave-info-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $apiToken = ApiKey::issueForOwner($admin->id);

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 820110,
        'username' => 'leave_info_upsert_target',
        'status' => 'ex_member',
    ]);

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info', [
        'token' => $apiToken,
        'type' => PeckLeaveInfo::TYPE_LEFT,
    ])->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data', PeckLeaveInfo::TYPE_LEFT);

    expect(PeckLeaveInfo::query()->find($peckUser->gaijin_id)?->type)->toBe(PeckLeaveInfo::TYPE_LEFT);

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info', [
        'token' => $apiToken,
        'type' => PeckLeaveInfo::TYPE_LEFT_SERVER,
    ])->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data', PeckLeaveInfo::TYPE_LEFT_SERVER);

    expect(PeckLeaveInfo::query()->find($peckUser->gaijin_id)?->type)->toBe(PeckLeaveInfo::TYPE_LEFT_SERVER);

    $this->patchJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info', [
        'token' => $apiToken,
        'type' => PeckLeaveInfo::TYPE_LEFT_SQUADRON,
    ])->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data', PeckLeaveInfo::TYPE_LEFT_SQUADRON);

    expect(PeckLeaveInfo::query()->find($peckUser->gaijin_id)?->type)->toBe(PeckLeaveInfo::TYPE_LEFT_SQUADRON);

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info', [
        'token' => $apiToken,
        'type' => 'InvalidLeaveType',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('api users leave info endpoints return 404 when user is missing', function () {
    $admin = User::query()->create([
        'name' => 'API Leave Info 404 Admin',
        'email' => 'api-leave-info-404-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->getJson('/api/v1/users/99999111/leave_info')
        ->assertNotFound();

    $this->postJson('/api/v1/users/99999111/leave_info', [
        'token' => $apiToken,
        'type' => PeckLeaveInfo::TYPE_LEFT,
    ])->assertNotFound();

    $this->patchJson('/api/v1/users/99999111/leave_info', [
        'token' => $apiToken,
        'type' => PeckLeaveInfo::TYPE_LEFT_SERVER,
    ])->assertNotFound();
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

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->patchJson('/api/v1/users/'.$targetUser->gaijin_id, [
        'token' => $apiToken,
        'initiator' => $nonOfficerUser->gaijin_id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['initiator']);

    $this->patchJson('/api/v1/users/'.$targetUser->gaijin_id, [
        'token' => $apiToken,
        'username' => 'patched_user',
        'discord_id' => 998877665544332211,
        'status' => 'member',
        'initiator' => $officerUser->gaijin_id,
    ])->assertOk()
        ->assertJsonPath('data.username', 'patched_user')
        ->assertJsonPath('data.initiator', $officerUser->gaijin_id)
        ->assertJsonMissingPath('data.initiator_username')
        ->assertJsonMissingPath('data.initiator_rank');

    $targetUser->refresh();

    expect($targetUser->username)->toBe('patched_user')
        ->and($targetUser->initiator)->toBe($officerUser->gaijin_id);
});

test('api users update promotes unverified user to member when discord id is provided', function () {
    $admin = User::query()->create([
        'name' => 'API Promote Unverified Admin',
        'email' => 'api-promote-unverified-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $targetUser = PeckUser::factory()->create([
        'gaijin_id' => 830004,
        'username' => 'promote_unverified_target',
        'status' => 'unverified',
        'discord_id' => null,
    ]);

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->patchJson('/api/v1/users/'.$targetUser->gaijin_id, [
        'token' => $apiToken,
        'discord_id' => 887766554433221100,
    ])->assertOk()
        ->assertJsonPath('data.status', 'member');

    expect(PeckUser::query()->find($targetUser->gaijin_id)?->status)->toBe('member');
});

test('api users update removes leave info when status changes away from ex_member', function () {
    $admin = User::query()->create([
        'name' => 'API Ex Member Cleanup Admin',
        'email' => 'api-ex-member-cleanup-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $apiToken = ApiKey::issueForOwner($admin->id);

    $exMemberUser = PeckUser::factory()->create([
        'gaijin_id' => 830110,
        'username' => 'api_cleanup_target',
        'status' => 'ex_member',
    ]);

    PeckLeaveInfo::query()->create([
        'user_id' => $exMemberUser->gaijin_id,
        'type' => PeckLeaveInfo::TYPE_LEFT,
    ]);

    $this->patchJson('/api/v1/users/'.$exMemberUser->gaijin_id, [
        'token' => $apiToken,
        'discord_id' => null,
        'status' => 'member',
    ])->assertOk()
        ->assertJsonPath('data.status', 'unverified');

    expect(PeckLeaveInfo::query()->find($exMemberUser->gaijin_id))->toBeNull();
});
