<?php

use App\Mail\StaffCredentialMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Staff Account Ownership / Administration Boundary
|--------------------------------------------------------------------------
| The account lifecycle is Admin-controlled. Staff is self-service ONLY for
| personal profile details (name / email / password) and must never be able
| to administer accounts, change roles, or self-delete through /profile.
*/

beforeEach(function () {
    // The six profile columns normally come from the migration
    // 2026_10_05_000002_add_profile_fields_to_users_table. Add them only
    // when the running schema path has not created them yet.
    $missing = array_filter(
        ['first_name', 'middle_name', 'last_name', 'address', 'contact_number', 'birthday'],
        fn (string $column) => !\Illuminate\Support\Facades\Schema::hasColumn('users', $column)
    );

    if ($missing === []) {
        return;
    }

    \Illuminate\Support\Facades\Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) use ($missing) {
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (in_array($field, $missing, true)) {
                $table->string($field, 100)->nullable();
            }
        }
        if (in_array('address', $missing, true)) {
            $table->text('address')->nullable();
        }
        if (in_array('contact_number', $missing, true)) {
            $table->string('contact_number', 30)->nullable();
        }
        if (in_array('birthday', $missing, true)) {
            $table->date('birthday')->nullable();
        }
    });
});

function staffProfilePayload(array $overrides): array
{
    // Explicit fixture components preserve the existing tests' expected full names.
    $parts = explode(' ', $overrides['name']);
    $last = array_pop($parts);
    return array_merge([
        'first_name' => implode(' ', $parts),
        'middle_name' => null,
        'last_name' => $last,
        'address' => 'Test clinic address',
        'contact_number' => '09171234567',
        'birthday' => '1990-05-12',
        'password_confirmation' => $overrides['password'],
    ], $overrides);
}

test('admin can open manage staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $otherStaff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)->get(route('staff.index'))
        ->assertOk()
        ->assertSee($staff->name)
        ->assertSee('data-archive-url="'.route('staff.destroy', $staff).'"', false)
        ->assertSee('data-archive-url="'.route('staff.destroy', $otherStaff).'"', false)
        ->assertSee(route('staff.archived'), false)
        ->assertSee('Deactivate Staff Account')
        ->assertSee('the account can be reactivated later.')
        ->assertSee('title="Deactivate Account"', false)
        ->assertSee('onclick="confirmArchiveStaff(this)"', false)
        ->assertSee('type="submit" id="confirmArchiveStaffButton"', false)
        ->assertSee('.staff-modal .staff-delete { background: #dc2626; }', false)
        ->assertSee('archiveStaffForm.action = archiveUrl', false)
        ->assertSee("archiveStaffForm.removeAttribute('action')", false)
        ->assertDontSee('permanently delete');
});

test('staff receives 403 when opening manage staff', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('staff.index'))->assertForbidden();
});

test('admin can create a staff account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('staff.store'), staffProfilePayload([
        'name' => 'New Staff',
        'email' => 'newstaff@example.com',
        'password' => 'secret123',
    ]))->assertRedirect(route('staff.index'));

    $created = User::where('email', 'newstaff@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('staff')
        ->and($created->name)->toBe('New Staff');
});

test('creating a staff account emails the entered credentials without persisting the plaintext password', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $email = 'credentials@example.com';
    $plainTextPassword = 'secret123';

    $this->actingAs($admin)->post(route('staff.store'), staffProfilePayload([
        'name' => 'Credential Staff',
        'email' => $email,
        'password' => $plainTextPassword,
    ]))->assertRedirect(route('staff.index'));

    $created = User::where('email', $email)->first();

    expect($created)->not->toBeNull()
        ->and($created->password)->not->toBe($plainTextPassword)
        ->and(Hash::check($plainTextPassword, $created->password))->toBeTrue();

    Mail::assertSent(StaffCredentialMail::class, function (StaffCredentialMail $mail) use ($email, $plainTextPassword) {
        $rendered = $mail->render();

        return $mail->hasTo($email)
            && str_contains($rendered, 'Login email: <strong>'.$email.'</strong>')
            && str_contains($rendered, 'Password: <strong>'.$plainTextPassword.'</strong>')
            && str_contains($rendered, 'Your DEPLA Family Care Staff account has been created successfully.');
    });
});

test('submitted role=admin during staff creation cannot create an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('staff.store'), staffProfilePayload([
        'name' => 'Malicious Staff',
        'email' => 'malicious@example.com',
        'password' => 'secret123',
        'role' => 'admin',
    ]))->assertRedirect(route('staff.index'));

    $created = User::where('email', 'malicious@example.com')->first();

    expect($created->role)->toBe('staff');
    expect(User::where('role', 'admin')->count())->toBe(1);
});

test('created staff password is hashed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('staff.store'), staffProfilePayload([
        'name' => 'Hashed Staff',
        'email' => 'hashed@example.com',
        'password' => 'secret123',
    ]));

    $created = User::where('email', 'hashed@example.com')->first();

    expect($created->password)->not->toBe('secret123');
    expect(Hash::check('secret123', $created->password))->toBeTrue();
});

