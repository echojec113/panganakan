<?php

namespace App\Support;

/**
 * Sprint 17B — single source of truth for the pregnancy-outcome vocabulary.
 *
 * Persisted values are observed facts only:
 *   - outcome_type: DELIVERED is the only confirmed final outcome in 17B.
 *     Clinically sensitive categories (miscarriage, stillbirth, abortion,
 *     maternal/neonatal death) are explicitly deferred and stay out of the
 *     list until a data/workflow contract exists and human approval is given.
 *   - delivery_location / follow_up_status / confirmation_source: see below.
 *
 * Explicitly NOT persisted:
 *   - outcome_confirmed (boolean) — confirmation is outcome_type != null
 *     ALONGSIDE confirmed_at / confirmed_by / confirmation_source.
 *   - follow-up CONFIRMATION_REQUIRED — derived in 17D from passed EDD +
 *     missing confirmed outcome + follow-up recency. Never stored.
 *   - follow-up RESOLVED — derived from outcome_type != null. Never stored.
 *   - delivery_date — patients.delivery_date is the single canonical date.
 *
 * A NULL value in any vocabulary column always means "no recorded evidence
 * yet" — never an inferred or invented outcome.
 */
final class PregnancyOutcomeVocabulary
{
    public const OUTCOME_TYPE_DELIVERED = 'DELIVERED';

    public const OUTCOME_TYPES = [
        self::OUTCOME_TYPE_DELIVERED,
    ];

    public const DELIVERY_LOCATION_THIS_CLINIC = 'THIS_CLINIC';
    public const DELIVERY_LOCATION_ANOTHER_FACILITY = 'ANOTHER_FACILITY';
    public const DELIVERY_LOCATION_HOME = 'HOME';
    public const DELIVERY_LOCATION_OTHER = 'OTHER';

    public const DELIVERY_LOCATIONS = [
        self::DELIVERY_LOCATION_THIS_CLINIC,
        self::DELIVERY_LOCATION_ANOTHER_FACILITY,
        self::DELIVERY_LOCATION_HOME,
        self::DELIVERY_LOCATION_OTHER,
    ];

    /** Persisted follow-up observations (initial scope). */
    public const FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED = 'STILL_PREGNANT_CONFIRMED';
    public const FOLLOW_UP_STATUS_UNABLE_TO_CONTACT = 'UNABLE_TO_CONTACT';

    public const FOLLOW_UP_STATUSES = [
        self::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        self::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT,
    ];

    /** Derived follow-up states — documented so they are never persisted. */
    public const FOLLOW_UP_DERIVED_CONFIRMATION_REQUIRED = 'CONFIRMATION_REQUIRED';
    public const FOLLOW_UP_DERIVED_RESOLVED = 'RESOLVED';

    public const CONFIRMATION_SOURCE_CLINIC_RECORD = 'CLINIC_RECORD';
    public const CONFIRMATION_SOURCE_PATIENT_REPORT = 'PATIENT_REPORT';
    public const CONFIRMATION_SOURCE_OTHER_FACILITY_REPORT = 'OTHER_FACILITY_REPORT';
    public const CONFIRMATION_SOURCE_OTHER = 'OTHER';

    public const CONFIRMATION_SOURCES = [
        self::CONFIRMATION_SOURCE_CLINIC_RECORD,
        self::CONFIRMATION_SOURCE_PATIENT_REPORT,
        self::CONFIRMATION_SOURCE_OTHER_FACILITY_REPORT,
        self::CONFIRMATION_SOURCE_OTHER,
    ];

    /** Single source of truth for friendly display labels. */
    public const DELIVERY_LOCATION_LABELS = [
        self::DELIVERY_LOCATION_THIS_CLINIC => 'This Clinic',
        self::DELIVERY_LOCATION_ANOTHER_FACILITY => 'Another Facility',
        self::DELIVERY_LOCATION_HOME => 'Home',
        self::DELIVERY_LOCATION_OTHER => 'Other',
    ];

    public const CONFIRMATION_SOURCE_LABELS = [
        self::CONFIRMATION_SOURCE_CLINIC_RECORD => 'Clinic Record',
        self::CONFIRMATION_SOURCE_PATIENT_REPORT => 'Patient Report',
        self::CONFIRMATION_SOURCE_OTHER_FACILITY_REPORT => 'Other Facility Report',
        self::CONFIRMATION_SOURCE_OTHER => 'Other',
    ];

    public const FOLLOW_UP_STATUS_LABELS = [
        self::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED => 'Still Pregnant — Confirmed',
        self::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT => 'Unable to Contact',
    ];

    public static function isValidOutcomeType(?string $value): bool
    {
        return $value === null || in_array($value, self::OUTCOME_TYPES, true);
    }

    public static function isValidDeliveryLocation(?string $value): bool
    {
        return $value === null || in_array($value, self::DELIVERY_LOCATIONS, true);
    }

    public static function isValidFollowUpStatus(?string $value): bool
    {
        return $value === null || in_array($value, self::FOLLOW_UP_STATUSES, true);
    }

    public static function isDerivedFollowUpStatus(?string $value): bool
    {
        return in_array($value, [
            self::FOLLOW_UP_DERIVED_CONFIRMATION_REQUIRED,
            self::FOLLOW_UP_DERIVED_RESOLVED,
        ], true);
    }

    public static function isValidConfirmationSource(?string $value): bool
    {
        return $value === null || in_array($value, self::CONFIRMATION_SOURCES, true);
    }

    public static function deliveryLocationLabel(?string $value): string
    {
        return self::DELIVERY_LOCATION_LABELS[$value] ?? (string) ($value ?? '—');
    }

    public static function confirmationSourceLabel(?string $value): string
    {
        return self::CONFIRMATION_SOURCE_LABELS[$value] ?? (string) ($value ?? '—');
    }

    public static function followUpStatusLabel(?string $value): string
    {
        return self::FOLLOW_UP_STATUS_LABELS[$value] ?? (string) ($value ?? '—');
    }

    /**
     * Friendly display label for patients.status (lifecycle).
     */
    public static function pregnancyStatusLabel(string $status): string
    {
        return match ($status) {
            'ONGOING' => 'Ongoing Pregnancy',
            'DELIVERED' => 'Delivered',
            'REFERRED' => 'Legacy Referred',
            default => $status,
        };
    }
}