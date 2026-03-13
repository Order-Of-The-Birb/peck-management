<?php

use App\Livewire\PeckUsersDashboard;
use App\Models\PeckUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = createDashboardUser();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertSee('All users - '.config('app.name'));
});

test('authenticated users can visit the missing discord dashboard tab', function () {
    $user = createDashboardUser();
    $this->actingAs($user);

    $response = $this->get(route('dashboard.members-missing-discord'));
    $response
        ->assertOk()
        ->assertSee('Missing Discord IDs - '.config('app.name'));
});

test('authenticated users can visit the unverified users dashboard tab', function () {
    $user = createDashboardUser();
    $this->actingAs($user);

    $response = $this->get(route('dashboard.unverified-users'));
    $response
        ->assertOk()
        ->assertSee('Unverified users - '.config('app.name'));
});

test('authenticated users can visit the alt accounts tab', function () {
    $user = createDashboardUser();
    $this->actingAs($user);

    $response = $this->get(route('dashboard.alt-accounts'));
    $response
        ->assertOk()
        ->assertSee('Alt accounts - '.config('app.name'));
});

test('dashboard displays peck users', function () {
    $user = createDashboardUser();
    $peckUser = PeckUser::factory()->create([
        'username' => 'peck-member-1',
        'status' => 'member',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('peck-member-1')
        ->assertSee((string) $peckUser->gaijin_id);
});

test('peck user data can be updated from the dashboard component', function () {
    $this->actingAs(createDashboardUser(level: 1));

    $initiator = PeckUser::factory()->create();
    $peckUser = PeckUser::factory()->create([
        'status' => 'applicant',
        'discord_id' => null,
        'tz' => null,
        'joindate' => null,
        'initiator' => null,
    ]);
    $previousGaijinId = $peckUser->gaijin_id;
    $updatedGaijinId = $previousGaijinId + 1000000;

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $peckUser->gaijin_id)
        ->assertSet('showEditModal', true)
        ->set('form.gaijin_id', (string) $updatedGaijinId)
        ->set('form.username', 'updated-username')
        ->set('form.status', 'member')
        ->set('form.discord_id', '123456789')
        ->set('form.tz', '3')
        ->set('form.joindate', '2026-01-15T08:30')
        ->set('form.initiator', (string) $initiator->gaijin_id)
        ->call('save')
        ->assertHasNoErrors();

    expect(PeckUser::query()->find($previousGaijinId))->toBeNull();

    $peckUser = PeckUser::query()->findOrFail($updatedGaijinId);

    expect($peckUser->gaijin_id)->toBe($updatedGaijinId);
    expect($peckUser->username)->toBe('updated-username');
    expect($peckUser->status)->toBe('member');
    expect($peckUser->discord_id)->toBe(123456789);
    expect($peckUser->tz)->toBe(3);
    expect($peckUser->joindate?->format('Y-m-d H:i:s'))->toBe('2026-01-15 08:30:00');
    expect($peckUser->initiator)->toBe($initiator->gaijin_id);
});

test('dashboard component can create peck user from modal form', function () {
    $this->actingAs(createDashboardUser(level: 1));

    $initiator = PeckUser::factory()->create();

    Livewire::test(PeckUsersDashboard::class)
        ->call('openCreateUserModal')
        ->assertSet('showCreateUserModal', true)
        ->set('newUserForm.gaijin_id', '900001')
        ->set('newUserForm.username', 'newly-created-user')
        ->set('newUserForm.status', 'unverified')
        ->set('newUserForm.discord_id', '987654321')
        ->set('newUserForm.tz', '1')
        ->set('newUserForm.joindate', '2026-02-01T10:15')
        ->set('newUserForm.initiator', (string) $initiator->gaijin_id)
        ->call('createUser')
        ->assertSet('showCreateUserModal', false)
        ->assertSet('showEditModal', false)
        ->assertSet('selectedGaijinId', null)
        ->assertHasNoErrors();

    $createdUser = PeckUser::query()->findOrFail(900001);

    expect($createdUser->username)->toBe('newly-created-user');
    expect($createdUser->status)->toBe('unverified');
    expect($createdUser->discord_id)->toBe(987654321);
    expect($createdUser->tz)->toBe(1);
    expect($createdUser->joindate?->format('Y-m-d H:i:s'))->toBe('2026-02-01 10:15:00');
    expect($createdUser->initiator)->toBe($initiator->gaijin_id);
});

test('supervisor level users can edit peck users', function () {
    $this->actingAs(createDashboardUser(level: 2));

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 910001,
        'username' => 'supervisor-edit-user',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $peckUser->gaijin_id)
        ->assertSet('showEditModal', true)
        ->set('form.username', 'supervisor-updated')
        ->set('form.status', 'member')
        ->set('form.tz', '0')
        ->call('save')
        ->assertHasNoErrors();

    expect(PeckUser::query()->findOrFail($peckUser->gaijin_id)->username)->toBe('supervisor-updated');
});

