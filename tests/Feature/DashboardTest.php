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

test('authenticated users can visit the squadron logs section', function () {
    $user = User::query()->create([
        'name' => 'Squadron Logs User',
        'email' => 'squadron-logs-user@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard.squadron.logs'));
    $response->assertOk();
});

test('authenticated users can visit the squadron applications section', function () {
    $user = User::query()->create([
        'name' => 'Squadron Applications User',
        'email' => 'squadron-applications-user@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard.squadron.applications'));
    $response->assertOk();
});

test('authenticated users can visit the squadron management section', function () {
    $user = User::query()->create([
        'name' => 'Squadron Management User',
        'email' => 'squadron-management-user@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard.squadron.management'));
    $response->assertOk();
});
