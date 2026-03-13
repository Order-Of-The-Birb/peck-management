<?php

use App\Models\PeckLeaveInfo;
use App\Models\PeckUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

test('peck refresh command syncs users from thunderinsights data', function () {
    $leftUser = PeckUser::factory()->create([
        'gaijin_id' => 910001,
        'username' => 'left-user',
        'status' => 'member',
    ]);

    $returningUser = PeckUser::factory()->create([
        'gaijin_id' => 910002,
        'username' => 'returning-old-name',
        'status' => 'ex_member',
        'initiator' => null,
    ]);

    Http::fake([
        'https://api.thunderinsights.dk/*' => Http::response([
            'clan' => [
                'members' => [
                    [
                        'uid' => 910002,
                        'nick' => 'returning-user',
                        'date' => 1_700_000_000,
                        'initiator' => null,
                    ],
                    [
                        'uid' => 910003,
                        'nick' => 'new-user',
                        'date' => 1_700_100_000,
                        'initiator' => 910002,
                    ],
                ],
            ],
        ], 200),
    ]);

    Artisan::call('peck:refresh-users', [
        '--squadron' => 'Order Of The Birb',
    ]);

    $returningUser->refresh();
    $leftUser->refresh();

    expect($returningUser->username)->toBe('returning-user');
    expect($returningUser->status)->toBe('member');

    $newUser = PeckUser::query()->findOrFail(910003);

    expect($newUser->username)->toBe('new-user');
    expect($newUser->status)->toBe('unverified');
    expect($newUser->initiator)->toBe(910002);

    expect($leftUser->status)->toBe('ex_member');
    expect(PeckLeaveInfo::query()->where('user_id', 910001)->exists())->toBeTrue();
    expect(PeckLeaveInfo::query()->where('user_id', 910001)->value('type'))->toBe(PeckLeaveInfo::TYPE_LEFT_SQUADRON);
});

test('peck refresh command dry run does not modify database', function () {
    PeckUser::factory()->create([
        'gaijin_id' => 920001,
        'username' => 'dry-run-existing',
        'status' => 'member',
    ]);

    Http::fake([
        'https://api.thunderinsights.dk/*' => Http::response([
            'clan' => [
                'members' => [
                    [
                        'uid' => 920002,
                        'nick' => 'dry-run-new',
                        'date' => 1_700_000_000,
                        'initiator' => null,
                    ],
                ],
            ],
        ], 200),
    ]);

    Artisan::call('peck:refresh-users', [
        '--dry-run' => true,
        '--squadron' => 'Order Of The Birb',
    ]);

    expect(PeckUser::query()->where('gaijin_id', 920002)->exists())->toBeFalse();
    expect(PeckUser::query()->where('gaijin_id', 920001)->value('status'))->toBe('member');
});
