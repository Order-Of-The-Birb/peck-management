<?php

use App\Livewire\PeckUsersDashboard;
use App\Models\Officer;
use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use App\Models\User;
use Livewire\Livewire;

test('peck users dashboard component is discoverable and mountable', function () {
    expect(app('livewire')->exists('peck-users-dashboard'))->toBeTrue();

    $instance = Livewire::test(PeckUsersDashboard::class)->instance();

    expect($instance)->toBeInstanceOf(PeckUsersDashboard::class);
});

test('users can apply filters from the filter modal', function () {
    $memberUser = PeckUser::factory()->create([
        'username' => 'member_target',
        'status' => 'member',
        'tz' => 2,
        'joindate' => '2025-02-01 00:00:00',
    ]);

    $otherUser = PeckUser::factory()->create([
        'username' => 'other_target',
        'status' => 'unverified',
        'tz' => -3,
        'joindate' => '2024-08-10 00:00:00',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openFilterModal')
        ->set('filterForm.status', 'member')
        ->set('filterForm.joined_after', '2025-01-01')
        ->call('applyFilters')
        ->assertSet('showFilterModal', false)
        ->assertSet('filters.status', 'member')
        ->assertSet('filters.joined_after', '2025-01-01')
        ->assertSee($memberUser->username)
        ->assertDontSee($otherUser->username);
});

test('users can clear filters and see all records again', function () {
    $memberUser = PeckUser::factory()->create([
        'username' => 'reset_member',
        'status' => 'member',
    ]);

    $otherUser = PeckUser::factory()->create([
        'username' => 'reset_other',
        'status' => 'applicant',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openFilterModal')
        ->set('filterForm.status', 'member')
        ->call('applyFilters')
        ->assertSee($memberUser->username)
        ->assertDontSee($otherUser->username)
        ->call('resetFilters')
        ->assertSet('filters.status', null)
        ->assertSet('showFilterModal', false)
        ->assertSee($memberUser->username)
        ->assertSee($otherUser->username);
});

test('search does not match users by status', function () {
    $memberUser = PeckUser::factory()->create([
        'username' => 'alpha_search_target',
        'status' => 'member',
    ]);

    $unverifiedUser = PeckUser::factory()->create([
        'username' => 'beta_search_target',
        'status' => 'unverified',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->set('search', 'member')
        ->assertDontSee($memberUser->username)
        ->assertDontSee($unverifiedUser->username);
});

test('create user only accepts initiators from the officers table', function () {
    $admin = User::query()->create([
        'name' => 'Dashboard Admin',
        'email' => 'dashboard-admin@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $officerUser = PeckUser::factory()->create([
        'gaijin_id' => 900001,
        'username' => 'authorized_initiator',
    ]);

    Officer::factory()->create([
        'gaijin_id' => $officerUser->gaijin_id,
        'rank' => 'Commander',
    ]);

    $nonOfficerUser = PeckUser::factory()->create([
        'gaijin_id' => 900002,
        'username' => 'unauthorized_initiator',
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openCreateUserModal')
        ->assertSeeHtml('value="'.$officerUser->gaijin_id.'"')
        ->assertDontSeeHtml('value="'.$nonOfficerUser->gaijin_id.'"')
        ->set('newUserForm.gaijin_id', '900003')
        ->set('newUserForm.username', 'new_dashboard_user')
        ->set('newUserForm.status', 'member')
        ->set('newUserForm.initiator', (string) $nonOfficerUser->gaijin_id)
        ->call('createUser')
        ->assertHasErrors(['newUserForm.initiator' => 'exists'])
        ->set('newUserForm.initiator', (string) $officerUser->gaijin_id)
        ->call('createUser')
        ->assertHasNoErrors();

    expect(PeckUser::query()->find(900003)?->initiator)->toBe($officerUser->gaijin_id);
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
        'username' => 'ntechnical',
        'status' => 'member',
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $editableUser->gaijin_id)
        ->set('form.gaijin_id', '97729719')
        ->set('form.username', 'ntechnical_updated')
        ->set('form.status', 'applicant')
        ->set('form.tz', '0')
        ->set('form.initiator', null)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('selectedGaijinId', 97729719)
        ->assertDispatched('peck-user-saved');

    expect(PeckUser::query()->find(96729719))->toBeNull();
    expect(PeckUser::query()->find(97729719)?->username)->toBe('ntechnical_updated');
    expect(PeckUser::query()->find(97729719)?->status)->toBe('applicant');
});

test('authorized users can save applicant users without status validation errors', function () {
    $admin = User::query()->create([
        'name' => 'Applicant Status Editor',
        'email' => 'applicant-status-editor@example.com',
        'password' => 'password',
    ]);

    $admin->forceFill([
        'email_verified_at' => now(),
        'level' => 1,
    ])->save();

    $applicantUser = PeckUser::factory()->create([
        'gaijin_id' => 98765432,
        'username' => 'applicant_account_user',
        'status' => 'applicant',
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $applicantUser->gaijin_id)
        ->set('form.status', 'member')
        ->set('form.tz', '0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('peck-user-saved');

    expect(PeckUser::query()->find(98765432)?->status)->toBe('member');
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
        'username' => 'leave_info_target',
        'status' => 'ex_member',
    ]);

    $memberUser = PeckUser::factory()->create([
        'gaijin_id' => 990002,
        'username' => 'leave_info_hidden',
        'status' => 'member',
    ]);

    $this->actingAs($admin);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'leave_info'])
        ->assertSee($exMemberUser->username)
        ->assertDontSee($memberUser->username)
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
        'username' => 'status_change_target',
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
        'username' => 'cleanup_target',
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
