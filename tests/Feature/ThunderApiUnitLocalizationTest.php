<?php

use App\Actions\ThunderApi;
use App\Actions\ThunderApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('peck.thunderapi_base_url', 'https://thunder.example');
});

test('unit localization returns the localized short and long names', function () {
    Http::fake([
        'https://thunder.example/v1/units/*/localization*' => Http::response([
            'short' => 'Leopard 2PL',
            'long' => 'Leopard 2PL',
        ], 200),
    ]);

    $localization = app(ThunderApi::class)->localizeUnit(null, 'germ_leopard_2pl', 'en');

    expect($localization)->toBe([
        'short' => 'Leopard 2PL',
        'long' => 'Leopard 2PL',
    ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v1/units/germ_leopard_2pl/localization')
            && str_contains($request->url(), 'language=en');
    });
});

test('unit localization surfaces malformed responses', function () {
    Http::fake([
        'https://thunder.example/v1/units/*/localization*' => Http::response(['unexpected' => true], 200),
    ]);

    app(ThunderApi::class)->localizeUnit(null, 'germ_leopard_2pl');
})->throws(ThunderApiException::class, 'Malformed unit localization response.');
