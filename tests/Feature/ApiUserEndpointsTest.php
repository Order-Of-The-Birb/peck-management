<?php

use App\Models\ApiKey;
use App\Models\Officer;
use App\Models\PeckUser;
use App\Models\PeckUserContext;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.squadron_id', null);
});

test('api users index returns filtered records', function () {
    config()->set('peck.squadron_id', '1061551');

    $matchingOfficer = PeckUser::factory()->create([
        'gaijin_id' => 800001,
    ]);

    Officer::factory()->create([
        'gaijin_id' => $matchingOfficer->gaijin_id,
        'rank' => 'Commander',
    ]);

    $matchingUser = PeckUser::factory()->create([
        'gaijin_id' => 800002,
        'tz' => 2,
    ]);

    $nonMatchingUser = PeckUser::factory()->create([
        'gaijin_id' => 800003,
        'tz' => -3,
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/1061551' => Http::response([
            'members' => [['uid' => (string) $matchingUser->gaijin_id, 'nick' => 'M', 'rank' => ['name' => 'PRIVATE', 'value' => 3], 'joined_at' => 1790000000]],
            'applicants' => [],
        ], 200),
        'https://thunder.example/v1/users/terse*' => Http::response([
            (string) $matchingUser->gaijin_id => ['nick' => 'Maho_Yoshino'],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/users?search=800002&status=member&tz=2');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.gaijin_id', $matchingUser->gaijin_id)
        ->assertJsonPath('data.0.status', 'member')
        ->assertJsonPath('data.0.username', 'Maho_Yoshino')
        ->assertJsonPath('data.0.joined_at', 1790000000)
        ->assertJsonMissingPath('data.0.initiator');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://thunder.example/v1/users/terse')
        && ! $request->hasHeader('Authorization'));

    expect(collect($response->json('data'))->pluck('gaijin_id'))
        ->not->toContain($nonMatchingUser->gaijin_id);
});

test('api users index searches by username', function () {
    config()->set('peck.squadron_id', '1061551');

    $matchingUser = PeckUser::factory()->create([
        'gaijin_id' => 800010,
    ]);

    $nonMatchingUser = PeckUser::factory()->create([
        'gaijin_id' => 800011,
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/1061551' => Http::response([
            'members' => [],
            'applicants' => [],
        ], 200),
        'https://thunder.example/v1/users/terse*' => Http::response([
            (string) $matchingUser->gaijin_id => ['nick' => 'Maho_Yoshino'],
            (string) $nonMatchingUser->gaijin_id => ['nick' => 'Some_Other_Name'],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/users?search=Maho');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.gaijin_id', $matchingUser->gaijin_id)
        ->assertJsonPath('data.0.username', 'Maho_Yoshino');

    expect(collect($response->json('data'))->pluck('gaijin_id'))
        ->not->toContain($nonMatchingUser->gaijin_id);
});

test('api users show returns a single record with derived status', function () {
    config()->set('peck.squadron_id', '1061551');

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 810001,
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/1061551' => Http::response([
            'members' => [['uid' => (string) $peckUser->gaijin_id, 'nick' => 'M', 'rank' => ['name' => 'PRIVATE', 'value' => 3], 'joined_at' => 1]],
            'applicants' => [],
        ], 200),
        'https://thunder.example/v1/users/terse*' => Http::response([
            (string) $peckUser->gaijin_id => ['nick' => 'Maho_Yoshino'],
        ], 200),
    ]);

    $this->getJson('/api/v1/users/'.$peckUser->gaijin_id)
        ->assertOk()
        ->assertJsonPath('data.gaijin_id', $peckUser->gaijin_id)
        ->assertJsonPath('data.status', 'member')
        ->assertJsonPath('data.username', 'Maho_Yoshino');
});

test('api users leave info endpoints no longer exist', function () {
    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 810050,
    ]);

    $this->getJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info')
        ->assertNotFound();

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info', [
        'type' => 'Left',
    ])->assertNotFound();

    $this->patchJson('/api/v1/users/'.$peckUser->gaijin_id.'/leave_info', [
        'type' => 'Left',
    ])->assertNotFound();
});

test('api users context show returns public per-user context entries', function () {
    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 810060,
    ]);

    PeckUserContext::factory()->create([
        'user_id' => $peckUser->gaijin_id,
        'context_id' => 0,
        'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
        'from_date' => '2026-05-01',
        'to_date' => '2026-06-01',
        'comment' => null,
    ]);

    PeckUserContext::factory()->create([
        'user_id' => $peckUser->gaijin_id,
        'context_id' => 1,
        'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
        'weekdays' => [0, 3, 6],
        'comment' => null,
    ]);

    $this->getJson('/api/v1/users/'.$peckUser->gaijin_id.'/context')
        ->assertOk()
        ->assertJsonPath('0.id', 0)
        ->assertJsonPath('0.type', PeckUserContext::TYPE_ONCE_ABSENCE)
        ->assertJsonPath('0.from', '2026-05-01')
        ->assertJsonPath('0.to', '2026-06-01')
        ->assertJsonPath('1.id', 1)
        ->assertJsonPath('1.weekdays', [0, 3, 6]);
});

