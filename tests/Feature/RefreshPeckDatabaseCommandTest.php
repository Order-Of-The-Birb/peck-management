<?php

use App\Models\PeckUser;
use App\Models\ThunderApiToken;
use Illuminate\Support\Facades\Http;

test('peck refresh command imports users from ThunderAPI', function () {
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://example.test');

    ThunderApiToken::factory()->create(['token' => 'test-token']);

    Http::fake([
        'https://example.test/v1/clans/search/*' => Http::response([
            [
                '_id' => '123',
                'name' => 'Order Of The Birb',
                'namel' => 'order of the birb',
            ],
        ], 200),
        'https://example.test/v1/clans/123' => Http::response([
            'members' => [
                [
                    'uid' => '900001',
                    'nick' => 'birb_member@steam',
                    'role' => 3,
                    'date' => 1_710_000_000,
                    'initiator' => null,
                ],
            ],
        ], 200),
    ]);

    $this->artisan('peck:refresh-db')
        ->assertSuccessful();

    $peckUser = PeckUser::query()->find(900001);

    expect($peckUser)->not->toBeNull();
    expect($peckUser?->username)->toBe('birb_member');

    Http::assertSentCount(2);
});

test('peck refresh command dry run does not write users', function () {
    config()->set('peck.squadron_name', 'Order Of The Birb');
    config()->set('peck.thunderapi_base_url', 'https://example.test');

    ThunderApiToken::factory()->create(['token' => 'test-token']);

    Http::fake([
        'https://example.test/v1/clans/search/*' => Http::response([
            [
                '_id' => '123',
                'name' => 'Order Of The Birb',
                'namel' => 'order of the birb',
            ],
        ], 200),
        'https://example.test/v1/clans/123' => Http::response([
            'members' => [
                [
                    'uid' => '900002',
                    'nick' => 'dry_run_member',
                    'role' => 3,
                    'date' => 1_710_000_100,
                    'initiator' => null,
                ],
            ],
        ], 200),
    ]);

    $this->artisan('peck:refresh-db --dry-run')
        ->assertSuccessful();

    expect(PeckUser::query()->find(900002))->toBeNull();
    Http::assertSentCount(2);
});
