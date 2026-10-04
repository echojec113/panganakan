<?php

use App\Models\User;

test('authenticated sensitive html page sends no-store cache-control', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();

    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('private')
        ->not->toContain('public');
});

test('guest login page is not marked no-store', function () {
    $response = $this->get(route('login'));

    $response->assertOk();

    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($cacheControl)->not->toContain('no-store');
});

test('logout then request to a protected route redirects to login', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('profile.edit'))->assertOk();

    $this->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));

    $this->get('/dashboard')->assertRedirect(route('login'));
});
