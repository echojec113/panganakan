<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Staff Account Ownership / Administration Boundary
|--------------------------------------------------------------------------
| The account lifecycle is Admin-controlled. Staff is self-service ONLY for
| personal profile details (name / email / password) and must never be able
| to administer accounts, change roles, or self-delete through /profile.
*/

test('admin can open manage staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)->get(route('staff.index'))
        ->assertOk()
        ->assertSee($staff->name);
});

test('staff receives 403 when opening manage staff', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('staff.index'))->assertForbidden();
});

test('admin can create a staff account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('staff.store'), [
        'name' => 'New Staff',
        'email' => 'newstaff@example.com',
        'password' => 'secret123',
    ])->assertRedirect(route('staff.index'));

    $created = User::where('email', 'newstaff@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('staff')
        ->and($created->name)->toBe('New Staff');
});

test('submitted role=admin during staff creation cannot create an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('staff.store'), [
        'name' => 'Malicious Staff',
        'email' => 'malicious@example.com',
        'password' => 'secret123',
        'role' => 'admin',
    ])->assertRedirect(route('staff.index'));

    $created = User::where('email', 'malicious@example.com')->first();

    expect($created->role)->toBe('staff');
    expect(User::where('role', 'admin')->count())->toBe(1);
});

test('created staff password is hashed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('staff.store'), [
        'name' => 'Hashed Staff',
        'email' => 'hashed@example.com',
        'password' => 'secret123',
    ]);

    $created = User::where('email', 'hashed@example.com')->first();

    expect($created->password)->not->toBe('secret123');
    expect(Hash::check('secret123', $created->password))->toBeTrue();
});

test('duplicate email is rejected during staff creation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'staff', 'email' => 'taken@example.com']);

    $response = $this->actingAs($admin)->post(route('staff.store'), [
        'name' => 'Dup Staff',
        'email' => 'taken@example.com',
        'password' => 'secret123',
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::where('email', 'taken@example.com')->count())->toBe(1);
});

test('staff cannot access the staff-create route', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('staff.create'))->assertForbidden();
});

test('staff cannot call the staff-store route', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->post(route('staff.store'), [
        'name' => 'Should Not Exist',
        'email' => 'shouldnot@example.com',
        'password' => 'secret123',
    ])->assertForbidden();

    expect(User::where('email', 'shouldnot@example.com')->count())->toBe(0);
});

test('staff cannot edit another staff account', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $target = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('staff.edit', $target))->assertForbidden();

    $this->actingAs($staff)->put(route('staff.update', $target), [
        'name' => 'Hijacked',
        'email' => 'hijacked@example.com',
    ])->assertForbidden();

    expect($target->refresh()->name)->not->toBe('Hijacked');
});

test('staff cannot delete another staff account', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $target = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->delete(route('staff.destroy', $target))->assertForbidden();

    expect($target->fresh())->not->toBeNull();
});

test('staff cannot delete their own account through the profile', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->delete('/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    $this->assertAuthenticated();
    expect($staff->fresh())->not->toBeNull();
});

test('admin can still remove a staff account through manage staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)->delete(route('staff.destroy', $target))
        ->assertRedirect(route('staff.index'));

    $this->assertSoftDeleted('users', ['id' => $target->id]);
});

test('profile update cannot modify role or account-administration fields', function () {
    $staff = User::factory()->create(['role' => 'staff', 'name' => 'Original Name']);

    $this->actingAs($staff)->patch('/profile', [
        'name' => 'Updated Name',
        'email' => $staff->email,
        'role' => 'admin',
        'approved' => 1,
        'is_administrator' => 1,
    ])->assertRedirect('/profile');

    $staff->refresh();

    expect($staff->role)->toBe('staff')
        ->and($staff->name)->toBe('Updated Name');
    expect(User::where('role', 'admin')->count())->toBe(0);
});