test('duplicate email is rejected during staff creation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'staff', 'email' => 'taken@example.com']);

    $response = $this->actingAs($admin)->post(route('staff.store'), staffProfilePayload([
        'name' => 'Dup Staff',
        'email' => 'taken@example.com',
        'password' => 'secret123',
    ]));

    $response->assertSessionHasErrors('email');
    expect(User::where('email', 'taken@example.com')->count())->toBe(1);
});

test('staff cannot access the staff-create route', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('staff.create'))->assertForbidden();
});

test('staff cannot call the staff-store route', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->post(route('staff.store'), staffProfilePayload([
        'name' => 'Should Not Exist',
        'email' => 'shouldnot@example.com',
        'password' => 'secret123',
    ]))->assertForbidden();

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

test('admin can archive a staff account through manage staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)->delete(route('staff.destroy', $target))
        ->assertRedirect(route('staff.index'))
        ->assertSessionHas('success', 'Staff account deactivated successfully.');

    $this->assertSoftDeleted('users', ['id' => $target->id]);
    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'name' => $target->name,
    ]);

    expect(AuditLog::where('action', 'ARCHIVE')
        ->where('module', 'STAFF')
        ->where('description', 'Staff account deactivated: '.$target->name)
        ->exists())->toBeTrue();

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertDontSeeText($target->name);
});

test('archived staff page shows only soft-deleted staff accounts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $archivedStaff = User::factory()->create(['role' => 'staff', 'name' => 'Archived Staff Member']);
    $archivedAdmin = User::factory()->create(['role' => 'admin', 'name' => 'Archived Admin Account']);
    $activeStaff = User::factory()->create(['role' => 'staff', 'name' => 'Active Staff Member']);

    $archivedStaff->delete();
    $archivedAdmin->delete();

    $this->actingAs($admin)
        ->get(route('staff.archived'))
        ->assertOk()
        ->assertSeeText('Deactivated Staff')
        ->assertSeeText('Archived Staff Member')
        ->assertSeeText($archivedStaff->email)
        ->assertSeeText('Staff')
        ->assertSee(route('staff.restore', $archivedStaff->id), false)
        ->assertDontSeeText('Archived Admin Account')
        ->assertDontSeeText('Active Staff Member');
});

test('admin can restore an archived staff account and audit the restoration', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff', 'name' => 'Restored Staff Member']);
    $staff->delete();

    $this->actingAs($admin)
        ->post(route('staff.restore', $staff->id))
        ->assertRedirect(route('staff.index'))
        ->assertSessionHas('success', 'Staff account reactivated successfully.');

    $this->assertDatabaseHas('users', [
        'id' => $staff->id,
        'name' => 'Restored Staff Member',
        'deleted_at' => null,
    ]);

    expect(AuditLog::where('action', 'RESTORE')
        ->where('module', 'STAFF')
        ->where('description', 'Staff account reactivated: Restored Staff Member')
        ->exists())->toBeTrue();

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSeeText('Restored Staff Member');

    $this->get(route('staff.archived'))
        ->assertOk()
        ->assertDontSeeText('Restored Staff Member');
});

test('staff archive and restore routes are admin-only', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $archivedStaff = User::factory()->create(['role' => 'staff']);
    $archivedStaff->delete();

    $this->actingAs($staff)
        ->get(route('staff.archived'))
        ->assertForbidden();

    $this->post(route('staff.restore', $archivedStaff->id))
        ->assertForbidden();

    $this->delete(route('staff.destroy', $staff))
        ->assertForbidden();

    $this->assertSoftDeleted('users', ['id' => $archivedStaff->id]);
    $this->assertDatabaseHas('users', [
        'id' => $staff->id,
        'deleted_at' => null,
    ]);
});

test('staff archive and restore routes cannot target admin accounts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $archivedAdmin = User::factory()->create(['role' => 'admin']);
    $archivedAdmin->delete();

    $this->actingAs($admin)
        ->delete(route('staff.destroy', $admin))
        ->assertNotFound();

    $this->post(route('staff.restore', $archivedAdmin->id))
        ->assertNotFound();

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'deleted_at' => null,
    ]);
    $this->assertSoftDeleted('users', ['id' => $archivedAdmin->id]);
});

test('archived staff cannot log in but restored staff can', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create([
        'role' => 'staff',
        'password' => Hash::make('staff-password'),
    ]);

    $this->actingAs($admin)->delete(route('staff.destroy', $staff));
    $this->post(route('logout'));

    $this->post(route('login'), [
        'email' => $staff->email,
        'password' => 'staff-password',
    ])->assertSessionHasErrors('email');

    $this->actingAs($admin)->post(route('staff.restore', $staff->id));
    $this->post(route('logout'));

    $this->post(route('login'), [
        'email' => $staff->email,
        'password' => 'staff-password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($staff);
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
