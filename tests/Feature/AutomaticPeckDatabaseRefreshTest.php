<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('automatic peck database refresh runs once when schedule is due', function () {
    $this->withoutDefer();
    $this->travelTo(Carbon::create(2026, 4, 20, 12, 30, 0));

    config()->set('peck.auto_refresh_enabled', true);
    config()->set('peck.refresh_schedule', '12:00');
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderinsights_base_url', 'https://example.test');

    Cache::forget((string) config('peck.auto_refresh.last_attempted_date_key'));
    Cache::forget((string) config('peck.auto_refresh.last_successful_date_key'));
    Cache::forget((string) config('peck.auto_refresh.lock_key'));

    Http::fake([
        'https://example.test/clans/direct/clan/search/*' => Http::response([
            'clan' => [
                'members' => [],
            ],
        ], 200),
    ]);

    $this->get('/')
        ->assertRedirect(route('dashboard'));

    $this->get('/')
        ->assertRedirect(route('dashboard'));

    Http::assertSentCount(1);

    expect(Cache::get((string) config('peck.auto_refresh.last_attempted_date_key')))
        ->toBe('2026-04-20');
    expect(Cache::get((string) config('peck.auto_refresh.last_successful_date_key')))
        ->toBe('2026-04-20');
});

test('automatic peck database refresh does not run before schedule time', function () {
    $this->withoutDefer();
    $this->travelTo(Carbon::create(2026, 4, 20, 12, 30, 0));

    config()->set('peck.auto_refresh_enabled', true);
    config()->set('peck.refresh_schedule', '13:00');
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderinsights_base_url', 'https://example.test');

    Cache::forget((string) config('peck.auto_refresh.last_attempted_date_key'));
    Cache::forget((string) config('peck.auto_refresh.lock_key'));

    Http::fake([
        'https://example.test/clans/direct/clan/search/*' => Http::response([
            'clan' => [
                'members' => [],
            ],
        ], 200),
    ]);

    $this->get('/')
        ->assertRedirect(route('dashboard'));

    Http::assertNothingSent();
});

test('automatic peck database refresh uses UTC schedule regardless of app timezone', function () {
    $this->withoutDefer();
    $this->travelTo(Carbon::create(2026, 4, 20, 23, 0, 0, 'Europe/Budapest'));

    config()->set('app.timezone', 'Europe/Budapest');
    config()->set('peck.auto_refresh_enabled', true);
    config()->set('peck.refresh_schedule', '22:00');
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderinsights_base_url', 'https://example.test');

    Cache::forget((string) config('peck.auto_refresh.last_attempted_date_key'));
    Cache::forget((string) config('peck.auto_refresh.lock_key'));

    Http::fake([
        'https://example.test/clans/direct/clan/search/*' => Http::response([
            'clan' => [
                'members' => [],
            ],
        ], 200),
    ]);

    $this->get('/')
        ->assertRedirect(route('dashboard'));

    Http::assertNothingSent();
});
