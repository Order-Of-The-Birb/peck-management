<?php

use App\Models\ThunderApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
});

test('refresh command refreshes due tokens and updates their expiry', function () {
    $user = User::factory()->create();

    $newExpiry = now()->addHour()->timestamp;

    $dueToken = ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'due-token',
        'expires_at' => now()->subHour()->timestamp,
        'refreshed_at' => now()->subDay(),
    ]);

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'OK',
            'expires' => $newExpiry,
        ], 200),
    ]);

    $this->artisan('thunderapi:refresh-tokens')->assertSuccessful();

    $dueToken->refresh();

    expect($dueToken->expires_at)->toBe($newExpiry)
        ->and($dueToken->refreshed_at->gte(now()->subSeconds(5)))->toBeTrue();

    Http::assertSentCount(1);
});

test('refresh command skips tokens that were refreshed recently', function () {
    $user = User::factory()->create();

    ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'fresh-token',
        'refreshed_at' => now(),
    ]);

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'OK',
            'expires' => now()->addHour()->timestamp,
        ], 200),
    ]);

    $this->artisan('thunderapi:refresh-tokens')->assertSuccessful();

    Http::assertSentCount(0);
});

test('refresh command respects the configured batch size', function () {
    config()->set('peck.thunderapi_refresh.batch_size', 2);

    foreach (range(1, 3) as $index) {
        ThunderApiToken::factory()->create([
            'user_id' => User::factory()->create()->id,
            'token' => 'due-token-'.$index,
            'refreshed_at' => now()->subDay(),
        ]);
    }

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'OK',
            'expires' => now()->addHour()->timestamp,
        ], 200),
    ]);

    $this->artisan('thunderapi:refresh-tokens')->assertSuccessful();

    Http::assertSentCount(2);
});

test('refresh command keeps invalid tokens instead of deleting them', function () {
    $user = User::factory()->create();

    $invalidToken = ThunderApiToken::factory()->create([
        'user_id' => $user->id,
        'token' => 'invalid-token',
        'refreshed_at' => now()->subDay(),
    ]);

    Http::fake([
        'https://thunder.example/v1/refresh-token' => Http::response([
            'status' => 'FAIL',
            'detail' => 'Invalid token',
        ], 404),
    ]);

    $this->artisan('thunderapi:refresh-tokens')->assertSuccessful();

    $invalidToken->refresh();

    expect(ThunderApiToken::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($invalidToken->refreshed_at->gte(now()->subSeconds(5)))->toBeTrue();
});
