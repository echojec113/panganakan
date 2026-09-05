<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can access their own profile', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
});

test('staff can access their own profile', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('profile.edit'))->assertOk();
});

test('guest cannot access the profile', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('profile displays the current authenticated users data', function () {
    $user = User::factory()->create([
        'name' => 'Maria Clinic',
        'email' => 'maria@clinic.test',
        'role' => 'staff',
    ]);

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();
    $response->assertSee('Maria Clinic', false);
    $response->assertSee('maria@clinic.test', false);
});

test('profile update changes only the current authenticated user', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $other = User::factory()->create(['role' => 'staff', 'name' => 'Other Person']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Renamed User',
        'email' => 'renamed@clinic.test',
    ])->assertRedirect(route('profile.edit'));

    expect($user->fresh()->name)->toBe('Renamed User');
    expect($user->fresh()->email)->toBe('renamed@clinic.test');
    expect($other->fresh()->name)->toBe('Other Person');
    expect($other->fresh()->email)->not->toBe('renamed@clinic.test');
});

test('email uniqueness validation preserves the current users own email', function () {
    $user = User::factory()->create(['role' => 'staff']);

    // Same email: allowed (ignored for self)
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Self', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    // Another user's email: rejected
    $other = User::factory()->create(['role' => 'staff']);
    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Self',
        'email' => $other->email,
    ]);
    $response->assertSessionHasErrors('email');
});

test('role cannot be modified through the profile request', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Trying Role Change',
        'email' => $user->email,
        'role' => 'admin',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->role)->toBe('staff');
});

test('staff cannot escalate to admin via profile tampering', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Trying Role Change',
        'email' => $user->email,
        'role' => 'admin',
    ]);

    expect($user->fresh()->role)->toBe('staff');

    // The escalated request still cannot reach admin-only pages.
    $this->actingAs($user->fresh())->get(route('staff.index'))->assertForbidden();
});

test('password update hashes the new password and leaves the role intact', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    $fresh = $user->fresh();
    expect(Hash::check('new-password', $fresh->password))->toBeTrue();
    expect($fresh->role)->toBe('staff');
});

test('dashboard header links to the authenticated users profile', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee(route('profile.edit'), false);
    $response->assertSee($staff->name, false);
    $response->assertSee('class="user-role">staff</div>', false);

    // The notification bell is now a real button, not the old dead anchor.
    $response->assertSee('<button type="button" class="icon-btn" id="notifBell"', false);
});

test('existing logout behavior still works', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});