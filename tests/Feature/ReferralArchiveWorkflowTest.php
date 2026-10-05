<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;

function referralArchiveStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function referralArchivePatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Cassia',
        'last_name' => 'Delacruz',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function referralArchiveReferral(Patient $patient, User $creator, array $overrides = []): Referral
{
    return Referral::create(array_merge([
        'patient_id' => $patient->id,
        'created_by' => $creator->id,
        'referred_to' => 'City Hospital',
        'reason' => 'High-risk referral for evaluation',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ], $overrides));
}

it('archives a referral with soft delete and exposes it only on the archived page', function () {
    $user = referralArchiveStaff();
    $patient = referralArchivePatient();
    $referral = referralArchiveReferral($patient, $user);

    $this->actingAs($user)
        ->get(route('referrals.index'))
        ->assertOk()
        ->assertViewHas('total', 1)
        ->assertSee(route('referrals.archived'), false)
        ->assertSee(route('referrals.destroy', $referral->id), false)
        ->assertSee('title="Archive"', false)
        ->assertSee('Archive Referral?')
        ->assertSee('moved to Archived Referrals and can be restored later')
        ->assertSee('View Referral');

    $this->actingAs($user)
        ->delete(route('referrals.destroy', $referral->id))
        ->assertRedirect(route('referrals.index'))
        ->assertSessionHas('success', 'Referral archived successfully.');

    $this->assertSoftDeleted('referrals', ['id' => $referral->id]);
    $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'patient_id' => $patient->id]);

    $this->actingAs($user)
        ->get(route('referrals.index'))
        ->assertOk()
        ->assertViewHas('total', 0)
        ->assertDontSeeText('Cassia Delacruz')
        ->assertDontSeeText('High-risk referral for evaluation');

    $archivedAt = $referral->refresh()->deleted_at->format('M d, Y h:i A');

    $this->actingAs($user)
        ->get(route('referrals.archived'))
        ->assertOk()
        ->assertSeeText('Archived Referrals')
        ->assertSeeText('Cassia Delacruz')
        ->assertSeeText('City Hospital')
        ->assertSeeText('High-risk referral for evaluation')
        ->assertSeeText('Pending')
        ->assertSeeText($archivedAt)
        ->assertSee(route('referrals.restore', $referral->id), false)
        ->assertSeeText('Restore Referral?')
        ->assertDontSee('View Referral');
});

it('restores an archived referral back to the active list', function () {
    $user = referralArchiveStaff();
    $patient = referralArchivePatient(['first_name' => 'Restora', 'last_name' => 'Santos']);
    $referral = referralArchiveReferral($patient, $user);

    $this->actingAs($user)->delete(route('referrals.destroy', $referral->id));
    $this->assertSoftDeleted('referrals', ['id' => $referral->id]);

    $this->actingAs($user)
        ->post(route('referrals.restore', $referral->id))
        ->assertRedirect(route('referrals.index'))
        ->assertSessionHas('success', 'Referral restored successfully.');

    $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'deleted_at' => null]);

    $this->actingAs($user)
        ->get(route('referrals.index'))
        ->assertOk()
        ->assertViewHas('total', 1)
        ->assertSeeText('Restora Santos');

    $this->actingAs($user)
        ->get(route('referrals.archived'))
        ->assertOk()
        ->assertDontSeeText('Restora Santos');
});

it('archives only the selected referral and leaves other referrals untouched', function () {
    $user = referralArchiveStaff();
    $patient = referralArchivePatient();
    $keep = referralArchiveReferral($patient, $user, ['reason' => 'Referral kept active']);
    $archive = referralArchiveReferral($patient, $user, ['reason' => 'Referral to be archived']);

    $this->actingAs($user)->delete(route('referrals.destroy', $archive->id));

    $this->actingAs($user)
        ->get(route('referrals.index'))
        ->assertOk()
        ->assertViewHas('total', 1)
        ->assertSeeText('Referral kept active')
        ->assertDontSeeText('Referral to be archived');

    $this->assertDatabaseHas('referrals', ['id' => $keep->id, 'deleted_at' => null]);
    $this->assertSoftDeleted('referrals', ['id' => $archive->id]);
});

it('keeps View Referral working for active referrals and blocks archived show until restored', function () {
    $user = referralArchiveStaff();
    $patient = referralArchivePatient(['first_name' => 'Visible', 'last_name' => 'Reyes']);
    $referral = referralArchiveReferral($patient, $user);

    $this->actingAs($user)->get(route('referrals.show', $referral->id))->assertOk();

    $this->actingAs($user)->delete(route('referrals.destroy', $referral->id));

    $this->actingAs($user)->get(route('referrals.show', $referral->id))->assertNotFound();

    $this->actingAs($user)->post(route('referrals.restore', $referral->id));

    $this->actingAs($user)->get(route('referrals.show', $referral->id))->assertOk();
});

it('restricts archive, archived page and restore to staff and keeps admin index unchanged', function () {
    $staff = referralArchiveStaff();
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = referralArchivePatient();
    $referral = referralArchiveReferral($patient, $staff);
    referralArchiveReferral($patient, $staff, ['reason' => 'Active referral stays']);
    $referral->delete();

    $this->get(route('referrals.archived'))->assertRedirect(route('login'));
    $this->delete(route('referrals.destroy', $referral->id))->assertRedirect(route('login'));
    $this->post(route('referrals.restore', $referral->id))->assertRedirect(route('login'));

    $this->actingAs($admin)->get(route('referrals.archived'))->assertForbidden();
    $this->actingAs($admin)->post(route('referrals.restore', $referral->id))->assertForbidden();
    $this->actingAs($admin)->delete(route('referrals.destroy', $referral->id))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('referrals.index'))
        ->assertOk()
        ->assertDontSee(route('referrals.archived'), false)
        ->assertDontSee('title="Archive"', false)
        ->assertSee('View Referral')
        ->assertDontSeeText('High-risk referral for evaluation')
        ->assertSeeText('Active referral stays');
});

it('records an ARCHIVE REFERRAL audit entry when a referral is archived', function () {
    $user = referralArchiveStaff();
    $patient = referralArchivePatient();
    $referral = referralArchiveReferral($patient, $user);

    $this->actingAs($user)->delete(route('referrals.destroy', $referral->id));

    $audit = AuditLog::where('action', 'ARCHIVE')->where('module', 'REFERRAL')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($user->id)
        ->and($audit->description)->toContain('referral #' . $referral->id)
        ->and($audit->description)->toContain('patient ID: ' . $patient->id);
});
