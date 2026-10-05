<?php

namespace Tests\Feature;

use App\Mail\StaffCredentialMail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffProfileBatchOneTest extends TestCase
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
        Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Cruz',
            'address' => '123 Clinic Road', 'contact_number' => '09171234567',
            'birthday' => '1990-05-12', 'email' => 'maria@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ], $overrides);
    }

    private function staff(): User
    {
        return User::create($this->payload() + ['name' => 'Maria Santos Cruz', 'role' => 'staff']);
    }

    public function test_create_persists_profile_forces_role_and_preserves_credential_email(): void
    {
        $this->post(route('staff.store'), $this->payload([
            'first_name' => '  Maria   Elena ', 'middle_name' => ' Santos ',
            'role' => 'admin', 'name' => 'Injected name',
        ]))->assertRedirect(route('staff.index'));
        $staff = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('Maria Elena Santos Cruz', $staff->name);
        $this->assertSame('Maria Elena', $staff->first_name);
        $this->assertSame('Santos', $staff->middle_name);
        $this->assertSame('Cruz', $staff->last_name);
        $this->assertSame('123 Clinic Road', $staff->address);
        $this->assertSame('09171234567', $staff->contact_number);
        $this->assertSame('1990-05-12', $staff->birthday->toDateString());
        $this->assertSame('staff', $staff->role);
        $this->assertTrue(Hash::check('secret123', $staff->password));
        Mail::assertSent(StaffCredentialMail::class, fn ($mail) => $mail->hasTo($staff->email)
            && $mail->plainTextPassword === 'secret123');
        $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE', 'module' => 'STAFF']);
    }

    public function test_blank_middle_name_is_optional_and_has_no_double_spaces(): void
    {
        $this->post(route('staff.store'), $this->payload(['middle_name' => '   ']))
            ->assertRedirect(route('staff.index'));
        $staff = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('Maria Cruz', $staff->name);
        $this->assertEmpty($staff->middle_name);
    }

    public static function invalidFields(): array
    {
        $cases = [];
        foreach (['first_name', 'last_name', 'address', 'contact_number', 'birthday', 'email'] as $field) {
            $cases['required '.$field] = [$field, ''];
        }
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $cases['length '.$field] = [$field, str_repeat('A', 101)];
        }
        return $cases + [
            'address length' => ['address', str_repeat('A', 1001)],
            'email length' => ['email', str_repeat('a', 250).'@example.com'],
            'email format' => ['email', 'invalid'],
            'invalid contact' => ['contact_number', '123456'],
            'landline' => ['contact_number', '0281234567'],
            'invalid date' => ['birthday', '2026-02-30'],
            'short password' => ['password', 'short'],
            'password confirmation' => ['password_confirmation', 'different'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_profile_is_rejected(string $field, string $value): void
    {
        $error = $field === 'password_confirmation' ? 'password' : $field;
        $this->post(route('staff.store'), $this->payload([$field => $value]))->assertSessionHasErrors($error);
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
        Mail::assertNothingSent();
        if (!in_array($field, ['password', 'password_confirmation'], true)) {
            $staff = $this->staff();
            $before = $staff->fresh()->getAttributes();
            $this->put(route('staff.update', $staff), $this->payload([$field => $value]))
                ->assertSessionHasErrors($error);
            $this->assertSame($before, $staff->fresh()->getAttributes());
        }
    }

    public static function contacts(): array
    {
        return [
            ['09171234567', '09171234567'], ['0917 123 4567', '09171234567'],
            ['+639171234567', '+639171234567'], ['+63 917-123-4567', '+639171234567'],
            ['639171234567', '639171234567'],
        ];
    }

    #[DataProvider('contacts')]
    public function test_supported_contacts_remain_strings(string $input, string $stored): void
    {
        $this->post(route('staff.store'), $this->payload(['contact_number' => $input]))
            ->assertSessionHasNoErrors();
        $this->assertSame($stored, User::where('email', 'maria@example.com')->firstOrFail()->contact_number);
    }

    public function test_future_birthday_and_combined_name_limit_are_rejected(): void
    {
        $this->post(route('staff.store'), $this->payload(['birthday' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('birthday');
        $this->post(route('staff.store'), $this->payload([
            'first_name' => str_repeat('A', 100), 'middle_name' => str_repeat('B', 100),
            'last_name' => str_repeat('C', 100),
        ]))->assertSessionHasErrors('name');
    }

    public function test_today_is_an_allowed_birthday_and_missing_confirmation_is_rejected(): void
    {
        $payload = $this->payload(['birthday' => today()->toDateString()]);
        unset($payload['password_confirmation']);
        $this->post(route('staff.store'), $payload)->assertSessionHasErrors('password');
        $this->post(route('staff.store'), $this->payload(['birthday' => today()->toDateString()]))
            ->assertSessionHasNoErrors();
    }

    public function test_active_and_archived_email_duplicates_are_rejected(): void
    {
        $staff = $this->staff();
        $this->post(route('staff.store'), $this->payload())->assertSessionHasErrors('email');
        $staff->delete();
        $this->post(route('staff.store'), $this->payload())->assertSessionHasErrors('email');
    }

    public function test_pages_render_and_edit_populates_all_saved_fields(): void
    {
        $this->get(route('staff.create'))->assertOk()->assertSee('First Name')->assertSee('Confirm Password');
        $staff = $this->staff();
        $response = $this->get(route('staff.edit', $staff))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (array_diff_key($this->payload(), array_flip(['password', 'password_confirmation'])) as $field => $value) {
            $element = $xpath->query('//*[@name="'.$field.'"]')->item(0);
            $this->assertNotNull($element, $field);
            $this->assertSame($value, $field === 'address' ? trim($element->textContent) : $element->getAttribute('value'), $field);
        }
        $this->assertSame(0, $xpath->query('//*[@name="password" or @name="role" or @name="deleted_at"]')->length);
    }

    public function test_update_changes_one_field_without_erasing_other_profile_or_account_data(): void
    {
        $staff = $this->staff();
        $before = $staff->getAttributes();
        $this->put(route('staff.update', $staff), $this->payload([
            'address' => '456 Updated Road', 'role' => 'admin', 'password' => 'malicious',
            'deleted_at' => now()->toDateTimeString(),
        ]))->assertRedirect(route('staff.index'));
        $after = $staff->fresh()->getAttributes();
        foreach (['name', 'first_name', 'middle_name', 'last_name', 'contact_number', 'birthday', 'email', 'password', 'role', 'deleted_at'] as $field) {
            $this->assertSame($before[$field] ?? null, $after[$field] ?? null, $field);
        }
        $this->assertSame('456 Updated Road', $after['address']);
        $this->put(route('staff.update', $staff), $this->payload(['middle_name' => '', 'last_name' => 'Reyes']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Maria Reyes', $staff->fresh()->name);
    }

    public function test_legacy_staff_name_is_visible_without_guessing_or_mutating_it(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Legacy Full Name']);
        $this->get(route('staff.edit', $staff))->assertOk()->assertSee('Legacy Full Name')
            ->assertSee('value=""', false);
        $this->assertNull($staff->fresh()->first_name);
        $this->assertSame('Legacy Full Name', $staff->fresh()->name);
        $this->put(route('staff.update', $staff), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('Maria Santos Cruz', $staff->fresh()->name);
    }

    public function test_validation_retry_keeps_safe_values_and_never_flashes_passwords(): void
    {
        $staff = $this->staff();
        $this->from(route('staff.edit', $staff))->put(route('staff.update', $staff), $this->payload([
            'first_name' => 'Attempted Name', 'contact_number' => 'invalid',
        ]))->assertSessionHasErrors('contact_number');
        $this->get(route('staff.edit', $staff))->assertOk()->assertSee('value="Attempted Name"', false)
            ->assertSee('Enter a Philippine mobile number');
        $this->assertSame('Maria', $staff->fresh()->first_name);
        $this->post(route('staff.store'), $this->payload(['password_confirmation' => 'wrong']))
            ->assertSessionHasErrors('password');
        $old = session()->getOldInput();
        $this->assertArrayNotHasKey('password', $old);
        $this->assertArrayNotHasKey('password_confirmation', $old);
        $this->get(route('staff.create'))->assertOk()->assertDontSee('value="secret123"', false);
    }

    public function test_admin_targets_and_staff_actors_are_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $original = $admin->name;
        $this->get(route('staff.edit', $admin))->assertForbidden();
        $this->put(route('staff.update', $admin), $this->payload())->assertForbidden();
        $this->assertSame($original, $admin->fresh()->name);
        $staff = $this->staff();
        $this->actingAs($staff)->get(route('staff.create'))->assertForbidden();
        $this->post(route('staff.store'), $this->payload())->assertForbidden();
        $this->get(route('staff.edit', $staff))->assertForbidden();
        $this->put(route('staff.update', $staff), $this->payload())->assertForbidden();
    }
}
