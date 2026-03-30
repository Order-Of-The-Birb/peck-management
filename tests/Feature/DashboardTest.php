<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::query()->create([
        'name' => 'Dashboard User',
        'email' => 'dashboard-user@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users can visit the leave info dashboard section', function () {
    $user = User::query()->create([
        'name' => 'Leave Info User',
        'email' => 'leave-info-user@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard.leave-info'));
    $response->assertOk();
});
