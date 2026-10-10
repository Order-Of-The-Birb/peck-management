<?php

use App\Models\ApiKey;
use App\Models\PeckUser;
use App\Models\ThunderApiServerToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('admin settings page is displayed for admins', function () {
    $user = User::query()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($user);

    $this->get(route('admin.edit'))->assertOk();
});

test('changing the selected user updates the selected user level', function () {
    $admin = User::query()->create([
        'name' => 'Admin User',
        'email' => 'admin-levels@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $viewer = User::query()->create([
        'name' => 'Viewer User',
        'email' => 'viewer@example.com',
        'password' => 'password',
    ]);

    $viewer->forceFill([
        'email_verified_at' => now(),
        'level' => 0,
    ])->save();

    $databaseUser = User::query()->create([
        'name' => 'Database User',
        'email' => 'database@example.com',
        'password' => 'password',
    ]);

    $databaseUser->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('selectedManagedUserId', (string) $viewer->id)
        ->assertSet('selectedManagedUserLevel', '0')
        ->set('selectedManagedUserId', (string) $databaseUser->id)
        ->assertSet('selectedManagedUserLevel', '1');
});

test('admin can delete selected authentication user from user access levels card', function () {
    $admin = User::query()->create([
        'name' => 'Admin Delete Selected Auth User',
        'email' => 'admin-delete-selected-auth-user@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $targetUser = User::query()->create([
        'name' => 'Target Auth User',
        'email' => 'target-auth-user@example.com',
        'password' => 'password',
    ]);

    $targetUser->forceFill([
        'email_verified_at' => now(),
        'level' => 0,
    ])->save();

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('selectedManagedUserId', (string) $targetUser->id)
        ->call('requestManagedUserDeletion')
        ->assertSet('showConfirmManagedUserDeletionModal', true)
        ->assertSet('pendingManagedUserDeletionDetails.email', $targetUser->email)
        ->call('confirmManagedUserDeletion')
        ->assertSet('showConfirmManagedUserDeletionModal', false)
        ->assertDispatched('managed-user-deleted');

    expect(User::query()->find($targetUser->id))->toBeNull();
    expect(User::query()->find($admin->id))->not->toBeNull();
});

