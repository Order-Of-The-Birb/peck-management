<?php

use App\Actions\UpdateEnvironmentFile;
use App\Models\ThunderApiServerToken;
use App\Models\ThunderApiToken;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\mock;

function createAdminForSquadronLookup(): User
{
    $user = User::factory()->create();

    $user->forceFill([
        'email_verified_at' => now(),
        'level' => 2,
    ])->save();

    return $user;
}

beforeEach(function () {
    config()->set('peck.thunderapi_server.email', null);
    config()->set('peck.thunderapi_server.password', null);
});

test('squadron id lookup card is hidden once the squadron id is configured', function () {
    config()->set('peck.squadron_id', '123');

    $this->actingAs(createAdminForSquadronLookup());

    Http::fake();

    Livewire::test('pages::settings.admin')
        ->assertDontSee('Squadron ID Lookup');
});

test('squadron id lookup card is blurred when thunderapi is not linked', function () {
    config()->set('peck.squadron_id', null);

    $this->actingAs(createAdminForSquadronLookup());

    Http::fake();

    Livewire::test('pages::settings.admin')
        ->assertSee('Squadron ID Lookup')
        ->assertSee('only available after connecting your ThunderAPI account');
});

test('squadron id lookup searches squadrons by name and tag', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');

    $admin = createAdminForSquadronLookup();

    $this->actingAs($admin);

    ThunderApiToken::factory()->create([
        'user_id' => $admin->id,
        'token' => 'lookup-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            ['_id' => '123', 'tag' => 'PECK', 'name' => 'Order Of The Birb'],
            ['_id' => '456', 'tag' => 'OTHER', 'name' => 'Another Squadron'],
        ], 200),
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSee('Squadron ID Lookup')
        ->assertSeeText('123 PECK Order Of The Birb')
        ->assertSeeText('456 OTHER Another Squadron');

    Http::assertSent(function ($request): bool {
        return str_starts_with($request->url(), 'https://thunder.example/v1/clans/search/')
            && $request->data()['clanName'] === 'Order Of The Birb'
            && $request->data()['clanTag'] === 'Order Of The Birb';
    });
});

test('squadron id lookup shows an informative message when no squadrons match', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');

    $admin = createAdminForSquadronLookup();

    $this->actingAs($admin);

    ThunderApiToken::factory()->create([
        'user_id' => $admin->id,
        'token' => 'lookup-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([], 200),
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSee('Squadron ID Lookup')
        ->assertSee('No squadron could be found with this search. Try refining your search query.');
});

test('squadron id lookup blurs the card when thunderapi is unreachable', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');

    $admin = createAdminForSquadronLookup();

    $this->actingAs($admin);

    ThunderApiToken::factory()->create([
        'user_id' => $admin->id,
        'token' => 'lookup-token',
    ]);

    Http::fake([
        'https://thunder.example/*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect to server'),
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSee('Squadron ID Lookup')
        ->assertSee('Unable to reach ThunderAPI. Please try again later.');
});

test('squadron id lookup invalidates the token when thunderapi returns 401', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');

    $admin = createAdminForSquadronLookup();

    $this->actingAs($admin);

    $token = ThunderApiToken::factory()->create([
        'user_id' => $admin->id,
        'token' => 'stale-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            'detail' => 'User not found',
        ], 401),
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSee('Squadron ID Lookup')
        ->assertSee('Your ThunderAPI token is no longer valid. Please reconnect your account.');

    $token->refresh();

    expect($token->isExpired())->toBeTrue();
});

test('selecting a squadron opens the confirmation modal', function () {
    config()->set('peck.squadron_id', null);

    $this->actingAs(createAdminForSquadronLookup());

    Http::fake();

    Livewire::test('pages::settings.admin')
        ->set('squadronLookupResults', [
            ['_id' => '123', 'tag' => 'PECK', 'name' => 'Order Of The Birb'],
        ])
        ->call('requestSquadronSelection', '123')
        ->assertSet('showSquadronSelectionModal', true)
        ->assertSet('pendingSquadronSelection._id', '123');
});

test('confirming a squadron selection saves the squadron id', function () {
    config()->set('peck.squadron_id', null);

    $this->actingAs(createAdminForSquadronLookup());

    Http::fake();

    mock(UpdateEnvironmentFile::class)
        ->shouldReceive('set')
        ->once()
        ->with('SQUADRON_ID', '123');

    Livewire::test('pages::settings.admin')
        ->set('pendingSquadronSelection', [
            '_id' => '123',
            'tag' => 'PECK',
            'name' => 'Order Of The Birb',
        ])
        ->call('confirmSquadronSelection')
        ->assertSet('showSquadronSelectionModal', false)
        ->assertDispatched('squadron-id-configured');

    expect(config('peck.squadron_id'))->toBe('123');
});

test('squadron id lookup falls back to the server-wide login when the admin has no personal token', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $this->actingAs(createAdminForSquadronLookup());

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            ['_id' => '123', 'tag' => 'PECK', 'name' => 'Order Of The Birb'],
        ], 200),
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSee('Squadron ID Lookup')
        ->assertDontSee('only available after connecting your ThunderAPI account')
        ->assertSeeText('123 PECK Order Of The Birb');

    Http::assertSent(function ($request): bool {
        return str_starts_with($request->url(), 'https://thunder.example/v1/clans/search/')
            && $request->hasHeader('Authorization', 'Bearer server-token');
    });
});

test('squadron id lookup prefers the admin personal token over the server-wide login', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $admin = createAdminForSquadronLookup();

    $this->actingAs($admin);

    ThunderApiToken::factory()->create([
        'user_id' => $admin->id,
        'token' => 'user-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            ['_id' => '123', 'tag' => 'PECK', 'name' => 'Order Of The Birb'],
        ], 200),
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSeeText('123 PECK Order Of The Birb');

    Http::assertSent(function ($request): bool {
        return str_starts_with($request->url(), 'https://thunder.example/v1/clans/search/')
            && $request->hasHeader('Authorization', 'Bearer user-token');
    });
});

test('squadron id lookup falls back to the server-wide login when the personal token is rejected', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $admin = createAdminForSquadronLookup();

    $this->actingAs($admin);

    $userToken = ThunderApiToken::factory()->create([
        'user_id' => $admin->id,
        'token' => 'stale-user-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => function ($request) {
            if ($request->hasHeader('Authorization', 'Bearer stale-user-token')) {
                return Http::response(['detail' => 'User not found'], 401);
            }

            return Http::response([
                ['_id' => '123', 'tag' => 'PECK', 'name' => 'Order Of The Birb'],
            ], 200);
        },
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSeeText('123 PECK Order Of The Birb');

    $userToken->refresh();

    expect($userToken->isExpired())->toBeTrue();
});

test('squadron id lookup reports an error when the server-wide login is unreachable', function () {
    config()->set('peck.squadron_id', null);
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    $this->actingAs(createAdminForSquadronLookup());

    Http::fake([
        'https://thunder.example/*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect to server'),
    ]);

    Livewire::test('pages::settings.admin')
        ->assertSee('Squadron ID Lookup')
        ->assertSee('Unable to reach ThunderAPI. Please try again later.');
});
