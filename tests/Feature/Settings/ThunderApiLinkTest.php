<?php

use App\Models\ThunderApiToken;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
});

test('thunderapi section shows the not connected state', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSee('ThunderAPI')
        ->assertSee('Not connected');
});

test('user can link a thunderapi account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'fresh-token',
        ], 200),
    ]);

    Livewire::test('pages::settings.profile')
        ->set('thunderEmail', 'thunder@example.com')
        ->set('thunderPassword', 'secret-password')
        ->call('submitThunderLogin')
        ->assertHasNoErrors();

    $token = ThunderApiToken::query()->where('user_id', $user->id)->first();

    expect($token)->not->toBeNull()
        ->and($token?->token)->toBe('fresh-token')
        ->and($token?->expires_at)->toBe(now()->addDay()->timestamp);
});

test('user can link a thunderapi account with two factor authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Http::fake([
        'https://thunder.example/v1/login' => Http::sequence()
            ->push([
                'status' => '2STEP',
                'types' => ['Email'],
                'requestId' => 'req-123',
                'userId' => 456,
            ], 401)
            ->push([
                'status' => 'OK',
                'token' => 'two-factor-token',
            ], 200),
        'https://thunder.example/v1/answer-2fa' => Http::response(['status' => 'OK'], 200),
    ]);

    $component = Livewire::test('pages::settings.profile')
        ->set('thunderEmail', 'thunder@example.com')
        ->set('thunderPassword', 'secret-password')
        ->call('submitThunderLogin');

    $component->assertSet('twoFactorRequired', true);

    $component
        ->set('thunderCode', '123456')
        ->call('submitThunderTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('twoFactorRequired', false);

    $token = ThunderApiToken::query()->where('user_id', $user->id)->first();

    expect($token?->token)->toBe('two-factor-token');
});

test('invalid thunderapi credentials show an error and store nothing', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'FAIL',
            'detail' => 'Login failed',
        ], 400),
    ]);

    Livewire::test('pages::settings.profile')
        ->set('thunderEmail', 'thunder@example.com')
        ->set('thunderPassword', 'secret-password')
        ->call('submitThunderLogin')
        ->assertSet('thunderError', 'ThunderAPI login failed (HTTP 400).');

    expect(ThunderApiToken::query()->exists())->toBeFalse();
});

test('expired token shows the expired state', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    ThunderApiToken::factory()->expired()->create(['user_id' => $user->id]);

    Livewire::test('pages::settings.profile')
        ->assertSee('Expired');
});

test('valid token shows the connected state', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    ThunderApiToken::factory()->create(['user_id' => $user->id]);

    Livewire::test('pages::settings.profile')
        ->assertSee('Connected');
});

test('user can disconnect thunderapi', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    ThunderApiToken::factory()->create(['user_id' => $user->id]);

    Livewire::test('pages::settings.profile')
        ->call('disconnectThunder');

    expect(ThunderApiToken::query()->exists())->toBeFalse();
});

test('each user can only have a single thunderapi token', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'replacement-token',
        ], 200),
    ]);

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'old-token',
    ]);

    Livewire::test('pages::settings.profile')
        ->set('thunderEmail', 'thunder@example.com')
        ->set('thunderPassword', 'secret-password')
        ->call('submitThunderLogin')
        ->assertHasNoErrors();

    expect(ThunderApiToken::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(ThunderApiToken::query()->where('user_id', $user->id)->value('token'))->toBe('replacement-token');
});

test('unreachable thunderapi shows a friendly error when logging in', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Http::fake([
        'https://thunder.example/*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect to server'),
    ]);

    Livewire::test('pages::settings.profile')
        ->set('thunderEmail', 'thunder@example.com')
        ->set('thunderPassword', 'secret-password')
        ->call('submitThunderLogin')
        ->assertSet('thunderError', 'Unable to reach ThunderAPI. Please try again later.')
        ->assertSet('twoFactorRequired', false);

    expect(ThunderApiToken::query()->exists())->toBeFalse();
});

test('unreachable thunderapi shows a friendly error during two factor authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => '2STEP',
            'types' => ['Email'],
            'requestId' => 'req-123',
            'userId' => 456,
        ], 401),
        'https://thunder.example/v1/answer-2fa' => fn () => throw new ConnectionException('cURL error 7: Failed to connect to server'),
    ]);

    $component = Livewire::test('pages::settings.profile')
        ->set('thunderEmail', 'thunder@example.com')
        ->set('thunderPassword', 'secret-password')
        ->call('submitThunderLogin');

    $component->assertSet('twoFactorRequired', true);

    $component
        ->set('thunderCode', '123456')
        ->call('submitThunderTwoFactor')
        ->assertSet('thunderError', 'Unable to reach ThunderAPI. Please try again later.');

    expect(ThunderApiToken::query()->exists())->toBeFalse();
});
