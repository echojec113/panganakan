<?php

namespace Tests\Feature;

use App\Mail\StaffCredentialMail;
use App\Models\User;
use App\Services\StaffAddressFormatter;
use Illuminate\Support\Arr;
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
            'address_line' => '123 Clinic Road', 'barangay' => 'San Jose', 'city_municipality' => 'Santa Maria',
            'replace_address' => '1', 'contact_number' => '09171234567',
            'birthday' => '1990-05-12', 'email' => 'maria@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ], $overrides);
    }

    private function staff(): User
    {
        return User::create(Arr::except($this->payload(), [...StaffAddressFormatter::FIELDS, 'replace_address', 'password_confirmation'])
            + ['name' => 'Maria Santos Cruz', 'role' => 'staff', 'address' => StaffAddressFormatter::format($this->payload())]);
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
        $this->assertSame("123 Clinic Road\nBarangay San Jose\nSanta Maria", $staff->address);
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
        foreach (['first_name', 'last_name', 'address_line', 'barangay', 'city_municipality', 'contact_number', 'birthday', 'email'] as $field) {
            $cases['required '.$field] = [$field, ''];
        }
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $cases['length '.$field] = [$field, str_repeat('A', 101)];
        }
        return $cases + [
            'address length' => ['address_line', str_repeat('A', 256)],
            'barangay length' => ['barangay', str_repeat('A', 256)],
            'city length' => ['city_municipality', str_repeat('A', 256)],
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
        foreach (array_diff_key($this->payload(), array_flip(['password', 'password_confirmation', 'replace_address'])) as $field => $value) {
            $element = $xpath->query('//*[@name="'.$field.'"]')->item(0);
            $this->assertNotNull($element, $field);
            $this->assertSame($value, $element->getAttribute('value'), $field);
        }
        $this->assertSame(0, $xpath->query('//*[@name="password" or @name="role" or @name="deleted_at"]')->length);
    }

    public function test_update_changes_one_field_without_erasing_other_profile_or_account_data(): void
    {
        $staff = $this->staff();
        $before = $staff->getAttributes();
        $this->put(route('staff.update', $staff), $this->payload([
            'address_line' => '456 Updated Road', 'role' => 'admin', 'password' => 'malicious',
            'deleted_at' => now()->toDateTimeString(),
        ]))->assertRedirect(route('staff.index'));
        $after = $staff->fresh()->getAttributes();
        foreach (['name', 'first_name', 'middle_name', 'last_name', 'contact_number', 'birthday', 'email', 'password', 'role', 'deleted_at'] as $field) {
            $this->assertSame($before[$field] ?? null, $after[$field] ?? null, $field);
        }
        $this->assertSame("456 Updated Road\nBarangay San Jose\nSanta Maria", $after['address']);
        $this->put(route('staff.update', $staff), $this->payload(['middle_name' => '', 'last_name' => 'Reyes']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Maria Reyes', $staff->fresh()->name);
    }

    public function test_legacy_staff_name_is_visible_without_guessing_or_mutating_it(): void
    {
        // A four-token multi-particle name cannot be safely separated, so the
        // original full name stays visible for manual entry and is never mutated.
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Maria De la Cruz']);
        $this->get(route('staff.edit', $staff))->assertOk()->assertSee('Maria De la Cruz')
            ->assertSee('value=""', false);
        $this->assertNull($staff->fresh()->first_name);
        $this->assertSame('Maria De la Cruz', $staff->fresh()->name);
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

    public function test_both_address_forms_reuse_patient_autocomplete_once(): void
    {
        $staff = $this->staff();
        foreach ([route('staff.create'), route('staff.edit', $staff)] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('Address Information');
            foreach (StaffAddressFormatter::FIELDS as $field) {
                $response->assertSee('name="'.$field.'"', false);
            }
            $response->assertDontSee('name="address"', false)
                ->assertSee('var DEBOUNCE_MS = 350', false)
                ->assertSee('aria-activedescendant', false)
                ->assertSee('Location search is temporarily unavailable.');
            $this->assertSame(1, substr_count($response->getContent(), 'var DEBOUNCE_MS = 350'));
        }
    }

    public function test_creation_trims_components_and_ignores_combined_address(): void
    {
        $this->post(route('staff.store'), $this->payload([
            'address_line' => '  12 Road  ', 'barangay' => ' Barangay Barangay San Jose ',
            'city_municipality' => ' Santa Maria ', 'address' => 'Injected address',
        ]))->assertSessionHasNoErrors();
        $this->assertSame("12 Road\nBarangay San Jose\nSanta Maria", User::where('email', 'maria@example.com')->firstOrFail()->address);
    }

    public function test_legacy_address_is_preserved_without_explicit_replacement(): void
    {
        $staff = $this->staff();
        foreach (["Near chapel, old district\r\nUnmarked road", null] as $original) {
            $staff->update(['address' => $original]);
            $payload = Arr::except($this->payload(['last_name' => 'Reyes', 'address' => 'Injected']), ['replace_address']);
            $this->put(route('staff.update', $staff), $payload)->assertSessionHasNoErrors();
            $this->assertSame($original, $staff->fresh()->address);
            $this->assertSame('Reyes', $staff->fresh()->last_name);
        }
        $staff->update(['address' => 'Old road, unknown barangay']);
        $this->get(route('staff.edit', $staff))->assertOk()->assertSee('Old road, unknown barangay')
            ->assertSee('Replace saved address')->assertSee('value=""', false);
    }

    public function test_explicit_legacy_replacement_and_validation_retry(): void
    {
        $staff = $this->staff();
        $staff->update(['address' => 'Original legacy address']);
        $this->from(route('staff.edit', $staff))->put(route('staff.update', $staff), $this->payload([
            'address_line' => 'Attempted Road', 'barangay' => '   ',
        ]))->assertSessionHasErrors('barangay');
        $this->assertSame('Original legacy address', $staff->fresh()->address);
        $this->get(route('staff.edit', $staff))->assertOk()->assertSee('value="Attempted Road"', false)
            ->assertSee('name="replace_address" value="1" checked', false);
        $this->put(route('staff.update', $staff), $this->payload(['address' => 'Injected']))->assertSessionHasNoErrors();
        $this->assertSame(StaffAddressFormatter::format($this->payload()), $staff->fresh()->address);
        $this->get(route('staff.edit', $staff))->assertOk()->assertDontSee('type="checkbox" name="replace_address"', false)
            ->assertSee('value="San Jose"', false)->assertSee('value="Santa Maria"', false);
    }

    public function test_new_staff_address_prefills_enabled_edit_inputs_and_edits_without_opt_in(): void
    {
        $this->post(route('staff.store'), Arr::except($this->payload(), ['replace_address']))->assertSessionHasNoErrors();
        $staff = User::where('email', 'maria@example.com')->firstOrFail();
        $response = $this->get(route('staff.edit', $staff))->assertOk()
            ->assertDontSee('type="checkbox" name="replace_address"', false);
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//*[@id="staff-address-components"][@disabled]')->length);
        foreach (StaffAddressFormatter::FIELDS as $field) {
            $this->assertSame($this->payload()[$field], $xpath->query('//*[@name="'.$field.'"]')->item(0)->getAttribute('value'));
        }
        $this->put(route('staff.update', $staff), Arr::except($this->payload([
            'city_municipality' => 'Makati', 'address' => 'Injected combined address',
        ]), ['replace_address']))->assertSessionHasNoErrors();
        $this->assertSame("123 Clinic Road\nBarangay San Jose\nMakati", $staff->fresh()->address);
    }

    public function test_unchanged_structured_address_is_omitted_from_the_persistence_payload(): void
    {
        $staff = $this->staff();
        $payload = Arr::except($this->payload(['last_name' => 'Reyes', 'name' => 'Maria Santos Reyes']), ['replace_address']);
        $request = \App\Http\Requests\StaffProfileRequest::create('/staff/'.$staff->id, 'PUT', $payload);
        $route = new \Illuminate\Routing\Route('PUT', 'staff/{staff}', fn () => null);
        $route->bind($request);
        $route->setParameter('staff', $staff);
        $request->setRouteResolver(fn () => $route);
        $request->setValidator(\Illuminate\Support\Facades\Validator::make($payload, $request->rules()));
        $this->assertArrayNotHasKey('address', $request->profileData());
        $before = $staff->address;
        $this->put(route('staff.update', $staff), $payload)->assertSessionHasNoErrors();
        $this->assertSame($before, $staff->fresh()->address);
        $this->assertSame('Reyes', $staff->fresh()->last_name);
    }

    public function test_direct_edit_validation_preserves_old_input_without_replacement_checkbox(): void
    {
        $staff = $this->staff();
        $before = $staff->address;
        $payload = Arr::except($this->payload(['address_line' => 'Changed road', 'barangay' => '   ']), ['replace_address']);
        $this->from(route('staff.edit', $staff))->put(route('staff.update', $staff), $payload)->assertSessionHasErrors('barangay');
        $this->assertSame($before, $staff->fresh()->address);
        $this->get(route('staff.edit', $staff))->assertOk()->assertSee('value="Changed road"', false)
            ->assertSee('value=""', false)->assertDontSee('type="checkbox" name="replace_address"', false);
        $this->put(route('staff.update', $staff), Arr::except($this->payload(), ['replace_address', 'barangay']))
            ->assertSessionHasErrors('barangay');
        $this->assertSame($before, $staff->fresh()->address);
    }

    public function test_invalid_component_types_whitespace_and_newlines_are_rejected(): void
    {
        foreach (StaffAddressFormatter::FIELDS as $field) {
            foreach ([[], '   ', "First\nSecond", str_repeat('x', 256)] as $value) {
                $this->post(route('staff.store'), $this->payload([$field => $value]))->assertSessionHasErrors($field);
            }
            $this->post(route('staff.store'), Arr::except($this->payload(), [$field]))->assertSessionHasErrors($field);
        }
        $this->post(route('staff.store'), $this->payload(['barangay' => 'Barangay']))->assertSessionHasErrors('barangay');
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_search_access_and_staff_form_access_boundaries(): void
    {
        $this->getJson(route('locations.search', ['q' => 'muzon south']))->assertOk()->assertJsonPath('results.0.name', 'Muzon South');
        $this->actingAs(User::factory()->create(['role' => 'patient']))
            ->getJson(route('locations.search', ['q' => 'muzon']))->assertForbidden();
        $this->get(route('staff.create'))->assertForbidden();
        $this->post(route('staff.store'), $this->payload())->assertForbidden();
        auth()->forgetGuards();
        $this->getJson(route('locations.search', ['q' => 'muzon']))->assertUnauthorized();
    }

    public function test_invalid_replacements_preserve_the_record_and_render_safe_old_input(): void
    {
        $staff = $this->staff();
        $before = $staff->fresh()->getAttributes();
        foreach (StaffAddressFormatter::FIELDS as $field) {
            foreach ([[], '   ', "First\nSecond", str_repeat('x', 256)] as $value) {
                $this->from(route('staff.edit', $staff))
                    ->put(route('staff.update', $staff), $this->payload([$field => $value]))
                    ->assertSessionHasErrors($field);
                $this->get(route('staff.edit', $staff))->assertOk();
                $this->assertSame($before, $staff->fresh()->getAttributes());
            }
        }
        $this->put(route('staff.update', $staff), $this->payload(['replace_address' => 'invalid']))
            ->assertSessionHasErrors('replace_address');
        $this->assertSame($before, $staff->fresh()->getAttributes());
        $this->put(route('staff.update', $staff), Arr::except($this->payload(['replace_address' => '0']), StaffAddressFormatter::FIELDS))
            ->assertSessionHasNoErrors();
        $this->assertSame($before['address'], $staff->fresh()->address);
    }

    private function editFormValues(User $staff): array
    {
        $response = $this->get(route('staff.edit', $staff))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $form = $xpath->query('//form[@id="editStaffForm"]')->item(0);
        $this->assertSame(route('staff.update', $staff), $form->getAttribute('action'));
        $this->assertSame(0, $xpath->query('.//input[@name="password" or @name="password_confirmation"]', $form)->length);
        $response->assertDontSee($staff->password, false);
        $values = [];
        foreach (['first_name', 'middle_name', 'last_name', 'contact_number', 'birthday', 'email', ...StaffAddressFormatter::FIELDS] as $field) {
            $elements = $xpath->query('.//input[@name="'.$field.'"]', $form);
            $this->assertSame(1, $elements->length, $field);
            $values[$field] = $elements->item(0)->getAttribute('value');
        }

        return $values;
    }

    public function test_selected_staff_prefills_every_saved_field_including_blank_middle_name(): void
    {
        $this->staff(); // A different record must never supply the selected staff's values.
        $expected = [
            'first_name' => 'Ana Luisa', 'middle_name' => '', 'last_name' => 'De la Cruz',
            'contact_number' => '+639181234567', 'birthday' => '1988-02-29', 'email' => 'selected@example.com',
            'address_line' => 'Unit 8, Mabini Street', 'barangay' => 'Santo Niño', 'city_municipality' => 'Pasig',
        ];
        $staff = User::factory()->create(Arr::except($expected, StaffAddressFormatter::FIELDS) + [
            'name' => 'Ana Luisa De la Cruz', 'role' => 'staff',
            'address' => StaffAddressFormatter::format($expected),
        ]);
        $staff->update(['middle_name' => null]);
        $before = $staff->fresh()->getAttributes();
        $this->assertSame($expected, $this->editFormValues($staff));
        $this->assertSame($before, $staff->fresh()->getAttributes());
        $staff->update(['middle_name' => 'Reyes']);
        $expected['middle_name'] = 'Reyes';
        $this->assertSame($expected, $this->editFormValues($staff));
    }

    public function test_validation_retry_restores_all_nine_fields_including_intentionally_blank_middle_name(): void
    {
        $staff = $this->staff();
        $before = $staff->fresh()->getAttributes();
        $attempt = [
            'first_name' => 'Ana', 'middle_name' => '', 'last_name' => 'Reyes',
            'contact_number' => 'invalid', 'birthday' => '1992-06-15', 'email' => 'retry@example.com',
            'address_line' => '77 New Road', 'barangay' => 'Poblacion', 'city_municipality' => 'Makati',
        ];
        $this->from(route('staff.edit', $staff))->put(route('staff.update', $staff), $attempt)
            ->assertSessionHasErrors('contact_number');
        $this->assertSame($attempt, $this->editFormValues($staff));
        $this->assertSame($before, $staff->fresh()->getAttributes());
    }

    public function test_submitting_rendered_form_with_only_contact_changed_preserves_all_other_saved_values(): void
    {
        $staff = $this->staff();
        foreach ([StaffAddressFormatter::format($this->payload()), 'Beside the chapel, old district'] as $address) {
            $staff->update(['address' => $address, 'contact_number' => '09171234567']);
            $before = $staff->fresh()->getAttributes();
            $values = $this->editFormValues($staff);
            if (StaffAddressFormatter::parse($address) === null) {
                foreach (StaffAddressFormatter::FIELDS as $field) {
                    $this->assertSame('', $values[$field]);
                }
                // Browser submission omits the disabled legacy replacement inputs.
                $values = Arr::except($values, StaffAddressFormatter::FIELDS);
                $this->get(route('staff.edit', $staff))->assertSee($address);
            }
            $values['contact_number'] = '09181234567';
            $this->put(route('staff.update', $staff), $values)->assertSessionHasNoErrors();
            $after = $staff->fresh()->getAttributes();
            $this->assertSame('09181234567', $after['contact_number']);
            $this->assertSame(Arr::except($before, ['contact_number', 'updated_at']), Arr::except($after, ['contact_number', 'updated_at']));
        }
    }

    public function test_screenshot_legacy_record_prefills_name_without_fabricating_missing_profile_data(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Bihsmila King',
            'first_name' => null, 'middle_name' => null, 'last_name' => null,
            'contact_number' => null, 'birthday' => null, 'address' => null]);
        $before = $staff->fresh()->getAttributes();
        $values = $this->editFormValues($staff);
        $this->assertSame('Bihsmila', $values['first_name']);
        $this->assertSame('King', $values['last_name']);
        foreach (['middle_name', 'contact_number', 'birthday', ...StaffAddressFormatter::FIELDS] as $field) {
            $this->assertSame('', $values[$field], $field);
        }
        $this->assertSame($staff->email, $values['email']);
        $this->get(route('staff.edit', $staff))->assertDontSee('Existing name:')->assertSee('No address recorded.');
        $this->assertSame($before, $staff->fresh()->getAttributes());

        // Failed saves retain typed names, including an intentional blank.
        $values['first_name'] = 'Edited';
        $values['last_name'] = '';
        $this->from(route('staff.edit', $staff))->put(route('staff.update', $staff), $values)
            ->assertSessionHasErrors(['last_name', 'contact_number', 'birthday']);
        $retry = $this->editFormValues($staff);
        $this->assertSame('Edited', $retry['first_name']);
        $this->assertSame('', $retry['last_name']);
        $this->assertSame($before, $staff->fresh()->getAttributes());
    }

    public function test_legacy_name_save_keeps_stored_profile_and_allows_missing_address_to_be_entered(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Bihsmila King',
            'first_name' => null, 'middle_name' => null, 'last_name' => null,
            'contact_number' => '09181234567', 'birthday' => '1985-07-19', 'address' => null]);
        $before = $staff->fresh()->getAttributes();
        $values = $this->editFormValues($staff);
        $this->assertSame('09181234567', $values['contact_number']);
        $this->assertSame('1985-07-19', $values['birthday']);
        $values = array_replace($values, [
            'replace_address' => '1', 'address_line' => '12 Road',
            'barangay' => 'San Jose', 'city_municipality' => 'Santa Maria',
        ]);
        $this->put(route('staff.update', $staff), $values)->assertSessionHasNoErrors();
        $after = $staff->fresh()->getAttributes();
        $this->assertSame('Bihsmila', $after['first_name']);
        $this->assertSame('King', $after['last_name']);
        $this->assertSame("12 Road\nBarangay San Jose\nSanta Maria", $after['address']);
        $excluded = ['first_name', 'middle_name', 'last_name', 'address', 'updated_at'];
        $this->assertSame(Arr::except($before, $excluded), Arr::except($after, $excluded));
    }

    public function test_name_fallback_refuses_ambiguous_names_and_never_overrides_structured_names(): void
    {
        foreach (['Maria De la Cruz', 'Prince', 'Dr King', 'King, Bihsmila', 'B King'] as $name) {
            $staff = User::factory()->create(['role' => 'staff', 'name' => $name]);
            $before = $staff->fresh()->getAttributes();
            $values = $this->editFormValues($staff);
            $this->assertSame('', $values['first_name']);
            $this->assertSame('', $values['last_name']);
            $this->get(route('staff.edit', $staff))->assertSee('Existing name:')->assertSee($name);
            $this->assertSame($before, $staff->fresh()->getAttributes());
        }
        $staff = $this->staff();
        $staff->update(['name' => 'Bihsmila King']);
        $values = $this->editFormValues($staff);
        $this->assertSame('Maria', $values['first_name']);
        $this->assertSame('Santos', $values['middle_name']);
        $this->assertSame('Cruz', $values['last_name']);
    }

    public function test_three_token_first_middle_last_legacy_name_prefills_the_standard_filipino_split(): void
    {
        // Root cause reproduction: pre-structured accounts store only users.name.
        // A three-token "First Middle Last" name previously returned null from
        // StaffNamePrefill, so the name inputs bound NULL columns (blank) and the
        // yellow "Existing name" notice rendered even though the split is the
        // standard, non-ambiguous Filipino reading.
        foreach (['Maria Santos Cruz', 'Ana Reyes Lim', 'Jose Ramos Bautista'] as $name) {
            [$first, $middle, $last] = explode(' ', $name);
            $staff = User::factory()->create(['role' => 'staff', 'name' => $name,
                'first_name' => null, 'middle_name' => null, 'last_name' => null,
                'contact_number' => null, 'birthday' => null, 'address' => null]);
            $before = $staff->fresh()->getAttributes();
            $values = $this->editFormValues($staff);
            $this->assertSame($first, $values['first_name'], $name);
            $this->assertSame($middle, $values['middle_name'], $name);
            $this->assertSame($last, $values['last_name'], $name);
            foreach (['contact_number', 'birthday', ...StaffAddressFormatter::FIELDS] as $field) {
                $this->assertSame('', $values[$field], $name.' '.$field);
            }
            $this->assertSame($staff->email, $values['email'], $name);
            $this->get(route('staff.edit', $staff))->assertDontSee('Existing name:')
                ->assertSee('No address recorded.');
            $this->assertSame($before, $staff->fresh()->getAttributes(), $name);
        }
    }

    public function test_compound_surname_particle_names_remain_unsplit_with_notice_visible(): void
    {
        // A three-token name whose middle token is a compound-surname particle is
        // genuinely ambiguous: the token most likely belongs to the surname (e.g.
        // "Dela Cruz"), so splitting it would guess a surname or middle name. The
        // notice stays and the original full name remains visible for manual entry.
        foreach (['Juan Dela Cruz', 'Ana San Juan', 'Rosa Van Cruz', 'Pedro De Jesus'] as $name) {
            $staff = User::factory()->create(['role' => 'staff', 'name' => $name,
                'first_name' => null, 'middle_name' => null, 'last_name' => null]);
            $before = $staff->fresh()->getAttributes();
            $values = $this->editFormValues($staff);
            $this->assertSame('', $values['first_name'], $name);
            $this->assertSame('', $values['middle_name'], $name);
            $this->assertSame('', $values['last_name'], $name);
            $this->get(route('staff.edit', $staff))->assertSee('Existing name:')->assertSee($name);
            $this->assertSame($before, $staff->fresh()->getAttributes(), $name);
        }
        // Four-token multi-particle names are still refused, not guessed.
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Maria De la Cruz',
            'first_name' => null, 'middle_name' => null, 'last_name' => null]);
        $values = $this->editFormValues($staff);
        $this->assertSame('', $values['first_name']);
        $this->assertSame('', $values['last_name']);
        $this->get(route('staff.edit', $staff))->assertSee('Existing name:');
    }

    public function test_structured_names_always_win_over_three_token_legacy_prefill(): void
    {
        // Structured components take priority; the legacy split never overrides them.
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Maria Santos Cruz',
            'first_name' => 'Mariel', 'middle_name' => null, 'last_name' => 'Cruz']);
        $values = $this->editFormValues($staff);
        $this->assertSame('Mariel', $values['first_name']);
        $this->assertSame('', $values['middle_name']);
        $this->assertSame('Cruz', $values['last_name']);
        $this->get(route('staff.edit', $staff))->assertDontSee('Existing name:');
    }

    public function test_universal_prefill_matrix_and_sequential_account_selection(): void
    {
        $cases = [
            ['Elena Rose Reyes', 'Elena', 'Rose', 'Reyes', ['Elena', 'Rose', 'Reyes'], 'structured'],
            ['Maria Santos', null, null, null, ['Maria', '', 'Santos'], 'legacy'],
            ['Pedro Ramos', null, null, null, ['Pedro', '', 'Ramos'], 'structured'],
            ['Juan Dela Cruz', null, null, null, ['', '', ''], 'legacy'],
            ['Rosa Luna', 'Rosalinda', null, null, ['Rosalinda', '', ''], 'missing'],
            ['Lucia Torres', null, 'Isabel', 'Torres', ['', 'Isabel', 'Torres'], 'structured'],
            ['Nina Lim', 'Nina', null, 'Lim', ['Nina', '', 'Lim'], 'missing'],
        ];
        $records = [];
        foreach ($cases as $i => [$name, $first, $middle, $last, $expectedNames, $addressKind]) {
            $parts = ['address_line' => ($i + 1).' Sample Road', 'barangay' => 'District '.($i + 1), 'city_municipality' => 'City '.($i + 1)];
            $address = match ($addressKind) {
                'structured' => StaffAddressFormatter::format($parts),
                'legacy' => 'Near landmark '.($i + 1).', old district',
                default => null,
            };
            $staff = User::factory()->create([
                'role' => 'staff', 'name' => $name, 'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last,
                'contact_number' => $i === 6 ? null : '0917123456'.$i,
                'birthday' => $i === 6 ? null : '1990-05-1'.($i + 1),
                'email' => 'matrix'.$i.'@example.com', 'address' => $address,
            ]);
            $expected = array_combine(['first_name', 'middle_name', 'last_name'], $expectedNames) + [
                'contact_number' => $staff->contact_number ?? '', 'birthday' => $staff->birthday?->toDateString() ?? '', 'email' => $staff->email,
            ] + ($addressKind === 'structured' ? $parts : array_fill_keys(StaffAddressFormatter::FIELDS, ''));
            $records[] = [$staff, $expected, $staff->fresh()->getAttributes()];
        }
        // Forward and reverse requests in one session exercise distinct IDs and defaults.
        foreach (array_merge($records, array_reverse($records)) as [$staff, $expected, $before]) {
            $this->assertSame($expected, $this->editFormValues($staff));
            $this->assertSame($before, $staff->fresh()->getAttributes());
        }
        foreach ($records as [$staff, $expected, $before]) {
            $payload = $expected;
            if (StaffAddressFormatter::parse($staff->address) === null) {
                $payload = Arr::except($payload, StaffAddressFormatter::FIELDS);
            }
            $payload['email'] = 'changed-'.$staff->email;
            $response = $this->put(route('staff.update', $staff), $payload);
            if ($expected['first_name'] === '' || $expected['last_name'] === '' || $expected['contact_number'] === '') {
                $response->assertSessionHasErrors();
                $this->assertSame($before, $staff->fresh()->getAttributes());
            } else {
                $response->assertSessionHasNoErrors();
                $after = $staff->fresh()->getAttributes();
                $this->assertSame($payload['email'], $after['email']);
                // Explicit saves may persist the displayed legacy name components.
                $excluded = ['email', 'first_name', 'middle_name', 'last_name', 'updated_at'];
                $this->assertSame(Arr::except($before, $excluded), Arr::except($after, $excluded));
            }
        }
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
