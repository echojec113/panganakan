<?php

/**
 * Optional supporting image for the "Record Referral Refusal" workflow.
 *
 * The test environment has NO GD extension, so UploadedFile::fake()->image()
 * is unavailable. Real image bytes are embedded instead and the mime rule
 * sniffs them with fileinfo (same code path as production).
 */

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralFollowThroughService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function refusalImageUser(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function refusalImagePatient(string $firstName = 'Mila', string $status = 'ONGOING'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Santos',
        'age' => 26,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => strtolower($firstName) . '-' . uniqid() . '@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => $status,
    ]);
}

function refusalImageReferral(Patient $patient, User $user, array $overrides = []): Referral
{
    return Referral::create(array_merge([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'doctor_name' => 'Dr. Cruz',
        'reason' => 'Needs specialist care',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ], $overrides));
}

function refusalImageBytes(string $type): string
{
    return match ($type) {
        'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        'jpg' => base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q=='),
        'webp' => base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA'),
        'gif' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
        default => throw new InvalidArgumentException('Unknown fixture type: ' . $type),
    };
}

function refusalImageUpload(string $type = 'jpg', ?string $clientName = null, ?int $forceSize = null): UploadedFile
{
    $clientName ??= 'proof.' . $type;
    $bytes = refusalImageBytes($type);

    if ($forceSize !== null && $forceSize > strlen($bytes)) {
        $bytes .= str_repeat(chr(0), $forceSize - strlen($bytes));
    }

    $path = tempnam(sys_get_temp_dir(), 'refusal_img');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $clientName, null, null, true);
}

function refusalImagePayload(array $overrides = []): array
{
    return array_merge([
        'refusal_notes' => 'Patient declined the referral due to transport difficulty.',
        'waiver_signed' => 1,
    ], $overrides);
}

beforeEach(function () {
    Storage::fake('public');
});

it('stores a server-generated path when a valid JPEG accompanies the refusal', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('jpg'),
    ]))->assertSessionHas('success', 'Referral refusal recorded.');

    $referral->refresh();

    expect($referral->status)->toBe('Refused');
    expect($referral->refusal_image_path)->toMatch(
        '#^referrals/refusal_' . $referral->id . '_\d+_[A-Za-z0-9]{8}\.jpg$#'
    );
    expect(Storage::disk('public')->exists($referral->refusal_image_path))->toBeTrue();
    expect($referral->refusal_notes)->toBe('Patient declined the referral due to transport difficulty.');
    expect($referral->waiver_signed)->toBeTrue();
    expect($referral->refusal_recorded_by)->toBe($user->id);
    expect($referral->completed_at)->toBeNull();

    $descriptions = AuditLog::where('module', 'REFERRAL')->pluck('description')->implode(' | ');
    expect($descriptions)->toContain('Recorded refusal for referral #' . $referral->id);
    expect($descriptions)->toContain('supporting image attached');
});

it('stores PNG and WebP uploads with matching content-derived extensions', function () {
    $user = refusalImageUser();

    foreach (['png', 'webp'] as $type) {
        $referral = refusalImageReferral(refusalImagePatient('Case' . ucfirst($type)), $user);

        $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
            'refusal_image' => refusalImageUpload($type),
        ]))->assertSessionHas('success', 'Referral refusal recorded.');

        $referral->refresh();

        expect($referral->refusal_image_path)->toMatch(
            '#^referrals/refusal_' . $referral->id . '_\d+_[A-Za-z0-9]{8}\.' . $type . '$#'
        );
        expect(Storage::disk('public')->exists($referral->refusal_image_path))->toBeTrue();
    }
});

it('records a refusal without an image leaving the path null and no files', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload())
        ->assertSessionHas('success', 'Referral refusal recorded.');

    $referral->refresh();

    expect($referral->status)->toBe('Refused');
    expect($referral->refusal_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('referrals'))->toBeEmpty();
});

it('rejects unsupported image types with the exact validation message', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('gif'),
    ]))->assertSessionHasErrors([
        'refusal_image' => 'Please upload a JPG, JPEG, PNG, or WebP image.',
    ]);

    $referral->refresh();

    expect($referral->status)->toBe('Pending');
    expect($referral->refusal_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('referrals'))->toBeEmpty();
});

it('rejects images above 5MB with the exact validation message', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png', 'huge.png', 5 * 1024 * 1024 + 2048),
    ]))->assertSessionHasErrors([
        'refusal_image' => 'The supporting image must not exceed 5MB.',
    ]);

    $referral->refresh();

    expect($referral->status)->toBe('Pending');
    expect($referral->refusal_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('referrals'))->toBeEmpty();
});

it('never uses the client-supplied original filename for storage', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png', '../../../evil-name.png'),
    ]))->assertSessionHas('success', 'Referral refusal recorded.');

    $path = $referral->refresh()->refusal_image_path;

    expect($path)->toMatch('#^referrals/refusal_' . $referral->id . '_\d+_[A-Za-z0-9]{8}\.png$#');
    expect($path)->not->toContain('evil');
    expect($path)->not->toContain('..');
});

