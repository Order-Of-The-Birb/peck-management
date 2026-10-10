<?php

use App\Livewire\PeckUsersDashboard;
use App\Models\PeckAlt;
use App\Models\PeckUser;
use App\Models\PeckUserContext;
use App\Models\ThunderApiServerToken;
use App\Models\ThunderApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
    config()->set('peck.thunderapi_refresh.refresh_after_hours', 1);
    config()->set('peck.squadron_id', null);
    config()->set('peck.thunderapi_server.email', null);
    config()->set('peck.thunderapi_server.password', null);
});

function thunderSelfResponse(string $role): array
{
    return [
        'userId' => '424242',
        'nick' => 'TestAdmin',
        'squadron' => [
            'user' => [
                'role' => ['name' => $role, 'value' => 1],
            ],
        ],
    ];
}

function actingThunderAdmin(string $role = 'Commander'): User
{
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'admin-token',
    ]);

    test()->actingAs($user);

    return $user;
}

test('peck users dashboard component is discoverable and mountable', function () {
    expect(app('livewire')->exists('peck-users-dashboard'))->toBeTrue();

    $instance = Livewire::test(PeckUsersDashboard::class)->instance();

    expect($instance)->toBeInstanceOf(PeckUsersDashboard::class)
        ->and($instance->section)->toBe('members');
});

