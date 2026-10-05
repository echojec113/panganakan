<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

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

class StaffAccountLifecycleBatchTwoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // TestCase checks SQLite :memory: before any schema operation. No migrations run.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            foreach (['first_name', 'middle_name', 'last_name'] as $field) {
                $table->string($field, 100)->nullable();
            }
            $table->text('address')->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->date('birthday')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('staff');
            $table->string('profile_photo_path')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->string('module');
            $table->text('description');
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->integer('age');
            $table->unsignedBigInteger('assigned_staff_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_admin_can_archive_a_staff_account_through_manage_staff(): void
    {
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
    }

    public function test_archived_staff_page_shows_only_soft_deleted_staff_accounts(): void
    {
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
    }

    public function test_admin_can_restore_an_archived_staff_account_and_audit_the_restoration(): void
    {
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
    }

    public function test_staff_archive_and_restore_routes_are_admin_only(): void
    {
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
    }

    public function test_staff_archive_and_restore_routes_cannot_target_admin_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $archivedAdmin = User::factory()->create(['role' => 'admin']);
        $archivedAdmin->delete();

        $this->actingAs($admin)
            ->delete(route('staff.destroy', $otherAdmin))
            ->assertNotFound();

        $this->post(route('staff.restore', $archivedAdmin->id))
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('users', ['id' => $archivedAdmin->id]);
    }

    public function test_archived_staff_cannot_log_in_but_restored_staff_can(): void
    {
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
    }

    public function test_deactivation_and_reactivation_preserve_the_full_profile_password_assigned_records_and_audit_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::create(staffProfilePayload(['name' => 'Maria Santos Cruz', 'email' => 'retained@example.com', 'password' => 'staff-password']));
        $patient = \App\Models\Patient::create(['first_name' => 'Assigned', 'last_name' => 'Patient', 'age' => 29, 'assigned_staff_id' => $staff->id]);
        $original = $staff->getRawOriginal();
        $history = AuditLog::create(['user_id' => $staff->id, 'action' => 'UPDATE', 'module' => 'STAFF', 'description' => 'Historical entry']);

        $this->actingAs($admin)->delete(route('staff.destroy', $staff))->assertRedirect(route('staff.index'));
        $retained = User::withTrashed()->findOrFail($staff->id);
        expect($retained->trashed())->toBeTrue();
        foreach (['name', 'first_name', 'middle_name', 'last_name', 'address', 'contact_number', 'birthday', 'email', 'password'] as $field) {
            expect($retained->getRawOriginal($field))->toBe($original[$field]);
        }
        $this->post(route('staff.store'), staffProfilePayload(['name' => 'Another Staff', 'email' => $staff->email, 'password' => 'staff-password']))
            ->assertSessionHasErrors(['email' => 'This email is already used by an existing account, including deactivated accounts.']);
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'assigned_staff_id' => $staff->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $history->id, 'user_id' => $staff->id]);

        $this->post(route('staff.restore', $staff->id))->assertRedirect(route('staff.index'));
        $retained->refresh();
        expect($retained->deleted_at)->toBeNull();
        foreach (['name', 'first_name', 'middle_name', 'last_name', 'address', 'contact_number', 'birthday', 'email', 'password'] as $field) {
            expect($retained->getRawOriginal($field))->toBe($original[$field]);
        }
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'assigned_staff_id' => $staff->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $history->id, 'user_id' => $staff->id]);
    }

    public function test_deactivated_staff_cannot_recover_reset_or_implicitly_reactivate_their_account(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $password = $staff->password;
        $token = \Illuminate\Support\Facades\Password::createToken($staff);
        $staff->delete();
        $this->actingAs($admin)->post(route('staff.reset-account', $staff->id))->assertNotFound();
        $this->post(route('logout'));
        $this->post('/forgot-password', ['email' => $staff->email])->assertSessionHasErrors('email');
        $this->post(route('password.store'), ['token' => $token, 'email' => $staff->email, 'password' => 'NewSecurePassword123!', 'password_confirmation' => 'NewSecurePassword123!'])->assertSessionHasErrors('email');
        \Illuminate\Support\Facades\Notification::assertNothingSent();
        $this->assertSoftDeleted('users', ['id' => $staff->id]);
        expect(User::withTrashed()->findOrFail($staff->id)->password)->toBe($password);
    }

    public function test_staff_lifecycle_pages_explain_retained_accounts_and_expose_responsive_reactivation_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin)->get(route('staff.index'))->assertOk()
            ->assertSeeText('Deactivate Staff Account')->assertSeeText('Deactivate Account')
            ->assertSeeText('Deactivated Staff')->assertSeeText('records and history will be preserved');
        $staff->delete();
        $this->get(route('staff.archived'))->assertOk()->assertSeeText('Deactivated Staff')
            ->assertSeeText('Reactivate Account')->assertSeeText('cannot sign in')
            ->assertSee('@container (max-width: 900px)', false)->assertSee('data-label="Email"', false)
            ->assertDontSee('min-w-[760px]', false)->assertDontSee('>Restore</button>', false);
    }

    public function test_active_staff_actor_is_resolved_and_displayed_in_audit_logs(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Historical Staff Actor']);
        $log = AuditLog::create(['user_id' => $staff->id, 'action' => 'UPDATE', 'module' => 'STAFF', 'description' => 'Historical activity']);

        $this->assertSame($staff->id, $log->fresh()->user->id);
        $this->assertSame($staff->id, AuditLog::with('user')->findOrFail($log->id)->user->id);
        $this->get(route('audit-logs.index'))->assertOk()
            ->assertSee('<span class="audit-name">Historical Staff Actor</span>', false);
    }

    public function test_historical_actor_and_log_ownership_survive_deactivation_and_reactivation(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Retained Historical Actor']);
        $log = AuditLog::create(['user_id' => $staff->id, 'action' => 'UPDATE', 'module' => 'STAFF', 'description' => 'Historical activity']);
        $originalLog = $log->fresh()->getRawOriginal();
        $userCount = User::withTrashed()->count();

        $this->delete(route('staff.destroy', $staff))->assertRedirect(route('staff.index'));
        $deletedAt = User::withTrashed()->findOrFail($staff->id)->getRawOriginal('deleted_at');
        $this->assertNotNull($deletedAt);
        $this->assertSame($originalLog, $log->fresh()->getRawOriginal());
        $this->assertSame($staff->id, $log->fresh()->user->id);
        $actor = AuditLog::with('user')->findOrFail($log->id)->user;
        $this->assertSame($staff->id, $actor->id);
        $this->assertTrue($actor->trashed());
        $this->get(route('audit-logs.index'))->assertOk()
            ->assertSee('<span class="audit-name">Retained Historical Actor</span>', false);
        $this->assertSame($deletedAt, User::withTrashed()->findOrFail($staff->id)->getRawOriginal('deleted_at'));
        $this->assertSame($userCount, User::withTrashed()->count());
        $this->assertNull(User::find($staff->id));

        $this->post(route('staff.restore', $staff->id))->assertRedirect(route('staff.index'));
        $this->assertSame($originalLog, $log->fresh()->getRawOriginal());
        $this->assertSame($staff->id, $log->fresh()->user->id);
        $actor = AuditLog::with('user')->findOrFail($log->id)->user;
        $this->assertSame($staff->id, $actor->id);
        $this->assertFalse($actor->trashed());
        $this->get(route('audit-logs.index'))->assertOk()
            ->assertSee('<span class="audit-name">Retained Historical Actor</span>', false);
        $this->assertSame($userCount, User::withTrashed()->count());
    }
}
