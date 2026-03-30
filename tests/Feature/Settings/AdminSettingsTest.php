<?php

use App\Models\ApiKey;
use App\Models\Officer;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use App\Models\User;
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

test('officer add modal pre-fills selected user from officer search', function () {
    $admin = User::query()->create([
        'name' => 'Admin Officer Search',
        'email' => 'admin-officer-search@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 910001,
        'username' => 'searchable_officer_user',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('officerSearch', $peckUser->username)
        ->call('openAddOfficerModal')
        ->assertSet('newOfficerForm.gaijin_id', (string) $peckUser->gaijin_id);
});

test('switching commander rank swaps the existing commander to officer rank', function () {
    $admin = User::query()->create([
        'name' => 'Admin Commander Switch',
        'email' => 'admin-commander-switch@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $currentCommander = PeckUser::factory()->create([
        'gaijin_id' => 910010,
        'username' => 'current_commander',
    ]);

    $newCommander = PeckUser::factory()->create([
        'gaijin_id' => 910011,
        'username' => 'new_commander',
    ]);

    Officer::query()->create([
        'gaijin_id' => $currentCommander->gaijin_id,
        'rank' => Officer::RANK_COMMANDER,
    ]);

    Officer::query()->create([
        'gaijin_id' => $newCommander->gaijin_id,
        'rank' => Officer::RANK_OFFICER,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->call('attemptOfficerRankUpdate', $newCommander->gaijin_id, Officer::RANK_COMMANDER)
        ->assertSet('showRankSwitchModal', true)
        ->call('confirmOfficerRankSwitch')
        ->assertSet('showRankSwitchModal', false);

    expect(Officer::query()->find($newCommander->gaijin_id)?->rank)->toBe(Officer::RANK_COMMANDER);
    expect(Officer::query()->find($currentCommander->gaijin_id)?->rank)->toBe(Officer::RANK_OFFICER);
    expect(Officer::query()->where('rank', Officer::RANK_COMMANDER)->count())->toBe(1);
});

test('switching deputy from add flow sets previous deputy to retired when they have leave info', function () {
    $admin = User::query()->create([
        'name' => 'Admin Deputy Retire Switch',
        'email' => 'admin-deputy-retire-switch@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $currentDeputy = PeckUser::factory()->create([
        'gaijin_id' => 910020,
        'username' => 'current_deputy',
    ]);

    $replacementDeputy = PeckUser::factory()->create([
        'gaijin_id' => 910021,
        'username' => 'replacement_deputy',
    ]);

    Officer::query()->create([
        'gaijin_id' => $currentDeputy->gaijin_id,
        'rank' => Officer::RANK_DEPUTY,
    ]);

    PeckLeaveInfo::query()->create([
        'user_id' => $currentDeputy->gaijin_id,
        'type' => PeckLeaveInfo::TYPE_LEFT,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('newOfficerForm.gaijin_id', (string) $replacementDeputy->gaijin_id)
        ->set('newOfficerForm.rank', Officer::RANK_DEPUTY)
        ->call('createOfficer')
        ->assertSet('showRankSwitchModal', true)
        ->call('confirmOfficerRankSwitch')
        ->assertSet('showRankSwitchModal', false);

    expect(Officer::query()->find($replacementDeputy->gaijin_id)?->rank)->toBe(Officer::RANK_DEPUTY);
    expect(Officer::query()->find($currentDeputy->gaijin_id)?->rank)->toBeNull();
    expect(Officer::query()->where('rank', Officer::RANK_DEPUTY)->count())->toBe(1);
});

test('switching deputy from add flow sets previous deputy to officer when they have no leave info', function () {
    $admin = User::query()->create([
        'name' => 'Admin Deputy Officer Switch',
        'email' => 'admin-deputy-officer-switch@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $currentDeputy = PeckUser::factory()->create([
        'gaijin_id' => 910030,
        'username' => 'current_deputy_no_leave',
    ]);

    $replacementDeputy = PeckUser::factory()->create([
        'gaijin_id' => 910031,
        'username' => 'replacement_deputy_no_leave',
    ]);

    Officer::query()->create([
        'gaijin_id' => $currentDeputy->gaijin_id,
        'rank' => Officer::RANK_DEPUTY,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::settings.admin')
        ->set('newOfficerForm.gaijin_id', (string) $replacementDeputy->gaijin_id)
        ->set('newOfficerForm.rank', Officer::RANK_DEPUTY)
        ->call('createOfficer')
        ->assertSet('showRankSwitchModal', true)
        ->call('confirmOfficerRankSwitch')
        ->assertSet('showRankSwitchModal', false);

    expect(Officer::query()->find($replacementDeputy->gaijin_id)?->rank)->toBe(Officer::RANK_DEPUTY);
    expect(Officer::query()->find($currentDeputy->gaijin_id)?->rank)->toBe(Officer::RANK_OFFICER);
    expect(Officer::query()->where('rank', Officer::RANK_DEPUTY)->count())->toBe(1);
});