test('members list shows gaijin id, discord id and a view button', function () {
    $member = PeckUser::factory()->create([
        'gaijin_id' => 800001,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->assertSee((string) $member->gaijin_id)
        ->assertSee((string) $member->discord_id)
        ->assertSee('View');
});

test('members list is searchable by gaijin id and discord id', function () {
    $member = PeckUser::factory()->create([
        'gaijin_id' => 800010,
    ]);

    $other = PeckUser::factory()->create([
        'gaijin_id' => 800020,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->set('search', '800010')
        ->assertSee((string) $member->gaijin_id)
        ->assertDontSee((string) $other->gaijin_id);
});

test('members list is searchable by username', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $member = PeckUser::factory()->create(['gaijin_id' => 800011]);
    $other = PeckUser::factory()->create(['gaijin_id' => 800012]);

    Http::fake([
        'https://thunder.example/v1/users/terse*' => Http::response([
            '800011' => ['nick' => 'AlphaBird'],
            '800012' => ['nick' => 'OtherUser'],
        ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->set('search', 'AlphaBird')
        ->assertSee((string) $member->gaijin_id)
        ->assertSee('AlphaBird')
        ->assertDontSee((string) $other->gaijin_id);
});

test('members list can be sorted by username', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $first = PeckUser::factory()->create(['gaijin_id' => 800013]);
    $second = PeckUser::factory()->create(['gaijin_id' => 800014]);

    Http::fake([
        'https://thunder.example/v1/users/terse*' => Http::response([
            (string) $first->gaijin_id => ['nick' => 'ZuluBird'],
            (string) $second->gaijin_id => ['nick' => 'AlphaBird'],
        ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('sort', 'username')
        ->assertSet('sortBy', 'username')
        ->assertSeeInOrder(['AlphaBird', 'ZuluBird'])
        ->call('sort', 'username')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['ZuluBird', 'AlphaBird']);
});

test('members list can be sorted by status', function () {
    config()->set('peck.squadron_id', '1061551');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $member = PeckUser::factory()->create(['gaijin_id' => 802200]);
    $applicant = PeckUser::factory()->create(['gaijin_id' => 802201]);
    $exMember = PeckUser::factory()->create(['gaijin_id' => 802202]);

    Http::fake([
        'https://thunder.example/v1/clans/1061551' => Http::response([
            'members' => [['uid' => (string) $member->gaijin_id, 'nick' => 'M', 'role' => 3, 'date' => 1]],
            'candidates' => [['uid' => (string) $applicant->gaijin_id, 'nick' => 'A', 'date' => 1, 'comments' => '']],
        ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('sort', 'status')
        ->assertSet('sortBy', 'status')
        ->assertSeeInOrder(['applicant', 'ex_member', 'member']);
});

test('refresh button is only shown to users with write access', function () {
    $this->actingAs(User::factory()->create(['level' => 1]));

    Livewire::test(PeckUsersDashboard::class)
        ->assertSee('Refresh');

    $this->actingAs(User::factory()->create(['level' => 0]));

    Livewire::test(PeckUsersDashboard::class)
        ->assertDontSee('Refresh');
});

test('requesting a partial refresh runs once and enforces a global cooldown', function () {
    $this->withoutDefer();

    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $this->actingAs(User::factory()->create(['level' => 1]));

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            ['_id' => '123', 'name' => 'Order Of The Birb', 'namel' => 'order of the birb'],
        ], 200),
        'https://thunder.example/v1/clans/123' => Http::response([
            'members' => [['uid' => '900001']],
        ], 200),
    ]);

    $component = Livewire::test(PeckUsersDashboard::class)
        ->call('requestPartialRefresh')
        ->assertDispatched('partial-refresh-cooldown-started');

    expect(PeckUser::query()->where('gaijin_id', 900001)->exists())->toBeTrue();
    expect($component->instance()->partialRefreshCooldownSeconds())->toBeGreaterThan(0);

    Http::fake([
        'https://thunder.example/v1/clans/search/*' => Http::response([
            ['_id' => '123', 'name' => 'Order Of The Birb', 'namel' => 'order of the birb'],
        ], 200),
        'https://thunder.example/v1/clans/123' => Http::response([
            'members' => [['uid' => '900002']],
        ], 200),
    ]);

    $component->call('requestPartialRefresh');

    Http::assertNotSent(function ($request): bool {
        return str_contains($request->url(), '/v1/clans/');
    });
});

test('users without write access cannot request a partial refresh', function () {
    $this->actingAs(User::factory()->create(['level' => 0]));

    Livewire::test(PeckUsersDashboard::class)
        ->call('requestPartialRefresh')
        ->assertForbidden();
});

test('members list derives member, applicant and ex_member status from the clan endpoint', function () {
    config()->set('peck.squadron_id', '1061551');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $member = PeckUser::factory()->create(['gaijin_id' => 802200]);
    $applicant = PeckUser::factory()->create(['gaijin_id' => 802201]);
    $exMember = PeckUser::factory()->create(['gaijin_id' => 802202]);

    Http::fake([
        'https://thunder.example/v1/clans/1061551' => Http::response([
            'members' => [['uid' => (string) $member->gaijin_id, 'nick' => 'M', 'role' => 3, 'date' => 1]],
            'candidates' => [['uid' => (string) $applicant->gaijin_id, 'nick' => 'A', 'date' => 1, 'comments' => '']],
        ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->assertSee('applicant')
        ->assertSee('ex_member')
        ->set('search', (string) $member->gaijin_id)
        ->assertSee('member')
        ->assertDontSee('ex_member');
});

test('members list re-authenticates the server token when the clan endpoint rejects it', function () {
    config()->set('peck.squadron_id', '1061551');
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'stale-server-token']);

    $member = PeckUser::factory()->create(['gaijin_id' => 802300]);
    $applicant = PeckUser::factory()->create(['gaijin_id' => 802301]);
    $exMember = PeckUser::factory()->create(['gaijin_id' => 802302]);

    Http::fake([
        'https://thunder.example/v1/login' => Http::response([
            'status' => 'OK',
            'token' => 'fresh-server-token',
            'user_id' => 424242,
        ], 200),
        'https://thunder.example/v1/clans/1061551' => Http::sequence()
            ->push([], 401)
            ->push([
                'members' => [['uid' => (string) $member->gaijin_id, 'nick' => 'M', 'role' => 3, 'date' => 1]],
                'candidates' => [['uid' => (string) $applicant->gaijin_id, 'nick' => 'A', 'date' => 1, 'comments' => '']],
            ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->assertSee('applicant')
        ->assertSee('ex_member')
        ->set('search', (string) $member->gaijin_id)
        ->assertSee('member')
        ->assertDontSee('ex_member');

    expect(ThunderApiServerToken::query()->first()->token)->toBe('fresh-server-token');
});

test('non-admin users can open the member modal but not edit or manage', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $member = PeckUser::factory()->create([
        'gaijin_id' => 800100,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->assertSet('showMemberModal', true)
        ->assertSet('memberEditMode', false)
        ->assertSee('Member')
        ->assertSee('Context')
        ->assertDontSee('Manage')
        ->assertDontSee('Edit');
});

test('admins can edit member fields and save changes', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create([
        'gaijin_id' => 800200,
        'discord_id' => 111,
        'tz' => 0,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->assertSee('Edit')
        ->assertSee('Manage')
        ->call('enterMemberEditMode')
        ->assertSet('memberEditMode', true)
        ->set('memberForm.discord_id', '123456789012345678')
        ->set('memberForm.tz', '3')
        ->set('memberForm.sqb_part', true)
        ->call('saveMember')
        ->assertHasNoErrors()
        ->assertDispatched('peck-member-saved')
        ->assertSet('memberEditMode', false);

    $member->refresh();

    expect($member->discord_id)->toBe(123456789012345678)
        ->and($member->tz)->toBe(3)
        ->and($member->sqb_part)->toBeTrue();
});

test('cancelling an edit discards changes made this session', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create([
        'gaijin_id' => 800300,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('enterMemberEditMode')
        ->set('memberForm.discord_id', '999999')
        ->call('cancelMemberEdit')
        ->assertSet('memberEditMode', false)
        ->assertSet('memberForm.discord_id', $member->discord_id);
});

test('an admin can assign an owner to a member', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 800400]);
    $owner = PeckUser::factory()->create(['gaijin_id' => 800401]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('enterMemberEditMode')
        ->set('memberForm.owner', (string) $owner->gaijin_id)
        ->call('saveMember')
        ->assertHasNoErrors();

    expect(PeckAlt::query()->where('alt_id', $member->gaijin_id)->value('owner_id'))
        ->toBe($owner->gaijin_id);
});

test('an admin can unassign an owner from a member', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 800500]);
    $owner = PeckUser::factory()->create(['gaijin_id' => 800501]);

    PeckAlt::query()->create([
        'alt_id' => $member->gaijin_id,
        'owner_id' => $owner->gaijin_id,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('enterMemberEditMode')
        ->set('memberForm.owner', '')
        ->call('saveMember')
        ->assertHasNoErrors();

    expect(PeckAlt::query()->where('alt_id', $member->gaijin_id)->exists())->toBeFalse();
});

test('context sub-tab lists misc entries with a view button and absences without one', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 800600]);

    PeckUserContext::factory()->create([
        'user_id' => $member->gaijin_id,
        'context_id' => 0,
        'type' => PeckUserContext::TYPE_MISC,
        'comment' => 'Max rank: 14.7',
    ]);

    PeckUserContext::factory()->create([
        'user_id' => $member->gaijin_id,
        'context_id' => 1,
        'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
        'from_date' => '2024-01-01',
        'to_date' => '2024-01-15',
        'comment' => null,
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('selectMemberTab', 'context')
        ->assertSee('misc')
        ->assertSee('Max rank: 14.7')
        ->assertSee('absence')
        ->assertSee('2024.01.01 - 2024.01.15')
        ->assertSeeHtml('openContextEntryModal(0)')
        ->assertDontSeeHtml('openContextEntryModal(1)');
});

test('viewing a misc context opens a read-only comment modal and edits can be saved', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 800700]);

    PeckUserContext::factory()->create([
        'user_id' => $member->gaijin_id,
        'context_id' => 0,
        'type' => PeckUserContext::TYPE_MISC,
        'comment' => 'Original comment',
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('selectMemberTab', 'context')
        ->call('openContextEntryModal', 0)
        ->assertSet('showContextEntryModal', true)
        ->assertSet('contextEntryComment', 'Original comment')
        ->assertSee('Original comment')
        ->call('enterContextEntryEditMode')
        ->assertSet('contextEntryEditMode', true)
        ->set('contextEntryComment', 'Updated comment')
        ->call('saveContextEntry')
        ->assertHasNoErrors()
        ->assertDispatched('peck-context-updated')
        ->assertSet('contextEntryEditMode', false);

    expect(
        PeckUserContext::query()
            ->where('user_id', $member->gaijin_id)
            ->where('context_id', 0)
            ->value('comment')
    )->toBe('Updated comment');
});

test('admins can add split recurring contexts and remove entries', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 800800]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('selectMemberTab', 'context')
        ->call('openAddContextForm')
        ->set('contextForm.type', PeckUserContext::TYPE_RECURRING_ABSENCE)
        ->set('contextForm.weekdays', [0, 3])
        ->set('contextForm.monthDay', '26')
        ->call('addContext')
        ->assertHasNoErrors()
        ->assertDispatched('peck-context-added')
        ->assertSee('every Mon, Thu')
        ->assertSee('every month on the 26th')
        ->call('removeContext', 0)
        ->assertDontSee('every Mon, Thu');

    $remaining = PeckUserContext::query()
        ->where('user_id', $member->gaijin_id)
        ->first();

    expect($remaining?->context_id)->toBe(1)
        ->and($remaining?->month_day)->toBe(26);
});

test('commander can kick a member with a confirmation reason', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
        'https://thunder.example/v1/clans/kick/*' => Http::response(['status' => 'success'], 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 801000]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('selectMemberTab', 'manage')
        ->assertSee('Kick user')
        ->call('openKickConfirmModal')
        ->set('kickReason', 'inactive')
        ->call('kickMember')
        ->assertDispatched('peck-member-kicked');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/clans/kick/'.$member->gaijin_id)
        && ($request->data()['reason'] ?? null) === 'inactive');
});

test('commander can change a member role', function () {
    actingThunderAdmin('Commander');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
        'https://thunder.example/v1/clans/role/*' => Http::response(['status' => 'OK'], 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 801100]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('selectMemberTab', 'manage')
        ->assertSee('Change role')
        ->set('manageRole', 'Officer')
        ->call('changeMemberRole')
        ->assertDispatched('peck-member-role-changed');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/clans/role/'.$member->gaijin_id)
        && str_contains($request->url(), 'role=Officer'));
});

test('officers can kick but cannot change roles', function () {
    actingThunderAdmin('Officer');

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Officer'), 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 801200]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->call('selectMemberTab', 'manage')
        ->assertSee('Kick user')
        ->assertDontSee('Change role');
});

test('member username resolves through the user thunderapi token when server login fails', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'user-token',
    ]);

    $this->actingAs($user);

    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    $member = PeckUser::factory()->create(['gaijin_id' => 801300]);

    Http::fake([
        'https://thunder.example/v1/login' => Http::response(['detail' => 'Invalid credentials'], 401),
        'https://thunder.example/v1/users/terse*' => Http::response([
            '801300' => ['nick' => 'AlphaBird'],
        ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->assertSee('AlphaBird');
});

test('members list resolves usernames via the terse endpoint using the server login', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    $member = PeckUser::factory()->create(['gaijin_id' => 802001]);

    Http::fake([
        'https://thunder.example/v1/users/terse*' => Http::response([
            '802001' => ['nick' => 'AlphaBird'],
        ], 200),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->assertSee('AlphaBird')
        ->assertSee((string) $member->gaijin_id);
});

test('shows an error popup when thunderapi is unreachable during username resolution', function () {
    config()->set('peck.thunderapi_server.email', 'server@example.com');
    config()->set('peck.thunderapi_server.password', 'server-password');

    ThunderApiServerToken::factory()->create(['token' => 'server-token']);

    PeckUser::factory()->create(['gaijin_id' => 802100]);

    Http::fake([
        'https://thunder.example/v1/users/terse*' => Http::failedConnection(),
    ]);

    Livewire::test(PeckUsersDashboard::class)
        ->assertSet('thunderApiError', true)
        ->assertSee('An error occurred with ThunderAPI')
        ->call('dismissThunderApiError')
        ->assertSet('thunderApiError', false)
        ->assertDontSee('An error occurred with ThunderAPI');
});

test('unverified admins are blocked from editing with a verification popup', function () {
    $user = User::factory()->unverified()->create(['level' => 1]);

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'admin-token',
    ]);

    $this->actingAs($user);

    Http::fake([
        'https://thunder.example/v1/users/self' => Http::response(thunderSelfResponse('Commander'), 200),
    ]);

    $member = PeckUser::factory()->create(['gaijin_id' => 801400]);

    Livewire::test(PeckUsersDashboard::class)
        ->call('openMemberModal', $member->gaijin_id)
        ->assertSee('Edit')
        ->call('enterMemberEditMode')
        ->assertSet('showVerificationRequiredModal', true)
        ->assertSet('memberEditMode', false);
});

test('unverified users can browse the members dashboard read-only', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
});
