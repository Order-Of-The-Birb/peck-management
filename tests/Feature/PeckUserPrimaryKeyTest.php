<?php

use App\Models\PeckUser;

test('peck user uses gaijin id as the primary key for find operations', function () {
    $peckUser = PeckUser::factory()->create([
        'gaijin_id' => 3989601,
    ]);

    $resolvedUser = PeckUser::query()->findOrFail(3989601);

    expect($resolvedUser->is($peckUser))->toBeTrue()
        ->and($resolvedUser->gaijin_id)->toBe(3989601);
});