test('api users index supports page query without pagination metadata in response', function () {
    PeckUser::factory()->create([
        'gaijin_id' => 811001,
    ]);

    PeckUser::factory()->create([
        'gaijin_id' => 811002,
    ]);

    PeckUser::factory()->create([
        'gaijin_id' => 811003,
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
    ])->assertUnauthorized();
});

test('api users context store requires api key authentication', function () {
    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 820120,
    ]);

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/context', [
        'type' => PeckUserContext::TYPE_MISC,
        'comment' => 'Max rank: 14.7',
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
    ]);

    Officer::factory()->create([
        'gaijin_id' => $officerUser->gaijin_id,
        'rank' => 'Executive Officer',
    ]);

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->postJson('/api/v1/users', [
        'token' => $apiToken,
        'gaijin_id' => 820003,
        'discord_id' => 123456789012345678,
        'tz' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.gaijin_id', 820003)
        ->assertJsonPath('data.discord_id', 123456789012345678)
        ->assertJsonMissingPath('data.username')
        ->assertJsonMissingPath('data.initiator');

    $createdUser = PeckUser::query()->find(820003);

    expect($createdUser)->not->toBeNull();
    expect($createdUser?->discord_id)->toBe(123456789012345678);
});

test('api users context endpoints create split recurring entries and modify them', function () {
    $admin = User::query()->create([
        'name' => 'API Context Admin',
        'email' => 'api-context-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $apiToken = ApiKey::issueForOwner($admin->id);

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 820130,
    ]);

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/context', [
        'token' => $apiToken,
        'type' => PeckUserContext::TYPE_MISC,
        'comment' => 'Max rank: 14.7',
    ])->assertCreated()
        ->assertJsonPath('0.id', 0)
        ->assertJsonPath('0.comment', 'Max rank: 14.7');

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/context', [
        'token' => $apiToken,
        'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
        'weekdays' => [6, 0, 3],
        'monthDay' => 26,
    ])->assertCreated()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', 1)
        ->assertJsonPath('0.weekdays', [0, 3, 6])
        ->assertJsonPath('1.id', 2)
        ->assertJsonPath('1.monthDay', 26);

    $this->patchJson('/api/v1/users/'.$peckUser->gaijin_id.'/context/0', [
        'token' => $apiToken,
        'comment' => 'Speaks occasionally, but prefers typing',
    ])->assertOk()
        ->assertJsonPath('id', 0)
        ->assertJsonPath('comment', 'Speaks occasionally, but prefers typing');

    $this->deleteJson('/api/v1/users/'.$peckUser->gaijin_id.'/context/1', [
        'token' => $apiToken,
    ])->assertNoContent();

    $this->postJson('/api/v1/users/'.$peckUser->gaijin_id.'/context', [
        'token' => $apiToken,
        'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
        'from' => '2026-06-01',
        'to' => '2026-06-15',
    ])->assertCreated()
        ->assertJsonPath('0.id', 1);

    expect(
        PeckUserContext::query()
            ->where('user_id', $peckUser->gaijin_id)
            ->orderBy('context_id')
            ->pluck('context_id')
            ->all()
    )->toBe([0, 1, 2]);
});

test('api users update updates discord id and timezone', function () {
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
    ]);

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->patchJson('/api/v1/users/'.$targetUser->gaijin_id, [
        'token' => $apiToken,
        'discord_id' => 887766554433221100,
        'tz' => 3,
    ])->assertOk()
        ->assertJsonPath('data.gaijin_id', $targetUser->gaijin_id)
        ->assertJsonPath('data.discord_id', 887766554433221100)
        ->assertJsonPath('data.tz', 3);

    $targetUser->refresh();

    expect($targetUser->discord_id)->toBe(887766554433221100)
        ->and($targetUser->tz)->toBe(3);
});

test('api users store is forbidden for unverified api key users', function () {
    $admin = User::query()->create([
        'name' => 'API Unverified Admin',
        'email' => 'api-unverified-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => null,
        'level' => 1,
    ])->save();

    $apiToken = ApiKey::issueForOwner($admin->id);

    $this->postJson('/api/v1/users', [
        'token' => $apiToken,
        'gaijin_id' => 820004,
    ])->assertForbidden();
});
