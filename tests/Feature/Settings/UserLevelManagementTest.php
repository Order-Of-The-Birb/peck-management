<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('supervisor users can change access levels of other users', function () {
    $supervisor = createAccessTestUser(level: 2);
    $targetUser = createAccessTestUser(level: 0);

    $this->actingAs($supervisor);

    Livewire::test('pages::settings.profile')
        ->assertSee('User Access Levels')
        ->assertSee('0 = Viewer, 1 = Database access, 2 = Supervisor')
        ->assertDontSee('Level 2 (Supervisor) can manage access. Level 1 has database edit access, and level 0 is read-only.')
        ->set('selectedManagedUserId', (string) $targetUser->id)
        ->set('selectedManagedUserLevel', '2')
        ->call('updateSelectedUserLevel')
        ->assertHasNoErrors()
        ->assertDispatched('user-level-updated');

    expect($targetUser->refresh()->level)->toBe(2);
});

test('non-supervisor users cannot change access levels', function () {
    $levelOneUser = createAccessTestUser(level: 1);
    $targetUser = createAccessTestUser(level: 0);

    $this->actingAs($levelOneUser);

    Livewire::test('pages::settings.profile')
        ->assertDontSee('User Access Levels')
        ->set('selectedManagedUserId', (string) $targetUser->id)
        ->set('selectedManagedUserLevel', '2')
        ->call('updateSelectedUserLevel')
        ->assertForbidden();

    expect($targetUser->refresh()->level)->toBe(0);
});

function createAccessTestUser(int $level): User
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
