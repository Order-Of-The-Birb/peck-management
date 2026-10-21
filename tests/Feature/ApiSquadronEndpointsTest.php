<?php

use App\Models\ThunderApiServerToken;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_refresh.refresh_after_hours', 1);
    config()->set('peck.squadron_id', '1061551');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);
});

test('api applicants returns normalized squadron applicants using the server token', function () {
    Http::fake([
        'https://thunder.example/v1/clans/applicants/1061551' => Http::response([
            [
                'uid' => '820003',
                'nickname' => 'Maho_Yoshino',
                'timestamp' => 1790000000,
                'comment' => 'Hello',
                'geo' => ['country' => 'DE', 'offset' => 2, 'city' => 'Berlin'],
            ],
        ], 200),
    ]);

    $this->getJson('/api/v1/applicants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uid', '820003')
        ->assertJsonPath('data.0.nickname', 'Maho_Yoshino')
        ->assertJsonPath('data.0.timestamp', 1790000000)
        ->assertJsonPath('data.0.comment', 'Hello')
        ->assertJsonPath('data.0.country', 'DE')
        ->assertJsonPath('data.0.city', 'Berlin')
        ->assertJsonPath('data.0.timezone', 2);

    Http::assertSent(fn ($request) => $request->url() === 'https://thunder.example/v1/clans/applicants/1061551'
        && $request->hasHeader('Authorization', 'Bearer server-token'));
});

test('api logs returns squadron log entries with the paging cursor', function () {
    Http::fake([
        'https://thunder.example/v1/clans/logs/1061551*' => Http::response([
            'lastLog' => 'log-marker-abc',
            'logs' => [
                [
                    'timestamp' => 1790000000,
                    'action' => ['value' => 1, 'detail' => 'Accept membership request'],
                    'admin' => ['_id' => 111, 'nickname' => 'AdminOne'],
                    'affected' => ['_id' => 222, 'nickname' => 'NewMember'],
                ],
            ],
        ], 200),
    ]);

    $this->getJson('/api/v1/logs?limit=25&from_entry=log-marker-prev')
        ->assertOk()
        ->assertJsonPath('lastLog', 'log-marker-abc')
        ->assertJsonCount(1, 'logs')
        ->assertJsonPath('logs.0.action.value', 1)
        ->assertJsonPath('logs.0.affected.nickname', 'NewMember');
});

test('api clan id returns the configured squadron id', function () {
    Http::fake();

    $this->getJson('/api/v1/clan-id')
        ->assertOk()
        ->assertJsonPath('clan_id', '1061551');

    Http::assertNothingSent();
});

test('api squadron endpoints return 503 when the squadron id is not configured', function () {
    config()->set('peck.squadron_id', null);

    Http::fake();

    $this->getJson('/api/v1/applicants')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Squadron ID is not configured.');

    $this->getJson('/api/v1/logs')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Squadron ID is not configured.');

    $this->getJson('/api/v1/clan-id')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Squadron ID is not configured.');

    Http::assertNothingSent();
});

test('api squadron endpoints return 503 when the server account is not configured', function () {
    config()->set('peck.thunderapi_server.email', null);
    config()->set('peck.thunderapi_server.password', null);

    Http::fake();

    $this->getJson('/api/v1/applicants')
        ->assertStatus(503)
        ->assertJsonPath('message', 'ThunderAPI server account is not configured.');

    $this->getJson('/api/v1/logs')
        ->assertStatus(503)
        ->assertJsonPath('message', 'ThunderAPI server account is not configured.');

    Http::assertNothingSent();
});

test('api applicants re-authenticates the server token when it is rejected', function () {
    Http::fake([
        'https://thunder.example/v1/clans/applicants/1061551' => Http::sequence()
            ->push(['detail' => 'User not found'], 401)
            ->push([
                ['uid' => '820003', 'nickname' => 'Maho_Yoshino'],
            ], 200),
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'fresh-server-token',
            'user_id' => 424242,
        ], 200),
    ]);

    $this->getJson('/api/v1/applicants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uid', '820003');

    expect(ThunderApiServerToken::query()->value('token'))->toBe('fresh-server-token');
});

test('api logs validates the limit query parameter', function () {
    $this->getJson('/api/v1/logs?limit=999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
});