test('admin cannot delete their own authentication user from user access levels card', function () {
    $admin = User::query()->create([
        'name' => 'Admin Self Delete Guard',
        'email' => 'admin-self-delete-guard@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('selectedManagedUserId', (string) $admin->id)
        ->call('requestManagedUserDeletion')
        ->assertSet('showConfirmManagedUserDeletionModal', false)
        ->assertSet('showManagedUserDeletionError', true)
        ->assertSee('You cannot delete your own account from this screen.');

    expect(User::query()->find($admin->id))->not->toBeNull();
});

test('admin settings api key section does not expose stored key value', function () {
    $user = User::query()->create([
        'name' => 'Admin Token Visibility User',
        'email' => 'admin-token-visibility@example.com',
        'password' => 'password',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($user);

    $plainToken = ApiKey::issueForOwner($user->id);

    $this->get(route('admin.edit'))
        ->assertOk()
        ->assertDontSee($plainToken)
        ->assertSee('Generate new key');
});

test('admin settings generates api token on demand and stores hash only', function () {
    $user = User::query()->create([
        'name' => 'Admin Generate Token User',
        'email' => 'admin-generate-token@example.com',
        'password' => 'password',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.admin')
        ->call('requestApiKeyGeneration')
        ->assertSet('showGeneratedApiKeyModal', true)
        ->assertSet('showConfirmApiKeyResetModal', false);

    $plainToken = $component->get('generatedApiToken');
    $storedApiKey = ApiKey::query()->findOrFail($user->id);

    expect($plainToken)->toBeString()->toStartWith('pmk_')
        ->and($storedApiKey->key)->toBe(ApiKey::hashToken($plainToken))
        ->and($storedApiKey->key)->not->toBe($plainToken)
        ->and($storedApiKey->key_prefix)->toBe(ApiKey::prefixFromToken($plainToken));
});

test('admin settings confirms before resetting existing api token', function () {
    $user = User::query()->create([
        'name' => 'Admin Reset Token User',
        'email' => 'admin-reset-token@example.com',
        'password' => 'password',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($user);

    $previousPlainToken = ApiKey::issueForOwner($user->id);
    $previousHash = ApiKey::hashToken($previousPlainToken);

    $component = Livewire::test('pages::settings.admin')
        ->call('requestApiKeyGeneration')
        ->assertSet('showConfirmApiKeyResetModal', true)
        ->assertSet('showGeneratedApiKeyModal', false)
        ->call('confirmApiKeyReset')
        ->assertSet('showConfirmApiKeyResetModal', false)
        ->assertSet('showGeneratedApiKeyModal', true);

    $newPlainToken = $component->get('generatedApiToken');
    $storedApiKey = ApiKey::query()->findOrFail($user->id);

    expect($newPlainToken)->toBeString()
        ->and($newPlainToken)->not->toBe($previousPlainToken)
        ->and($storedApiKey->key)->toBe(ApiKey::hashToken($newPlainToken))
        ->and($storedApiKey->key)->not->toBe($previousHash);
});

test('delete user section filters war thunder users and shows empty state', function () {
    $admin = User::query()->create([
        'name' => 'Admin Delete User Search',
        'email' => 'admin-delete-user-search@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $matchingPeckUser = PeckUser::factory()->create([
        'gaijin_id' => 910040,
    ]);

    $nonMatchingPeckUser = PeckUser::factory()->create([
        'gaijin_id' => 910041,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test('pages::settings.admin')
        ->set('peckUserDeletionSearch', (string) $matchingPeckUser->gaijin_id);

    $filteredPeckUsers = $component->get('filteredPeckUsersForDeletion');

    expect($filteredPeckUsers)->toHaveCount(1);
    expect($filteredPeckUsers->pluck('gaijin_id')->all())->toBe([$matchingPeckUser->gaijin_id]);
    expect($filteredPeckUsers->pluck('gaijin_id')->all())->not->toContain($nonMatchingPeckUser->gaijin_id);

    $component
        ->set('peckUserDeletionSearch', '999999999')
        ->assertSee('No users match your search.');
});

test('admin can delete a war thunder user without affecting laravel auth users', function () {
    $admin = User::query()->create([
        'name' => 'Admin Delete War Thunder User',
        'email' => 'admin-delete-war-thunder-user@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $unrelatedAuthUser = User::query()->create([
        'name' => 'Unrelated Auth User',
        'email' => 'unrelated-auth-user@example.com',
        'password' => 'password',
    ]);

    $targetPeckUser = PeckUser::factory()->create([
        'gaijin_id' => 910050,
    ]);

    $remainingPeckUser = PeckUser::factory()->create([
        'gaijin_id' => 910051,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->call('openDeletePeckUserModal', $targetPeckUser->gaijin_id)
        ->assertSet('showDeletePeckUserModal', true)
        ->assertSet('pendingDeletePeckUserDetails.gaijin_id', $targetPeckUser->gaijin_id)
        ->call('deletePeckUser')
        ->assertSet('showDeletePeckUserModal', false)
        ->assertDispatched('peck-user-deleted');

    expect(PeckUser::query()->find($targetPeckUser->gaijin_id))->toBeNull();
    expect(PeckUser::query()->find($remainingPeckUser->gaijin_id))->not->toBeNull();
    expect(User::query()->find($unrelatedAuthUser->id))->not->toBeNull();
});

test('non-admin users cannot trigger war thunder user deletion from admin settings component', function () {
    $viewer = User::query()->create([
        'name' => 'Viewer Delete Guard',
        'email' => 'viewer-delete-guard@example.com',
        'password' => 'password',
    ]);

    $viewer->forceFill([
        'email_verified_at' => now(),
        'level' => 0,
    ])->save();

    $targetPeckUser = PeckUser::factory()->create([
        'gaijin_id' => 910060,
    ]);

    $this->actingAs($viewer);

    Livewire::test('pages::settings.admin')
        ->call('openDeletePeckUserModal', $targetPeckUser->gaijin_id)
        ->assertForbidden();
});

test('delete user action reports a graceful error when selected user is already gone', function () {
    $admin = User::query()->create([
        'name' => 'Admin Delete Missing User',
        'email' => 'admin-delete-missing-user@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $targetPeckUser = PeckUser::factory()->create([
        'gaijin_id' => 910070,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test('pages::settings.admin')
        ->call('openDeletePeckUserModal', $targetPeckUser->gaijin_id)
        ->assertSet('showDeletePeckUserModal', true);

    PeckUser::query()->whereKey($targetPeckUser->gaijin_id)->delete();

    $component
        ->call('deletePeckUser')
        ->assertSet('showDeletePeckUserModal', false)
        ->assertSet('showDeletePeckUserError', true)
        ->assertSee('The selected user no longer exists.');
});

test('admin can force a refresh with a global ten minute cooldown', function () {
    $this->withoutDefer();

    $admin = User::query()->create([
        'name' => 'Force Refresh Admin',
        'email' => 'force-refresh-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($admin);

    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');

    ThunderApiServerToken::factory()->create(['token' => 'test-token']);

    Cache::forget((string) config('peck.force_refresh.lock_key'));

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            ['_id' => '123', 'name' => 'Order Of The Birb', 'namel' => 'order of the birb'],
        ], 200),
        'https://thunder.example/v1/clans/123' => Http::response([
            'members' => [],
        ], 200),
    ]);

    Livewire::test('pages::settings.admin')
        ->call('requestForceRefresh')
        ->assertSet('forceRefreshError', null)
        ->assertSee('Refresh completed');

    expect(Cache::has((string) config('peck.force_refresh.lock_key')))->toBeTrue();
    Http::assertSentCount(2);

    Livewire::test('pages::settings.admin')
        ->call('requestForceRefresh')
        ->assertSee('try again in');

    Http::assertSentCount(2);
});

test('force refresh surfaces an error when thunderapi rejects the token', function () {
    $this->withoutDefer();

    $admin = User::query()->create([
        'name' => 'Force Refresh Error Admin',
        'email' => 'force-refresh-error-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($admin);

    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');

    ThunderApiServerToken::factory()->create(['token' => 'test-token']);

    Cache::forget((string) config('peck.force_refresh.lock_key'));
    Cache::forget((string) config('peck.force_refresh.result_key'));

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            'detail' => 'User not found',
        ], 401),
    ]);

    Livewire::test('pages::settings.admin')
        ->call('requestForceRefresh')
        ->assertSee('Failed to search for squadron (HTTP 401)');
});

test('force refresh re-authenticates when the server token is invalid', function () {
    $this->withoutDefer();

    $admin = User::query()->create([
        'name' => 'Force Refresh Invalid Token Admin',
        'email' => 'force-refresh-invalid-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($admin);

    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_refresh.refresh_after_hours', 1);
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create([
        'token' => 'stale-token',
        'refreshed_at' => now()->subHours(2),
    ]);

    Cache::forget((string) config('peck.force_refresh.lock_key'));
    Cache::forget((string) config('peck.force_refresh.result_key'));

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'FAIL',
            'detail' => 'Invalid token',
        ], 404),
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'replacement-token',
            'user_id' => 424242,
        ], 200),
        'https://thunder.example/v1/clans/search/*' => Http::response([
            ['_id' => '123', 'name' => 'Order Of The Birb', 'namel' => 'order of the birb'],
        ], 200),
        'https://thunder.example/v1/clans/123' => Http::response([
            'members' => [],
        ], 200),
    ]);

    Livewire::test('pages::settings.admin')
        ->call('requestForceRefresh')
        ->assertSee('Refresh completed');

    expect(ThunderApiServerToken::query()->value('token'))->toBe('replacement-token');
});

test('user access levels card marks unverified emails', function () {
    $admin = User::query()->create([
        'name' => 'Admin Unverified Marker',
        'email' => 'admin-unverified-marker@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $unverifiedUser = User::query()->create([
        'name' => 'Unverified User',
        'email' => 'unverified-user@example.com',
        'password' => 'password',
    ]);

    $unverifiedUser->forceFill([
        'email_verified_at' => null,
        'level' => 1,
    ])->save();

    $verifiedUser = User::query()->create([
        'name' => 'Verified User',
        'email' => 'verified-user@example.com',
        'password' => 'password',
    ]);

    $verifiedUser->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('selectedManagedUserId', (string) $unverifiedUser->id)
        ->assertSee('(Unverified)')
        ->set('selectedManagedUserId', (string) $verifiedUser->id)
        ->assertDontSee('(Unverified)');
});

test('unverified admin is blocked from saving access levels with a verification popup', function () {
    $admin = User::query()->create([
        'name' => 'Unverified Admin',
        'email' => 'unverified-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => null,
        'level' => 2,
    ])->save();

    $targetUser = User::query()->create([
        'name' => 'Level Target',
        'email' => 'level-target@example.com',
        'password' => 'password',
    ]);

    $targetUser->forceFill([
        'email_verified_at' => now(),
        'level' => 0,
    ])->save();

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('selectedManagedUserId', (string) $targetUser->id)
        ->set('selectedManagedUserLevel', '1')
        ->call('updateSelectedUserLevel')
        ->assertSet('showVerificationRequiredModal', true);

    expect($targetUser->fresh()->level)->toBe(0);
});

test('verified admin can save a user access level', function () {
    $admin = User::query()->create([
        'name' => 'Verified Level Admin',
        'email' => 'verified-level-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $targetUser = User::query()->create([
        'name' => 'Verified Level Target',
        'email' => 'verified-level-target@example.com',
        'password' => 'password',
    ]);

    $targetUser->forceFill([
        'email_verified_at' => now(),
        'level' => 0,
    ])->save();

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('selectedManagedUserId', (string) $targetUser->id)
        ->set('selectedManagedUserLevel', '1')
        ->call('updateSelectedUserLevel')
        ->assertSet('showVerificationRequiredModal', false)
        ->assertDispatched('user-level-updated');

    expect($targetUser->fresh()->level)->toBe(1);
});
