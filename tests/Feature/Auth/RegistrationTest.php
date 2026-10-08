<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Public Registration — CLOSED (Phase 1 security hardening)
|--------------------------------------------------------------------------
| GET/POST /register answer 404 for everyone. Admin-managed staff creation
| via StaffController is a separate workflow and remains functional.
*/

test('guest cannot view the registration screen', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('guest cannot create a user through public registration', function () {
    $usersBefore = User::count();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    expect(User::count())->toBe($usersBefore);
});

test('direct HTTP requests cannot bypass the registration closure', function () {
    $usersBefore = User::count();

    $this->postJson('/register', [
        'name' => 'Bypass Attempt',
        'email' => 'bypass@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->getJson('/register')->assertNotFound();

    expect(User::count())->toBe($usersBefore);
});

test('authenticated users posting to registration are redirected without creating users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $usersBefore = User::count();

    $response = $this->actingAs($admin)->post('/register', [
        'name' => 'Redirected User',
        'email' => 'redirected@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(User::count())->toBe($usersBefore);
});

test('admin can still create staff accounts while public registration stays closed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post(route('staff.store'), [
        'first_name' => 'Sample',
        'middle_name' => null,
        'last_name' => 'Staff',
        'address' => 'Test clinic address',
        'contact_number' => '09171234567',
        'birthday' => '1990-05-12',
        'email' => 'sample.staff@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('staff.index'));

    $staff = User::where('email', 'sample.staff@example.com')->first();
    expect($staff)->not->toBeNull();
    expect($staff->role)->toBe('staff');
});
