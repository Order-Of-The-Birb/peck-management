<?php

use App\Models\PeckUser;
use Illuminate\Support\Facades\Http;

test('peck refresh command imports users from thunderinsights', function () {
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderinsights_base_url', 'https://example.test');

    Http::fake([
        'https://example.test/clans/direct/clan/search/*' => Http::response([
            'clan' => [
                'members' => [
                    [
                        'uid' => 900001,
                        'nick' => 'birb_member@steam',
                        'date' => 1_710_000_000,
                        'initiator' => null,
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('peck:refresh-db')
        ->assertSuccessful();

    $peckUser = PeckUser::query()->find(900001);

    expect($peckUser)->not->toBeNull();
    expect($peckUser?->username)->toBe('birb_member');

    Http::assertSentCount(1);
});

test('peck refresh command dry run does not write users', function () {
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderinsights_base_url', 'https://example.test');

    Http::fake([
        'https://example.test/clans/direct/clan/search/*' => Http::response([
            'clan' => [
                'members' => [
                    [
                        'uid' => 900002,
                        'nick' => 'dry_run_member',
                        'date' => 1_710_000_100,
                        'initiator' => null,
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('peck:refresh-db --dry-run')
        ->assertSuccessful();

    expect(PeckUser::query()->find(900002))->toBeNull();
    Http::assertSentCount(1);
});
