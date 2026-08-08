<?php

use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration rollback safety: the Sprint 16 down() must refuse to narrow the
 * status enum while any referral still uses 'Refused', and must leave the
 * Phase 16B schema and the Refused rows untouched when it aborts.
 */
function rollbackSafetyPatient(string $firstName = 'Lorna'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Test',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function rollbackSafetyReferral(string $status, array $overrides = []): Referral
{
    return Referral::create(array_merge([
        'patient_id' => rollbackSafetyPatient()->id,
        'created_by' => User::factory()->create()->id,
        'referred_to' => 'Hospital',
        'reason' => 'Rollback safety fixture',
        'referral_date' => now()->toDateString(),
        'status' => $status,
    ], $overrides));
}

/**
 * Require the migration file fresh and instantiate its anonymous class so the
 * real down()/up() implementations are exercised directly against the isolated
 * test database.
 */
function referralIntegrationMigration(): object
{
    return require database_path('migrations/2026_08_09_000001_add_referral_integration_to_referrals_table.php');
}

it('rejects rollback when a Refused referral exists and preserves the row and columns', function () {
    rollbackSafetyReferral('Refused', ['refusal_notes' => 'Patient declined']);

    $migration = referralIntegrationMigration();

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    // The Refused row must remain untouched.
    $refused = DB::table('referrals')->where('status', 'Refused')->first();
    expect($refused)->not->toBeNull();
    expect($refused->refusal_notes)->toBe('Patient declined');

    // No Phase 16B column may have been removed.
    expect(Schema::hasColumn('referrals', 'prenatal_visit_id'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'assessment_snapshot'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'refusal_recorded_at'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'refusal_recorded_by'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'refusal_notes'))->toBeTrue();
});

it('still supports the Refused status value after a rejected rollback', function () {
    rollbackSafetyReferral('Refused');

    $migration = referralIntegrationMigration();

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Referral::where('status', 'Refused')->count())->toBe(1);
    expect(Referral::where('status', 'Refused')->first()->status)->toBe('Refused');
});

it('does not convert or delete any rows when rollback is rejected', function () {
    rollbackSafetyReferral('Pending');
    rollbackSafetyReferral('Completed');
    rollbackSafetyReferral('Refused');

    $migration = referralIntegrationMigration();

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(DB::table('referrals')->count())->toBe(3);
    expect(DB::table('referrals')->where('status', 'Pending')->count())->toBe(1);
    expect(DB::table('referrals')->where('status', 'Completed')->count())->toBe(1);
    expect(DB::table('referrals')->where('status', 'Refused')->count())->toBe(1);
});

it('allows normal rollback when no Refused referrals exist', function () {
    rollbackSafetyReferral('Pending');
    rollbackSafetyReferral('Completed');
    rollbackSafetyReferral('Cancelled');

    $migration = referralIntegrationMigration();

    $migration->down();

    // Phase 16B columns were removed.
    expect(Schema::hasColumn('referrals', 'prenatal_visit_id'))->toBeFalse();
    expect(Schema::hasColumn('referrals', 'assessment_snapshot'))->toBeFalse();
    expect(Schema::hasColumn('referrals', 'refusal_recorded_by'))->toBeFalse();

    // The original three statuses remain valid.
    expect(Referral::where('status', 'Pending')->count())->toBe(1);
    expect(Referral::where('status', 'Completed')->count())->toBe(1);
    expect(Referral::where('status', 'Cancelled')->count())->toBe(1);

    // Restore the Phase 16B schema for the rest of the test session.
    $migration->up();
    expect(Schema::hasColumn('referrals', 'prenatal_visit_id'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'assessment_snapshot'))->toBeTrue();
});