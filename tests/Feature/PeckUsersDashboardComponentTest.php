<?php

use App\Livewire\PeckUsersDashboard;
use App\Models\PeckAlt;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use App\Models\PeckUserContext;
use App\Models\ThunderApiServerToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('peck users dashboard component is discoverable and mountable', function () {
    expect(app('livewire')->exists('peck-users-dashboard'))->toBeTrue();

    $instance = Livewire::test(PeckUsersDashboard::class)->instance();

    expect($instance)->toBeInstanceOf(PeckUsersDashboard::class);
});

test('users can apply filters from the filter modal', function () {
    $memberUser = PeckUser::factory()->create([
        'gaijin_id' => 800001,
        'status' => 'member',
        'tz' => 2,
    ]);

    $otherUser = PeckUser::factory()->create([
        'gaijin_id' => 800002,
        'status' => 'unverified',
        'tz' => -3,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openFilterModal')
        ->set('filterForm.status', 'member')
        ->set('filterForm.tz', '2')
        ->call('applyFilters')
        ->assertSet('showFilterModal', false)
        ->assertSet('filters.status', 'member')
        ->assertSet('filters.tz', 2)
        ->assertSee((string) $memberUser->gaijin_id)
        ->assertDontSee((string) $otherUser->gaijin_id);
});

test('users can clear filters and see all records again', function () {
    $memberUser = PeckUser::factory()->create([
        'gaijin_id' => 800003,
        'status' => 'member',
    ]);

    $otherUser = PeckUser::factory()->create([
        'gaijin_id' => 800004,
        'status' => 'applicant',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openFilterModal')
        ->set('filterForm.status', 'member')
        ->call('applyFilters')
        ->assertSee((string) $memberUser->gaijin_id)
        ->assertDontSee((string) $otherUser->gaijin_id)
        ->call('resetFilters')
        ->assertSet('filters.status', null)
        ->assertSet('showFilterModal', false)
        ->assertSee((string) $memberUser->gaijin_id)
        ->assertSee((string) $otherUser->gaijin_id);
});

test('search does not match users by status', function () {
    $memberUser = PeckUser::factory()->create([
        'gaijin_id' => 800005,
        'status' => 'member',
    ]);

    $unverifiedUser = PeckUser::factory()->create([
        'gaijin_id' => 800006,
        'status' => 'unverified',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->set('search', 'member')
        ->assertDontSee((string) $memberUser->gaijin_id)
        ->assertDontSee((string) $unverifiedUser->gaijin_id);
});

test('users table displays thunderapi usernames when available', function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $memberUser = PeckUser::factory()->create([
        'gaijin_id' => 800010,
        'status' => 'member',
    ]);

    Http::fake([
        'https://thunder.example/v1/users/terse*' => Http::response([
            '800010' => ['nick' => 'AlphaBird'],
        ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->assertSee('AlphaBird')
        ->assertSee((string) $memberUser->gaijin_id);
});

test('authorized users can create a user', function () {
    $admin = User::query()->create([
        'name' => 'Dashboard Admin',
        'email' => 'dashboard-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openCreateUserModal')
        ->set('newUserForm.gaijin_id', '900003')
        ->set('newUserForm.status', 'member')
        ->set('newUserForm.discord_id', '123456789012345678')
        ->set('newUserForm.tz', '0')
        ->call('createUser')
        ->assertHasNoErrors();

    expect(PeckUser::query()->find(900003)?->status)->toBe('member');
});

test('dashboard status dropdown excludes applicant and unverified options', function () {
    $admin = User::query()->create([
        'name' => 'Dashboard Status Admin',
        'email' => 'dashboard-status-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $this->actingAs($admin);

    $component = Livewire::test(PeckUsersDashboard::class)
        ->call('openCreateUserModal');

    expect($component->instance()->editableStatuses())
        ->toBe(['member', 'ex_member']);
});

test('authorized users can save edits and change gaijin id', function () {
    $admin = User::query()->create([
        'name' => 'Dashboard Editor',
        'email' => 'dashboard-editor@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $editableUser = PeckUser::factory()->create([
        'gaijin_id' => 96729719,
        'status' => 'member',
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $editableUser->gaijin_id)
        ->set('form.gaijin_id', '97729719')
        ->set('form.status', 'member')
        ->set('form.discord_id', '123456789012345678')
        ->set('form.tz', '0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('selectedGaijinId', 97729719)
        ->assertDispatched('peck-user-saved');

    expect(PeckUser::query()->find(96729719))->toBeNull();
    expect(PeckUser::query()->find(97729719)?->discord_id)->toBe(123456789012345678);
    expect(PeckUser::query()->find(97729719)?->status)->toBe('member');
});

test('saving a member without a discord id derives unverified status', function () {
    $admin = User::query()->create([
        'name' => 'Status Derivation Admin',
        'email' => 'status-derivation-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $applicantUser = PeckUser::factory()->create([
        'gaijin_id' => 98765432,
        'status' => 'applicant',
        'discord_id' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $applicantUser->gaijin_id)
        ->set('form.status', 'member')
        ->set('form.discord_id', null)
        ->set('form.tz', '0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('peck-user-saved');

    expect(PeckUser::query()->find(98765432)?->status)->toBe('unverified');
});

test('editing an unverified user discord id promotes status to member', function () {
    $admin = User::query()->create([
        'name' => 'Unverified Discord Edit Admin',
        'email' => 'unverified-discord-edit-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $unverifiedUser = PeckUser::factory()->create([
        'gaijin_id' => 98765433,
        'status' => 'unverified',
        'discord_id' => null,
        'tz' => 0,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $unverifiedUser->gaijin_id)
        ->set('form.discord_id', '123456789012345678')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('peck-user-saved');

    expect(PeckUser::query()->find(98765433)?->status)->toBe('member');
    expect(PeckUser::query()->find(98765433)?->discord_id)->toBe(123456789012345678);
});

test('leave info section only shows ex-members and allows leave info edits', function () {
    $admin = User::query()->create([
        'name' => 'Leave Info Admin',
        'email' => 'leave-info-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $exMemberUser = PeckUser::factory()->create([
        'gaijin_id' => 990001,
        'status' => 'ex_member',
    ]);

    $memberUser = PeckUser::factory()->create([
        'gaijin_id' => 990002,
        'status' => 'member',
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'leave_info'])
        ->assertSee((string) $exMemberUser->gaijin_id)
        ->assertDontSee((string) $memberUser->gaijin_id)
        ->call('openLeaveInfoModal', $exMemberUser->gaijin_id)
        ->assertSet('leaveInfoForm.type', PeckLeaveInfo::TYPE_LEFT)
        ->set('leaveInfoForm.type', PeckLeaveInfo::TYPE_LEFT_SERVER)
        ->call('saveLeaveInfo')
        ->assertHasNoErrors()
        ->assertDispatched('peck-leave-info-saved');

    expect(PeckLeaveInfo::query()->find($exMemberUser->gaijin_id)?->type)->toBe(PeckLeaveInfo::TYPE_LEFT_SERVER);
});

test('changing user status to ex_member opens leave info modal when no leave info exists', function () {
    $admin = User::query()->create([
        'name' => 'Status Change Admin',
        'email' => 'status-change-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $memberUser = PeckUser::factory()->create([
        'gaijin_id' => 990010,
        'status' => 'member',
        'tz' => 0,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $memberUser->gaijin_id)
        ->set('form.status', 'ex_member')
        ->set('form.tz', '0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showEditModal', false)
        ->assertSet('showLeaveInfoModal', true)
        ->assertSet('leaveInfoModalFromStatusChange', true)
        ->assertSet('leaveInfoForm.type', PeckLeaveInfo::TYPE_LEFT);

    expect(PeckLeaveInfo::query()->find($memberUser->gaijin_id))->toBeNull();
});

test('changing status away from ex_member removes leave info entry', function () {
    $admin = User::query()->create([
        'name' => 'Ex Member Cleanup Admin',
        'email' => 'ex-member-cleanup-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $exMemberUser = PeckUser::factory()->create([
        'gaijin_id' => 990020,
        'status' => 'ex_member',
        'tz' => 0,
    ]);

    PeckLeaveInfo::query()->create([
        'user_id' => $exMemberUser->gaijin_id,
        'type' => PeckLeaveInfo::TYPE_LEFT,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $exMemberUser->gaijin_id)
        ->set('form.status', 'member')
        ->set('form.tz', '0')
        ->call('save')
        ->assertHasNoErrors();

    expect(PeckLeaveInfo::query()->find($exMemberUser->gaijin_id))->toBeNull();
});

test('alts section can search by master gaijin id', function () {
    $firstMaster = PeckUser::factory()->create([
        'gaijin_id' => 991001,
    ]);

    $secondMaster = PeckUser::factory()->create([
        'gaijin_id' => 991002,
    ]);

    $firstSlave = PeckUser::factory()->create([
        'gaijin_id' => 991003,
    ]);

    $secondSlave = PeckUser::factory()->create([
        'gaijin_id' => 991004,
    ]);

    PeckAlt::query()->create([
        'owner_id' => $firstMaster->gaijin_id,
        'alt_id' => $firstSlave->gaijin_id,
    ]);

    PeckAlt::query()->create([
        'owner_id' => $secondMaster->gaijin_id,
        'alt_id' => $secondSlave->gaijin_id,
    ]);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'alts'])
        ->assertSee((string) $firstMaster->gaijin_id)
        ->assertSee((string) $secondMaster->gaijin_id)
        ->set('altSearch', '991002')
        ->assertSee((string) $secondMaster->gaijin_id)
        ->assertDontSee((string) $firstMaster->gaijin_id);
});

test('adding a master requires selecting at least one slave account', function () {
    $admin = User::query()->create([
        'name' => 'Alts Admin',
        'email' => 'alts-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $masterCandidate = PeckUser::factory()->create([
        'gaijin_id' => 992001,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'alts'])
        ->call('openCreateMasterModal')
        ->set('altFormMasterGaijinId', (string) $masterCandidate->gaijin_id)
        ->call('saveMasterAssignment')
        ->assertHasErrors(['altFormSlaveGaijinIds']);

    expect(
        PeckAlt::query()
            ->where('owner_id', $masterCandidate->gaijin_id)
            ->exists()
    )->toBeFalse();
});

test('selecting an existing master preloads its slave accounts in the modal', function () {
    $admin = User::query()->create([
        'name' => 'Alts Preload Admin',
        'email' => 'alts-preload-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $master = PeckUser::factory()->create([
        'gaijin_id' => 992101,
    ]);

    $firstSlave = PeckUser::factory()->create([
        'gaijin_id' => 992102,
    ]);

    $secondSlave = PeckUser::factory()->create([
        'gaijin_id' => 992103,
    ]);

    PeckAlt::query()->create([
        'owner_id' => $master->gaijin_id,
        'alt_id' => $firstSlave->gaijin_id,
    ]);

    PeckAlt::query()->create([
        'owner_id' => $master->gaijin_id,
        'alt_id' => $secondSlave->gaijin_id,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'alts'])
        ->call('openCreateMasterModal')
        ->set('altFormMasterGaijinId', (string) $master->gaijin_id)
        ->assertSet('editingMasterGaijinId', $master->gaijin_id)
        ->assertSet('altFormSlaveGaijinIds', [
            $firstSlave->gaijin_id,
            $secondSlave->gaijin_id,
        ]);
});

test('set master action reassigns ownership to selected slave and keeps all previous slaves', function () {
    $admin = User::query()->create([
        'name' => 'Alts Editor',
        'email' => 'alts-editor@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $originalMaster = PeckUser::factory()->create([
        'gaijin_id' => 993001,
    ]);

    $firstSlave = PeckUser::factory()->create([
        'gaijin_id' => 993002,
    ]);

    $secondSlave = PeckUser::factory()->create([
        'gaijin_id' => 993003,
    ]);

    PeckAlt::query()->create([
        'owner_id' => $originalMaster->gaijin_id,
        'alt_id' => $firstSlave->gaijin_id,
    ]);

    PeckAlt::query()->create([
        'owner_id' => $originalMaster->gaijin_id,
        'alt_id' => $secondSlave->gaijin_id,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'alts'])
        ->call('openEditMasterModal', $originalMaster->gaijin_id)
        ->call('setMasterFromSlave', $firstSlave->gaijin_id)
        ->call('saveMasterAssignment')
        ->assertHasNoErrors()
        ->assertDispatched('peck-alt-saved');

    $newMasterSlaveIds = PeckAlt::query()
        ->where('owner_id', $firstSlave->gaijin_id)
        ->orderBy('alt_id')
        ->pluck('alt_id')
        ->values()
        ->all();

    expect($newMasterSlaveIds)->toBe([
        $originalMaster->gaijin_id,
        $secondSlave->gaijin_id,
    ]);

    expect(
        PeckAlt::query()
            ->where('owner_id', $originalMaster->gaijin_id)
            ->exists()
    )->toBeFalse();
});

test('context section lists users and hides expired one-time absences until enabled', function () {
    $admin = User::query()->create([
        'name' => 'Context Viewer',
        'email' => 'context-viewer@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 994001,
    ]);

    PeckUserContext::factory()->create([
        'user_id' => $peckUser->gaijin_id,
        'context_id' => 0,
        'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
        'from_date' => '2000-01-01',
        'to_date' => '2000-01-15',
        'comment' => null,
    ]);

    PeckUserContext::factory()->create([
        'user_id' => $peckUser->gaijin_id,
        'context_id' => 1,
        'type' => PeckUserContext::TYPE_MISC,
        'comment' => 'Max rank: 14.7',
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'context'])
        ->assertSee((string) $peckUser->gaijin_id)
        ->call('openContextModal', $peckUser->gaijin_id)
        ->assertSee('misc: Max rank: 14.7')
        ->assertDontSee('absence: 2000.01.01 - 2000.01.15')
        ->set('contextShowExpiredAbsences', true)
        ->assertSee('absence: 2000.01.01 - 2000.01.15');
});

test('authorized users can add split recurring contexts and remove context entries', function () {
    $admin = User::query()->create([
        'name' => 'Context Editor',
        'email' => 'context-editor@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 994101,
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'context'])
        ->call('openContextModal', $peckUser->gaijin_id)
        ->call('openAddContextForm')
        ->set('contextForm.type', PeckUserContext::TYPE_RECURRING_ABSENCE)
        ->set('contextForm.weekdays', [0, 3])
        ->set('contextForm.monthDay', '26')
        ->call('addContext')
        ->assertHasNoErrors()
        ->assertDispatched('peck-context-added')
        ->assertSee('absence: every Mon,Thu')
        ->assertSee('absence: every month on the 26th')
        ->call('removeContext', 0)
        ->assertDontSee('absence: every Mon,Thu');

    $remainingContext = PeckUserContext::query()
        ->where('user_id', $peckUser->gaijin_id)
        ->first();

    expect($remainingContext?->context_id)->toBe(1)
        ->and($remainingContext?->month_day)->toBe(26);
});