it('keeps refusal notes and waiver validation unchanged when an image is supplied', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_notes' => 'short',
        'refusal_image' => refusalImageUpload('png'),
    ]))->assertSessionHasErrors(['refusal_notes']);

    expect($referral->refresh()->status)->toBe('Pending');
    expect(Storage::disk('public')->allFiles('referrals'))->toBeEmpty();

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), [
        'refusal_notes' => 'Patient declined the referral after counselling.',
        'refusal_image' => refusalImageUpload('png'),
    ])->assertSessionHas('success', 'Referral refusal recorded.');

    $referral->refresh();

    expect($referral->status)->toBe('Refused');
    expect($referral->waiver_signed)->toBeFalse();
    expect($referral->refusal_image_path)->not->toBeNull();
});

it('does not let a later referral inherit a previous refusal image', function () {
    $user = refusalImageUser();
    $patient = refusalImagePatient();

    $first = refusalImageReferral($patient, $user);
    $this->actingAs($user)->post(route('referrals.refuse', $first->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]));
    $first->refresh();

    $second = refusalImageReferral($patient, $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $user->id,
        'refusal_notes' => 'Second refusal without any attached evidence.',
    ]);

    expect($first->refusal_image_path)->not->toBeNull();
    expect($second->refusal_image_path)->toBeNull();
});

it('streams the exact stored file to authenticated users', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]));
    $referral->refresh();

    $this->actingAs($user)
        ->get(route('referrals.refusal-image', $referral))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    expect(Storage::disk('public')->get($referral->refusal_image_path))->toBe(refusalImageBytes('png'));
});

it('requires authentication for the refusal image route', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->get(route('referrals.refusal-image', $referral))->assertRedirect(route('login'));
});

it('returns 404 when the referral has no stored image', function () {
    $user = refusalImageUser();
    $refused = refusalImageReferral(refusalImagePatient('NoImage'), $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $user->id,
        'refusal_notes' => 'Refused without any attached evidence.',
    ]);
    $pending = refusalImageReferral(refusalImagePatient('StillOpen'), $user);

    $this->actingAs($user)->get(route('referrals.refusal-image', $refused))->assertNotFound();
    $this->actingAs($user)->get(route('referrals.refusal-image', $pending))->assertNotFound();
});

it('returns 404 when the stored file is missing from disk', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $user->id,
        'refusal_notes' => 'Row references a file that no longer exists.',
        'refusal_image_path' => 'referrals/refusal_ghost_0_00000000.png',
    ]);

    $this->actingAs($user)->get(route('referrals.refusal-image', $referral))->assertNotFound();
});

it('hides the image while archived and restores access after restore', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);
    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]));
    $referral->refresh();
    $path = $referral->refusal_image_path;

    $this->actingAs($user)->delete(route('referrals.destroy', $referral->id))
        ->assertRedirect(route('referrals.index'));

    expect($referral->refresh()->trashed())->toBeTrue();
    expect(Storage::disk('public')->exists($path))->toBeTrue();
    $this->actingAs($user)->get(route('referrals.refusal-image', $referral->id))->assertNotFound();

    $this->actingAs($user)->post(route('referrals.restore', $referral->id))
        ->assertRedirect();

    expect($referral->refresh()->trashed())->toBeFalse();
    $this->actingAs($user)->get(route('referrals.refusal-image', $referral))->assertOk();
    expect(Storage::disk('public')->exists($path))->toBeTrue();
});

it('deletes the newly stored image when the refusal transition fails (no orphan)', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user, ['status' => 'Completed']);

    // An unrelated, pre-existing evidence file must survive the cleanup.
    Storage::disk('public')->put('referrals/other-referral-evidence.png', refusalImageBytes('png'));

    $logsBefore = AuditLog::where('module', 'REFERRAL')->count();

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]))->assertSessionHas('error', 'Referral is already Completed and cannot transition to Refused.');

    $referral->refresh();

    expect($referral->status)->toBe('Completed');
    expect($referral->refusal_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('referrals'))->toBe(['referrals/other-referral-evidence.png']);
    expect(AuditLog::where('module', 'REFERRAL')->count())->toBe($logsBefore);
});

it('keeps the uploaded image when the refusal succeeds', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]))->assertSessionHas('success', 'Referral refusal recorded.');

    $referral->refresh();

    expect($referral->refusal_image_path)->not->toBeNull();
    expect(Storage::disk('public')->exists($referral->refusal_image_path))->toBeTrue();
    expect(Storage::disk('public')->allFiles('referrals'))->toHaveCount(1);
});

