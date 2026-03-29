<?php

use App\Livewire\PeckUsersDashboard;
use App\Models\Officer;
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
