<?php

namespace App\Services;

use App\Models\Baby;
use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\User;
use App\Support\PregnancyOutcomeVocabulary;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 17C — authoritative owner of the confirmed-delivery write transaction.
 *
 * The existing PatientController::markDelivered() workflow delegates ALL
 * multi-model writes to this service. The controller never updates the
 * patient and then separately creates the outcome: the whole transition
 * (patient lifecycle, outcome record + provenance, baby rows, para) commits
 * in ONE database transaction or not at all.
 *
 * Ownership boundary (server-controlled, never client-supplied):
 *   - outcome_type, confirmed_at, confirmed_by, patient.status, para.
 *   - Browser input is restricted to delivery_location / confirmation_source
 *     / outcome note / babies. Those are re-validated against the approved
 *     PregnancyOutcomeVocabulary here even when the controller already
 *     validated them (defence in depth).
 *
 * Safety invariants:
 *   - patient.status must be exactly ONGOING (DELIVERED and legacy REFERRED
 *     are rejected, never silently rewritten).
 *   - exactly one PregnancyOutcome row per patient (patient_id UNIQUE).
 *     An already-confirmed outcome rejects the operation instead of
 *     overwriting its provenance. A blank placeholder row is updated in place.
 *   - a confirmed delivery is FINAL: follow-up fields are cleared and the
 *     derived RESOLVED value is never persisted.
 *   - para is incremented exactly once per delivery episode.
 */
class PregnancyOutcomeRecordingService
{
    private const STATUS_ONGOING = 'ONGOING';
    private const STATUS_DELIVERED = 'DELIVERED';
    private const STATUS_REFERRED = 'REFERRED';

    public function recordConfirmedDelivery(
        Patient $patient,
        User $actor,
        string $deliveryDate,
        string $deliveryLocation,
        string $confirmationSource,
        array $babies,
        ?string $notes = null
    ): PregnancyOutcome {
        $babies = array_values($babies);

        // Defense in depth at the authoritative service boundary: the core
        // invariants are re-validated here even when a direct caller bypasses
        // controller validation. A future/malformed/inconsistent delivery date
        // or baby date of birth can never be written through the service.
        $this->assertValidVocabulary($deliveryLocation, $confirmationSource);
        $this->assertBabyContract($babies);
        $this->assertDateIntegrity($deliveryDate, $babies);

        return DB::transaction(function () use (
            $patient,
            $actor,
            $deliveryDate,
            $deliveryLocation,
            $confirmationSource,
            $babies,
            $notes
        ) {
            // Reactation under the transaction's lock, not on the stale
            // controller-loaded model (TOCTOU protection).
            $locked = Patient::query()
                ->whereKey($patient->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->trashed()) {
                throw new DomainException('This patient record is not found.');
            }

            $this->assertEligibleForClosure($locked);

            $outcome = PregnancyOutcome::query()
                ->where('patient_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($outcome && $outcome->hasConfirmedOutcome()) {
                throw new DomainException('This patient already has a confirmed delivery outcome recorded.');
            }

            $trimmer = fn (?string $value): ?string => $value === null ? null : (trim($value) === '' ? null : trim($value));

            // Reuse the single UNIQUE outcome row when a blank/unconfirmed
            // placeholder already exists; never attempt a second insert.
            $outcomeData = [
                'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
                'delivery_location' => $deliveryLocation,
                'follow_up_status' => null,
                'follow_up_recorded_at' => null,
                'follow_up_recorded_by' => null,
                'confirmation_source' => $confirmationSource,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
                'notes' => $trimmer($notes),
            ];

            if ($outcome) {
                $outcome->update($outcomeData);
            } else {
                $outcome = PregnancyOutcome::create(array_merge(['patient_id' => $locked->id], $outcomeData));
            }

            foreach ($babies as $baby) {
                Baby::create([
                    'patient_id' => $locked->id,
                    'first_name' => $baby['first_name'] ?? null,
                    'middle_name' => $baby['middle_name'] ?? null,
                    'last_name' => $baby['last_name'] ?? null,
                    'sex' => $baby['sex'] ?? null,
                    'date_of_birth' => $baby['date_of_birth'],
                    'time_of_birth' => $baby['time_of_birth'],
                    'birth_weight' => $baby['birth_weight'] ?? null,
                    'birth_length' => $baby['birth_length'] ?? null,
                ]);
            }

            $locked->update([
                'status' => self::STATUS_DELIVERED,
                'delivery_date' => $deliveryDate,
                'para' => ($locked->para ?? 0) + 1,
            ]);

            return $outcome->fresh();
        });
    }

    private function assertValidVocabulary(string $deliveryLocation, string $confirmationSource): void
    {
        if (! PregnancyOutcomeVocabulary::isValidDeliveryLocation($deliveryLocation)) {
            throw new DomainException('Invalid delivery location.');
        }

        if (! PregnancyOutcomeVocabulary::isValidConfirmationSource($confirmationSource)) {
            throw new DomainException('Invalid confirmation source.');
        }
    }

    private function assertEligibleForClosure(Patient $patient): void
    {
        if ($patient->status === self::STATUS_DELIVERED) {
            throw new DomainException('This patient is already marked as delivered.');
        }

        if ($patient->status === self::STATUS_REFERRED) {
            throw new DomainException('Legacy referred patient records cannot be closed by the delivery workflow.');
        }

        if ($patient->status !== self::STATUS_ONGOING) {
            throw new DomainException('This patient is not eligible for delivery recording.');
        }
    }

    private function assertBabyContract(array $babies): void
    {
        if (count($babies) < 1) {
            throw new DomainException('At least one baby record is required.');
        }

        foreach ($babies as $baby) {
            if (empty($baby['date_of_birth']) || empty($baby['time_of_birth'])) {
                throw new DomainException('Each baby must have a date and time of birth.');
            }
        }
    }

    /**
     * Independent date-integrity guard at the authoritative boundary.
     *
     * Dates are always compared as calendar dates (Y-m-d) through
     * Carbon, never as raw strings, so formatting variance cannot slip a
     * mismatch past the service. The delivery date is never inferred from the
     * baby and the baby date is never inferred from the delivery date — both
     * must be supplied explicitly and agree.
     */
    private function assertDateIntegrity(string $deliveryDate, array $babies): void
    {
        try {
            $delivery = Carbon::parse($deliveryDate);
        } catch (\Throwable $e) {
            throw new DomainException('Invalid delivery date.');
        }

        if ($delivery->isFuture()) {
            throw new DomainException('Delivery date cannot be in the future.');
        }

        foreach ($babies as $baby) {
            try {
                $dob = Carbon::parse($baby['date_of_birth']);
            } catch (\Throwable $e) {
                throw new DomainException('Each baby must have a valid date of birth.');
            }

            if ($dob->isFuture()) {
                throw new DomainException('Baby date of birth cannot be in the future.');
            }

            if (! $dob->isSameDay($delivery)) {
                throw new DomainException('Baby date of birth must match the delivery date.');
            }
        }
    }
}