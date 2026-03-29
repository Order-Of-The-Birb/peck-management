<?php

use App\Livewire\PeckAltsDashboard;
use App\Models\PeckAlt;
use App\Models\PeckUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('guests are redirected to login from alt accounts tab', function () {
    $response = $this->get(route('dashboard.alt-accounts'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view alt accounts tab', function () {
    $this->actingAs(createAltAccountsTestUser());

    $response = $this->get(route('dashboard.alt-accounts'));

    $response->assertOk();
});

test('alt account mappings can be created from the alt accounts component', function () {
    $this->actingAs(createAltAccountsTestUser(level: 1));

    $ownerInitiator = PeckUser::factory()->create([
        'gaijin_id' => 701010,
        'username' => 'owner-initiator',
    ]);
    $ownerUser = PeckUser::factory()->create([
        'gaijin_id' => 701001,
        'username' => 'owner-user',
        'discord_id' => 123456789,
        'tz' => 4,
        'status' => 'member',
        'joindate' => '2025-04-01 12:00:00',
        'initiator' => $ownerInitiator->gaijin_id,
    ]);
    $altUser = PeckUser::factory()->create([
        'gaijin_id' => 701002,
        'username' => 'alt-user',
        'discord_id' => 999111222,
        'tz' => -2,
        'status' => 'unverified',
        'joindate' => null,
        'initiator' => null,
    ]);

    Livewire::test(PeckAltsDashboard::class)
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true)
        ->set('newAltForm.alt_id', (string) $altUser->gaijin_id)
        ->set('newAltForm.owner_id', (string) $ownerUser->gaijin_id)
        ->call('createAlt')
        ->assertSet('showCreateModal', false)
        ->assertSet('showEditModal', false)
        ->assertSet('selectedAltId', null)
        ->assertHasNoErrors();

    $peckAlt = PeckAlt::query()->findOrFail($altUser->gaijin_id);
    $altUser->refresh();

    expect($peckAlt->owner_id)->toBe($ownerUser->gaijin_id);
    expect($altUser->discord_id)->toBe($ownerUser->discord_id);
    expect($altUser->tz)->toBe($ownerUser->tz);
    expect($altUser->status)->toBe('alt');
    expect($altUser->joindate?->format('Y-m-d H:i:s'))->toBe($ownerUser->joindate?->format('Y-m-d H:i:s'));
    expect($altUser->initiator)->toBe($ownerUser->initiator);
});

test('alt account mappings can be updated from the alt accounts component', function () {
    $this->actingAs(createAltAccountsTestUser(level: 1));

    $ownerInitiator = PeckUser::factory()->create([
        'gaijin_id' => 702010,
        'username' => 'primary-initiator',
    ]);
    $ownerUser = PeckUser::factory()->create([
        'gaijin_id' => 702001,
        'username' => 'primary-owner',
        'discord_id' => 111111111,
        'tz' => 1,
        'status' => 'member',
        'joindate' => '2025-02-01 08:00:00',
        'initiator' => $ownerInitiator->gaijin_id,
    ]);
    $newOwnerInitiator = PeckUser::factory()->create([
        'gaijin_id' => 702011,
        'username' => 'secondary-initiator',
    ]);
    $newOwnerUser = PeckUser::factory()->create([
        'gaijin_id' => 702002,
        'username' => 'secondary-owner',
        'discord_id' => 222222222,
        'tz' => 9,
        'status' => 'member',
        'joindate' => '2025-03-01 09:30:00',
        'initiator' => $newOwnerInitiator->gaijin_id,
    ]);
    $altUser = PeckUser::factory()->create([
        'gaijin_id' => 702003,
        'username' => 'alt-target',
        'discord_id' => 333333333,
        'tz' => -1,
        'status' => 'unverified',
        'joindate' => null,
        'initiator' => null,
    ]);

    PeckAlt::query()->create([
        'alt_id' => $altUser->gaijin_id,
        'owner_id' => $ownerUser->gaijin_id,
    ]);

    Livewire::test(PeckAltsDashboard::class)
        ->call('selectAlt', $altUser->gaijin_id)
        ->assertSet('showEditModal', true)
        ->set('form.owner_id', (string) $newOwnerUser->gaijin_id)
        ->call('save')
        ->assertSet('showEditModal', false)
        ->assertSet('selectedAltId', null)
        ->assertHasNoErrors();

    $peckAlt = PeckAlt::query()->findOrFail($altUser->gaijin_id);
    $altUser->refresh();

    expect($peckAlt->owner_id)->toBe($newOwnerUser->gaijin_id);
    expect($altUser->discord_id)->toBe($newOwnerUser->discord_id);
    expect($altUser->tz)->toBe($newOwnerUser->tz);
    expect($altUser->status)->toBe('alt');
    expect($altUser->joindate?->format('Y-m-d H:i:s'))->toBe($newOwnerUser->joindate?->format('Y-m-d H:i:s'));
    expect($altUser->initiator)->toBe($newOwnerUser->initiator);
});

test('supervisor level users can edit alt account mappings', function () {
    $this->actingAs(createAltAccountsTestUser(level: 2));

    $ownerUser = PeckUser::factory()->create([
        'gaijin_id' => 704001,
        'username' => 'supervisor-owner',
    ]);
    $newOwnerUser = PeckUser::factory()->create([
        'gaijin_id' => 704002,
        'username' => 'supervisor-new-owner',
    ]);
    $altUser = PeckUser::factory()->create([
        'gaijin_id' => 704003,
        'username' => 'supervisor-alt',
    ]);

    PeckAlt::query()->create([
        'alt_id' => $altUser->gaijin_id,
        'owner_id' => $ownerUser->gaijin_id,
    ]);

    Livewire::test(PeckAltsDashboard::class)
        ->call('selectAlt', $altUser->gaijin_id)
        ->set('form.owner_id', (string) $newOwnerUser->gaijin_id)
        ->call('save')
        ->assertSet('showEditModal', false)
        ->assertSet('selectedAltId', null)
        ->assertHasNoErrors();

    expect(PeckAlt::query()->findOrFail($altUser->gaijin_id)->owner_id)->toBe($newOwnerUser->gaijin_id);
});

test('non-admin users can view alt accounts but cannot edit mappings', function () {
    $this->actingAs(createAltAccountsTestUser(level: 0));

    $ownerUser = PeckUser::factory()->create([
        'gaijin_id' => 703001,
        'username' => 'readonly-owner',
    ]);
    $altUser = PeckUser::factory()->create([
        'gaijin_id' => 703002,
        'username' => 'readonly-alt',
    ]);

    PeckAlt::query()->create([
        'alt_id' => $altUser->gaijin_id,
        'owner_id' => $ownerUser->gaijin_id,
    ]);

    Livewire::test(PeckAltsDashboard::class)
        ->assertDontSee('Add Alt Mapping')
        ->call('openCreateModal')
        ->assertForbidden();

    Livewire::test(PeckAltsDashboard::class)
        ->call('selectAlt', $altUser->gaijin_id)
        ->assertForbidden();
});

test('alt account mappings can be deleted from the alt accounts component', function () {
    $this->actingAs(createAltAccountsTestUser(level: 1));

    $ownerUser = PeckUser::factory()->create([
        'gaijin_id' => 705001,
        'username' => 'delete-owner',
    ]);
    $altUser = PeckUser::factory()->create([
        'gaijin_id' => 705002,
        'username' => 'delete-alt',
    ]);

    PeckAlt::query()->create([
        'alt_id' => $altUser->gaijin_id,
        'owner_id' => $ownerUser->gaijin_id,
    ]);

    Livewire::test(PeckAltsDashboard::class)
        ->call('selectAlt', $altUser->gaijin_id)
        ->assertSet('showEditModal', true)
        ->call('deleteSelectedAlt')
        ->assertSet('showEditModal', false)
        ->assertSet('selectedAltId', null)
        ->assertHasNoErrors();

    expect(PeckAlt::query()->find($altUser->gaijin_id))->toBeNull();
});

function createAltAccountsTestUser(int $level = 0): User
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
