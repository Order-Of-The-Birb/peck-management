<?php

use App\Livewire\PeckUsersDashboard;
use App\Models\Officer;
use App\Models\PeckUser;
use App\Models\ThunderApiToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_refresh.refresh_after_hours', 1);
    config()->set('peck.squadron_id', '1061551');
});

test('squadron pages are blocked when the squadron id is not configured', function () {
    config()->set('peck.squadron_id', null);

    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake();

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('Squadron not set up')
        ->assertSee('contact an administrator')
        ->assertDontSee('ThunderAPI connection required');

    Http::assertNothingSent();
});

test('squadron pages are blocked with a settings button when thunderapi is not linked', function () {
    $user = User::factory()->create();

    Http::fake();

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('ThunderAPI connection required')
        ->assertSee('Open Settings')
        ->assertDontSee('Squadron not set up');

    Http::assertNothingSent();
});

test('the thunderapi block prompt can be dismissed without loading data', function () {
    $user = User::factory()->create();

    Http::fake();

    actingAs($user);

    $component = Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('ThunderAPI connection required')
        ->assertSeeHtml('wire:click.self="dismissThunderPrompt"')
        ->assertSeeHtml('wire:click="dismissThunderPrompt"');

    $component
        ->call('dismissThunderPrompt')
        ->assertDontSee('ThunderAPI connection required')
        ->assertSee('No logs found.');

    Http::assertNothingSent();
});

test('the squadron id block takes precedence over the thunderapi block', function () {
    config()->set('peck.squadron_id', null);

    $user = User::factory()->create();

    Http::fake();

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('Squadron not set up')
        ->assertDontSee('ThunderAPI connection required');
});

test('squadron applications and management pages share the squadron block', function () {
    $user = User::factory()->create();

    Http::fake();

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_applications'])
        ->assertSee('ThunderAPI connection required');

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_management'])
        ->assertSee('ThunderAPI connection required');
});

test('squadron logs render as cards with action, actor and datetime', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    $timestamp = 1790000000;

    Http::fake([
        'https://thunder.example/v1/clans/logs/*' => Http::response([
            'lastLog' => 'log-marker-abc',
            'logs' => [
                [
                    'timestamp' => $timestamp,
                    'action' => ['value' => 1, 'detail' => 'Accept membership request'],
                    'admin' => ['_id' => 111, 'nickname' => 'AdminOne'],
                    'affected' => ['_id' => 222, 'nickname' => 'NewMember'],
                ],
                [
                    'timestamp' => $timestamp,
                    'action' => ['value' => 2, 'detail' => 'Role change'],
                    'admin' => ['_id' => 111, 'nickname' => 'AdminOne'],
                    'affected' => ['_id' => 222, 'nickname' => 'NewMember'],
                    'roleChange' => ['old' => 'Private', 'new' => 'Officer'],
                ],
            ],
        ], 200),
    ]);

    actingAs($user);

    $expectedDatetime = Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i');

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee("NewMember (#222)'s application was accepted")
        ->assertSee('AdminOne (#111)')
        ->assertSee("NewMember (#222)'s role was changed")
        ->assertSee('Role changed from Private to Officer')
        ->assertSee($expectedDatetime);
});

