<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('user can generate an api token from profile settings', function () {
    $user = createSettingsUser([
        'api_token' => null,
        'api_token_plain' => null,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.profile')
        ->call('resetApiToken')
        ->assertHasNoErrors()
        ->assertDispatched('api-token-reset')
        ->assertSee('Copy Token');

    $generatedToken = $component->get('apiToken');
    $user->refresh();

    expect($generatedToken)->toBeString();
    expect($generatedToken)->toStartWith('peck_');
    expect($user->api_token_plain)->toBe($generatedToken);
    expect(hash('sha256', $generatedToken))->toBe($user->api_token);
});

test('user can reset an existing api token from profile settings', function () {
    $originalToken = 'peck_original_token';
    $user = createSettingsUser([
        'api_token' => hash('sha256', $originalToken),
        'api_token_plain' => $originalToken,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.profile')
        ->assertSet('apiToken', $originalToken)
        ->assertSee('Reset Token')
        ->call('resetApiToken')
        ->assertHasNoErrors()
        ->assertDispatched('api-token-reset');

    $updatedToken = $component->get('apiToken');
    $user->refresh();

    expect($updatedToken)->toBeString();
    expect($updatedToken)->not->toBe($originalToken);
    expect($user->api_token_plain)->toBe($updatedToken);
    expect(hash('sha256', $updatedToken))->toBe($user->api_token);
});

function createSettingsUser(array $attributes = []): User
{
    $user = new User;
    $user->forceFill(array_merge([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'level' => 0,
    ], $attributes));

    $user->save();

    return $user;
}