it('deletes only the newly stored image when an unexpected Throwable fails the refusal after storage', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    // Unrelated pre-existing evidence must never be touched by cleanup.
    Storage::disk('public')->put('referrals/other-referral-evidence.png', refusalImageBytes('png'));

    // Simulate a database failure happening AFTER the image was stored
    // (e.g. QueryException while persisting refusal_image_path).
    $this->mock(ReferralFollowThroughService::class)
        ->shouldReceive('refuse')
        ->once()
        ->andThrow(new QueryException(
            'sqlite',
            'insert into "referrals"',
            [],
            new PDOException('SQLSTATE[42S21]: Column already exists')
        ));

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->post(
        route('referrals.refuse', $referral->id),
        refusalImagePayload(['refusal_image' => refusalImageUpload('png')])
    ))->toThrow(QueryException::class);

    // The file newly stored by THIS request is gone; the unrelated file
    // remains and the referral row was never modified.
    expect(Storage::disk('public')->allFiles('referrals'))->toBe(['referrals/other-referral-evidence.png']);

    $referral->refresh();
    expect($referral->status)->toBe('Pending');
    expect($referral->refusal_image_path)->toBeNull();
});

it('does not store the image when the delivered-patient guard blocks the refusal', function () {
    $user = refusalImageUser();
    $patient = refusalImagePatient('Delia', 'DELIVERED');
    $referral = refusalImageReferral($patient, $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]))->assertSessionHas('error', 'Delivered patients are read-only; referral status cannot be changed.');

    expect($referral->refresh()->status)->toBe('Pending');
    expect($referral->refusal_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('referrals'))->toBeEmpty();
});

it('registers the refusal image route inside the authenticated no-store group', function () {
    $route = app('router')->getRoutes()->getByName('referrals.refusal-image');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('referrals/{referral}/refusal-image');
    expect($route->methods())->toContain('GET');
    expect($route->gatherMiddleware())->toContain('web');
    expect($route->gatherMiddleware())->toContain('auth');
    expect($route->gatherMiddleware())->toContain('no-store');
});

it('surfaces image validation errors in the detail page summary', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)
        ->from(route('referrals.show', $referral))
        ->post(route('referrals.refuse', $referral->id), refusalImagePayload([
            'refusal_image' => refusalImageUpload('gif'),
        ]))
        ->assertRedirect(route('referrals.show', $referral));

    $this->actingAs($user)
        ->get(route('referrals.show', $referral))
        ->assertSee('Please upload a JPG, JPEG, PNG, or WebP image.');
});

it('shows the supporting image link on the refused detail page only when a path exists', function () {
    $user = refusalImageUser();

    $withImage = refusalImageReferral(refusalImagePatient('WithImg'), $user);
    $this->actingAs($user)->post(route('referrals.refuse', $withImage->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]));

    $withoutImage = refusalImageReferral(refusalImagePatient('NoImg'), $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $user->id,
        'refusal_notes' => 'Refused without any attached evidence.',
    ]);

    $this->actingAs($user)->get(route('referrals.show', $withImage))
        ->assertOk()
        ->assertSee('View Supporting Image')
        ->assertSee(url('referrals/' . $withImage->id . '/refusal-image'), false);

    $this->actingAs($user)->get(route('referrals.show', $withoutImage))
        ->assertOk()
        ->assertDontSee('View Supporting Image')
        ->assertDontSee('id="refusal-image"', false);
});

it('renders the optional file input inside the pending refusal modal', function () {
    $user = refusalImageUser();
    $referral = refusalImageReferral(refusalImagePatient(), $user);

    $this->actingAs($user)->get(route('referrals.show', $referral))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('id="refusal-image"', false)
        ->assertSee('name="refusal_image"', false)
        ->assertSee('accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"', false)
        ->assertSee('JPG, JPEG, PNG, or WebP. Maximum 5MB.', false)
        ->assertSee('The supporting image must not exceed 5MB.', false);
});

it('links the exact refusal evidence row in the patient history list', function () {
    $user = refusalImageUser();
    $patient = refusalImagePatient();

    $withImage = refusalImageReferral($patient, $user);
    $this->actingAs($user)->post(route('referrals.refuse', $withImage->id), refusalImagePayload([
        'refusal_image' => refusalImageUpload('png'),
    ]));

    $laterNoImage = refusalImageReferral($patient, $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $user->id,
        'refusal_notes' => 'Later refusal without evidence must not link anywhere.',
    ]);

    $response = $this->actingAs($user)->get(route('referrals.index'))->assertOk();

    $response->assertSee(url('referrals/' . $withImage->id . '/refusal-image'), false);

    // The history partial renders twice (desktop table + mobile card), so
    // only the presence/absence of the exact referral ID is asserted here.
    $page = $response->getContent();
    expect($page)->toContain(url('referrals/' . $withImage->id . '/refusal-image'));
    expect($page)->not->toContain(url('referrals/' . $laterNoImage->id . '/refusal-image'));
});