test('show more requests the next page using the previous lastLog', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/logs/*' => Http::sequence()
            ->push([
                'lastLog' => 'log-marker-abc',
                'logs' => [
                    [
                        'timestamp' => 1790000000,
                        'action' => ['value' => 1, 'detail' => 'Accept membership request'],
                        'admin' => ['_id' => 111, 'nickname' => 'AdminOne'],
                        'affected' => ['_id' => 222, 'nickname' => 'NewMember'],
                    ],
                ],
            ], 200)
            ->push([
                'lastLog' => 'log-marker-def',
                'logs' => [
                    [
                        'timestamp' => 1790000100,
                        'action' => ['value' => 0, 'detail' => 'Kick user/Leave'],
                        'affected' => ['_id' => 333, 'nickname' => 'Leaver'],
                    ],
                ],
            ], 200),
    ]);

    actingAs($user);

    $component = Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('NewMember')
        ->assertDontSee('Leaver');

    $component
        ->call('loadMoreSquadronLogs')
        ->assertSee('Leaver');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v1/clans/logs/1061551')
            && ($request->data()['fromEntry'] ?? null) === 'log-marker-abc';
    });
});

test('unknown log actions render a generic unknown action label', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/logs/*' => Http::response([
            'lastLog' => 'log-marker-abc',
            'logs' => [
                [
                    'timestamp' => 1790000000,
                    'action' => ['value' => 99, 'detail' => 'Some future action'],
                    'admin' => ['_id' => 111, 'nickname' => 'AdminOne'],
                ],
            ],
        ], 200),
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('Unknown action');
});

test('squadron logs distinguish kicks from self-initiated leaves', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/logs/*' => Http::response([
            'lastLog' => 'log-marker-abc',
            'logs' => [
                [
                    'timestamp' => 1790000000,
                    'action' => ['value' => 0, 'detail' => 'Kick user/Leave'],
                    'admin' => ['_id' => 111, 'nickname' => 'AdminOne'],
                    'affected' => ['_id' => 222, 'nickname' => 'NewMember'],
                ],
                [
                    'timestamp' => 1790000100,
                    'action' => ['value' => 0, 'detail' => 'Kick user/Leave'],
                    'affected' => ['_id' => 333, 'nickname' => 'Leaver'],
                ],
            ],
        ], 200),
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('NewMember (#222) was kicked')
        ->assertSee('AdminOne (#111)')
        ->assertSee('Leaver (#333) left')
        ->assertDontSee('Leaver (#333) was kicked');
});

test('squadron logs refresh a stale thunderapi token before loading', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
        'refreshed_at' => now()->subHours(2),
    ]);

    $newExpiry = now()->addHour()->timestamp;

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'OK',
            'expires' => $newExpiry,
        ], 200),
        'https://thunder.example/v1/clans/logs/*' => Http::response([
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

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee("NewMember (#222)'s application was accepted");

    $token = ThunderApiToken::query()->where('user_id', $user->id)->first();

    expect($token?->expires_at)->toBe($newExpiry);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://thunder.example/v1/refresh-token';
    });
});

test('squadron management page is blurred for users without clearance', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake();

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_management'])
        ->assertSee('Insufficient clearance')
        ->assertSee('do not have a high enough clearance');
});

test('squadron management page is blurred for retired officers', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
        'gaijin_id' => 222,
    ]);

    PeckUser::factory()->create(['gaijin_id' => 222]);

    Officer::query()->create([
        'gaijin_id' => 222,
        'rank' => null,
    ]);

    Http::fake();

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_management'])
        ->assertSee('Insufficient clearance');
});

test('squadron management page renders for non-retired officers', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
        'gaijin_id' => 222,
    ]);

    PeckUser::factory()->create(['gaijin_id' => 222]);

    Officer::query()->create([
        'gaijin_id' => 222,
        'rank' => Officer::RANK_OFFICER,
    ]);

    Http::fake();

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_management'])
        ->assertDontSee('Insufficient clearance')
        ->assertSee('Squadron management will appear here.');
});

test('squadron management sidebar item is hidden for unauthorized users', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake();

    actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Management');
});

test('squadron management sidebar item is shown for authorized officers', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
        'gaijin_id' => 222,
    ]);

    PeckUser::factory()->create(['gaijin_id' => 222]);

    Officer::query()->create([
        'gaijin_id' => 222,
        'rank' => Officer::RANK_COMMANDER,
    ]);

    Http::fake();

    actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Management');
});

test('squadron log description and application messages render with the wt-glyphs class', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/logs/*' => Http::response([
            'lastLog' => 'log-marker-abc',
            'logs' => [
                [
                    'timestamp' => 1790000000,
                    'action' => ['value' => 3, 'detail' => 'Rejected membership request'],
                    'admin' => ['_id' => 111, 'nickname' => 'AdminOne'],
                    'affected' => ['_id' => 222, 'nickname' => 'NewMember'],
                    'comment' => 'Denied \u250e reason \u253f',
                ],
                [
                    'timestamp' => 1790000100,
                    'action' => ['value' => 4, 'detail' => 'Squadron info changed'],
                    'tag' => '\u250ePECK\u253f',
                    'desc' => 'A \u250e description \u253f here',
                ],
            ],
        ], 200),
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_logs'])
        ->assertSee('Denied \u250e reason \u253f')
        ->assertSee('A \u250e description \u253f here')
        ->assertSee('wt-glyphs');
});

test('squadron applications render as cards with applicant details', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    $timestamp = 1790000000;

    Http::fake([
        'https://thunder.example/v1/clans/applicants/*' => Http::response([
            [
                'uid' => '12345678',
                'nickname' => 'ApplicantOne',
                'timestamp' => $timestamp,
                'comment' => 'Looking to join the squadron.',
                'geodata' => ['country' => 'Germany', 'timezone' => 2],
            ],
            [
                'uid' => '87654321',
                'nickname' => 'ApplicantTwo',
                'timestamp' => $timestamp,
                'comment' => '',
                'geodata' => ['country' => 'United States', 'timezone' => -5],
            ],
        ], 200),
    ]);

    actingAs($user);

    $expectedDatetime = Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i');

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_applications'])
        ->assertSee('ApplicantOne')
        ->assertSee('#12345678')
        ->assertSee('ApplicantTwo')
        ->assertSee('#87654321')
        ->assertSee('Germany')
        ->assertSee('UTC+2')
        ->assertSee('United States')
        ->assertSee('UTC-5')
        ->assertSee($expectedDatetime);
});

test('squadron applications show an empty state when there are no applicants', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/applicants/*' => Http::response([], 200),
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_applications'])
        ->assertSee('There are no applicants currently.')
        ->assertDontSee('openApplicantModal');
});

test('squadron applications show an error when thunderapi is unreachable', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/applicants/*' => function (): never {
            throw new ConnectionException('cURL error 7: Failed to connect');
        },
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_applications'])
        ->assertSee('Unable to reach ThunderAPI. Please try again later.');
});

test('opening an application modal shows the applicants details and comment', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/applicants/*' => Http::response([
            [
                'uid' => '12345678',
                'nickname' => 'ApplicantOne',
                'timestamp' => 1790000000,
                'comment' => 'Looking to join the squadron.',
                'geodata' => ['country' => 'Germany', 'timezone' => 2],
            ],
        ], 200),
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_applications'])
        ->call('openApplicantModal', '12345678')
        ->assertSet('showApplicantModal', true)
        ->assertSee('ApplicantOne')
        ->assertSee('#12345678')
        ->assertSee('Germany')
        ->assertSee('UTC+2')
        ->assertSee('Looking to join the squadron.')
        ->assertSee('Comment');
});

test('accepting an application posts to the accept endpoint and refreshes the list', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/applicants/*' => Http::sequence()
            ->push([
                [
                    'uid' => '12345678',
                    'nickname' => 'ApplicantOne',
                    'timestamp' => 1790000000,
                    'comment' => '',
                    'geodata' => ['country' => 'Germany', 'timezone' => 2],
                ],
            ], 200)
            ->push([], 200),
        'https://thunder.example/v1/clans/accept/*' => Http::response(['status' => 'success'], 200),
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_applications'])
        ->assertSee('ApplicantOne')
        ->call('openApplicantModal', '12345678')
        ->call('acceptApplicant')
        ->assertSet('showApplicantModal', false)
        ->assertSet('selectedApplicantUid', null)
        ->assertSee('There are no applicants currently.');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://thunder.example/v1/clans/accept/12345678'
            && $request->method() === 'POST';
    });
});

test('rejecting an application posts a reason to the reject endpoint', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'logs-token',
    ]);

    Http::fake([
        'https://thunder.example/v1/clans/applicants/*' => Http::sequence()
            ->push([
                [
                    'uid' => '12345678',
                    'nickname' => 'ApplicantOne',
                    'timestamp' => 1790000000,
                    'comment' => '',
                    'geodata' => ['country' => 'Germany', 'timezone' => 2],
                ],
            ], 200)
            ->push([], 200),
        'https://thunder.example/v1/clans/reject/*' => Http::response(['status' => 'success'], 200),
    ]);

    actingAs($user);

    Livewire::test(PeckUsersDashboard::class, ['section' => 'squadron_applications'])
        ->call('openApplicantModal', '12345678')
        ->call('openRejectApplicantModal')
        ->set('rejectApplicantReason', 'Not a good fit')
        ->call('confirmRejectApplicant')
        ->assertSet('showRejectApplicantModal', false)
        ->assertSee('There are no applicants currently.');

    Http::assertSent(function ($request): bool {
        $query = [];

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

        return str_starts_with($request->url(), 'https://thunder.example/v1/clans/reject/12345678')
            && $request->method() === 'POST'
            && ($query['message'] ?? null) === 'Not a good fit';
    });
});
