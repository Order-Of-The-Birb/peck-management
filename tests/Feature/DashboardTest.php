<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create([
        'name' => 'Dashboard User',
        'email' => 'dashboard-user@example.com',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('legacy platform sections redirect to the members dashboard', function () {
    $user = User::factory()->create([
        'name' => 'Legacy Section User',
        'email' => 'legacy-section-user@example.com',
    ]);
    $this->actingAs($user);

    $this->get(route('dashboard.leave-info'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard.alts'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard.context'))->assertRedirect(route('dashboard'));
});

test('authenticated users can visit the squadron logs section', function () {
    $user = User::factory()->create([
        'name' => 'Squadron Logs User',
        'email' => 'squadron-logs-user@example.com',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard.squadron.logs'));
    $response->assertOk();
});

test('authenticated users can visit the squadron applications section', function () {
    $user = User::factory()->create([
        'name' => 'Squadron Applications User',
        'email' => 'squadron-applications-user@example.com',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard.squadron.applications'));
    $response->assertOk();
});

test('authenticated users can visit the squadron management section', function () {
    $user = User::factory()->create([
        'name' => 'Squadron Management User',
        'email' => 'squadron-management-user@example.com',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard.squadron.management'));
    $response->assertOk();
});
