<?php

use App\Models\ApiKey;
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

test('admin settings page displays the logged in user api token', function () {
    $user = User::query()->create([
        'name' => 'Admin Token User',
        'email' => 'admin-token@example.com',
        'password' => 'password',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    ApiKey::query()->create([
        'owner' => $user->id,
        'key' => 'visible-rest-api-token',
    ]);

    $this->actingAs($user);

    $this->get(route('admin.edit'))
        ->assertOk()
        ->assertSee('visible-rest-api-token');
});

test('admin settings creates api token for logged in user when missing', function () {
    $user = User::query()->create([
        'name' => 'Admin No Token User',
        'email' => 'admin-no-token@example.com',
        'password' => 'password',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    $this->actingAs($user);

    $apiToken = Livewire::test('pages::settings.admin')->get('apiToken');

    expect($apiToken)->toBeString()->not->toBeEmpty()
        ->and(ApiKey::query()->where('owner', $user->id)->value('key'))->toBe($apiToken);
});