test('dashboard component can sort peck users by username and join date', function () {
    $this->actingAs(createDashboardUser());

    PeckUser::factory()->create([
        'gaijin_id' => 1001,
        'username' => 'beta-user',
        'joindate' => '2025-01-01 00:00:00',
    ]);
    PeckUser::factory()->create([
        'gaijin_id' => 1002,
        'username' => 'alpha-user',
        'joindate' => '2024-01-01 00:00:00',
    ]);
    PeckUser::factory()->create([
        'gaijin_id' => 1003,
        'username' => 'gamma-user',
        'joindate' => '2026-01-01 00:00:00',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('sort', 'username')
        ->assertSet('sortBy', 'username')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['alpha-user', 'beta-user', 'gamma-user'])
        ->call('sort', 'username')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['gamma-user', 'beta-user', 'alpha-user'])
        ->call('sort', 'joindate')
        ->assertSet('sortBy', 'joindate')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['alpha-user', 'beta-user', 'gamma-user']);
});

test('dashboard component can filter members without discord id', function () {
    $this->actingAs(createDashboardUser());

    PeckUser::factory()->create([
        'gaijin_id' => 2001,
        'username' => 'member-no-discord',
        'status' => 'member',
        'discord_id' => null,
    ]);
    PeckUser::factory()->create([
        'gaijin_id' => 2002,
        'username' => 'member-with-discord',
        'status' => 'member',
        'discord_id' => 123456789,
    ]);
    PeckUser::factory()->create([
        'gaijin_id' => 2003,
        'username' => 'applicant-no-discord',
        'status' => 'applicant',
        'discord_id' => null,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('toggleMembersWithoutDiscordOnly')
        ->assertSet('membersWithoutDiscordOnly', true)
        ->assertViewHas('peckUsers', function ($peckUsers): bool {
            $usernames = collect($peckUsers->items())->pluck('username');

            return $usernames->contains('member-no-discord')
                && ! $usernames->contains('member-with-discord')
                && ! $usernames->contains('applicant-no-discord');
        });
});

test('missing discord dashboard tab is pre-filtered', function () {
    $this->actingAs(createDashboardUser());

    PeckUser::factory()->create([
        'gaijin_id' => 3001,
        'username' => 'member-no-discord-prefilter',
        'status' => 'member',
        'discord_id' => null,
    ]);
    PeckUser::factory()->create([
        'gaijin_id' => 3002,
        'username' => 'member-with-discord-prefilter',
        'status' => 'member',
        'discord_id' => 777777,
    ]);

    Livewire::test(PeckUsersDashboard::class, ['membersWithoutDiscordOnly' => true])
        ->assertSet('membersWithoutDiscordOnly', true)
        ->assertViewHas('peckUsers', function ($peckUsers): bool {
            $usernames = collect($peckUsers->items())->pluck('username');

            return $usernames->contains('member-no-discord-prefilter')
                && ! $usernames->contains('member-with-discord-prefilter');
        });
});

test('unverified dashboard tab is pre-filtered', function () {
    $this->actingAs(createDashboardUser());

    PeckUser::factory()->create([
        'gaijin_id' => 4001,
        'username' => 'unverified-prefilter',
        'status' => 'unverified',
        'discord_id' => null,
    ]);
    PeckUser::factory()->create([
        'gaijin_id' => 4002,
        'username' => 'member-prefilter',
        'status' => 'member',
        'discord_id' => null,
    ]);

    Livewire::test(PeckUsersDashboard::class, ['unverifiedOnly' => true])
        ->assertSet('unverifiedOnly', true)
        ->assertViewHas('peckUsers', function ($peckUsers): bool {
            $usernames = collect($peckUsers->items())->pluck('username');

            return $usernames->contains('unverified-prefilter')
                && ! $usernames->contains('member-prefilter');
        });
});

test('non-admin users can view dashboard but cannot edit peck users', function () {
    $this->actingAs(createDashboardUser(level: 0));

    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 5001,
        'username' => 'readonly-peck-user',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->assertDontSee('Add User')
        ->call('openCreateUserModal')
        ->assertForbidden();

    Livewire::test(PeckUsersDashboard::class)
        ->call('selectUser', $peckUser->gaijin_id)
        ->assertForbidden();
});

function createDashboardUser(int $level = 0): User
{
    $user = new User;
    $user->forceFill([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'level' => $level,
    ]);

    $user->save();

    return $user;
}
