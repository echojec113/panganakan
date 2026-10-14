<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Support\PregnancyOutcomeVocabulary;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DEPLA Phase 3E.2 — Patient Portal "My Medical Records" (read-only),
 * extended by Phase 3E.3 with Ultrasound Records, Birth Plan, Delivery
 * Records and Baby Information.
 *
 * Scope is deliberately narrow: ONE method, ONE GET route, NO writes.
 * The page renders only the pregnancy row currently linked to the
 * authenticated login through `patientAccount` (users.id ->
 * patients.user_id). Nothing here reads an ID from the URL, query string
 * or request body, so a patient can never address another record.
 *
 * HISTORICAL PREGNANCIES ARE OUT OF SCOPE. `patients.user_id` is UNIQUE
 * and `PatientController::startNewPregnancy()` does not move the link, so
 * the account may still point at an earlier (DELIVERED) pregnancy row.
 * That linked row is what is displayed. No name/birthdate matching is
 * performed to discover other episodes — such a lookup would be an
 * undocumented second ownership rule.
 *
 * PRIVACY. Clinical rows are fetched with explicit column allow-lists and
 * then copied into plain presentation arrays before the view is built, so
 * the Blade receives no Eloquent model at all. Risk assessment, ML
 * predictions, factor evidence, assessment metadata, decision metadata,
 * staff verification fields, referral internals and free-text clinical
 * notes are excluded at the SQL level and are therefore unreachable even
 * if a future template edit forgets a guard.
 *
 * PHASE 3E.3 ADDITIONS keep the same contract. Ultrasound `remarks`,
 * `report_file` and `report_image` (staff notes, storage paths, raw
 * filenames) are never selected — no image preview, report download or
 * file-serving route exists on this page. Birth plan `notes` is never
 * selected. Pregnancy-outcome confirmation provenance
 * (`confirmation_source`, `confirmed_at`, `confirmed_by`,
 * `follow_up_status`, `follow_up_recorded_at/by`) and outcome `notes` are
 * never selected; only the patient-facing facts (patients.delivery_date,
 * outcome_type, delivery_location) are read.
 *
 * Eligibility is enforced upstream by the route middleware chain
 * ['auth', 'patient-portal-account', 'must-change-password', 'no-store']
 * (guests -> login, unlinked/archived -> 403, flagged -> password change,
 * responses never cacheable). The role check and resolveOwnPatient()
 * below repeat the same defence-in-depth contract used by
 * PatientPortalController and PatientAppointmentController.
 */
class PatientMedicalRecordsController extends Controller
{
    /**
     * Pregnancy Overview columns. `id` is required so the eager-loaded
     * relations can resolve their foreign key; it is never rendered.
     * Everything else is an approved overview field — note that
     * `patients.risk_level` was dropped in migration
     * 2026_03_12_105610 and is deliberately absent.
     *
     * `delivery_date` (migration 2026_03_31_174932) is selected here purely
     * as the source column for the Phase 3E.3 Delivery Records section —
     * patients.delivery_date is the single canonical delivery date. It is
     * NOT rendered in Pregnancy Overview, whose field list stays exactly as
     * shipped in Phase 3E.2.
     */
    private const OVERVIEW_COLUMNS = [
        'id',
        'status',
        'lmp',
        'edd',
        'gravida',
        'para',
        'previous_cs',
        'miscarriage',
        'delivery_date',
    ];

    /**
     * Prenatal visit observation columns. Anything not listed here —
     * risk_level, risk_reasons, ml_prediction, ml_valid, factor_evidence,
     * assessment_metadata, decision_source, rule_reasons, missing_records,
     * urgency, bp_assessment, assessment, recommendation, treatment_plan,
     * notes, hypertension/diabetes/anemia visit flags, presenting_part,
     * uterine_activity, cervical_dilation, bag_of_water and every repeat
     * BP / staff-provenance column — is never selected from the database.
     */
    private const VISIT_COLUMNS = [
        'id',
        'patient_id',
        'visit_date',
        'gestational_age',
        'bp_sys',
        'bp_dia',
        'weight',
        'temperature',
        'fundic_height',
        'fetal_heart_tone',
        'fetal_movement',
        'next_visit_date',
    ];

    /**
     * Medical history columns: the 18 recorded boolean conditions plus
     * `other_specify`. `patient_id` is needed for hasOne matching.
     */
    private const HISTORY_COLUMNS = [
        'id',
        'patient_id',
        'epilepsy',
        'severe_headache',
        'visual_disturbance',
        'chest_pain',
        'shortness_breath',
        'breast_mass',
        'liver_disease',
        'smoking',
        'allergies',
        'drug_intake',
        'std_history',
        'diabetes',
        'hypertension',
        'asthma',
        'thyroid_disease',
        'heart_disease',
        'anemia',
        'mental_health_condition',
        'other_specify',
    ];

    /**
     * Ultrasound observation columns. Deliberately ABSENT: `remarks`
     * (staff free text), `report_file` and `report_image` (storage paths /
     * raw filenames). Phase 3E.3 ships no image preview, no report
     * download and no file-serving route for the portal, so those three
     * columns must never leave the database.
     */
    private const ULTRASOUND_COLUMNS = [
        'id',
        'patient_id',
        'scan_date',
        'fetal_heartbeat',
        'fetal_movement',
        'presentation',
        'amniotic_fluid',
        'placenta_position',
        'gestational_age_scan',
        'estimated_fetal_weight',
    ];

    /**
     * Birth plan answer columns. Deliberately ABSENT: `notes` (unapproved
     * free-text remarks / internal staff notes).
     */
    private const BIRTH_PLAN_COLUMNS = [
        'id',
        'patient_id',
        'planned_visits',
        'deliver_in_clinic',
        'delivery_location',
        'transportation',
        'transport_cost',
        'payment_method',
        'saving_started',
        'birth_companion',
        'caregiver_home',
        'plan_more_children',
        'number_more_children',
        'knows_fp_method',
        'used_fp_before',
        'family_planning_method',
        'fp_source',
    ];

    /**
     * Pregnancy outcome columns. Deliberately ABSENT: `follow_up_status`,
     * `follow_up_recorded_at`, `follow_up_recorded_by`,
     * `confirmation_source`, `confirmed_at`, `confirmed_by` and `notes`
     * — all internal confirmation/follow-up provenance.
     */
    private const OUTCOME_COLUMNS = [
        'id',
        'patient_id',
        'outcome_type',
        'delivery_location',
    ];

    /**
     * Baby columns. Names are composed into one display string; there is no
     * baby photograph, no staff field and no newborn metadata on this page.
     */
    private const BABY_COLUMNS = [
        'id',
        'patient_id',
        'first_name',
        'middle_name',
        'last_name',
        'sex',
        'date_of_birth',
        'time_of_birth',
        'birth_weight',
        'birth_length',
    ];

    /**
     * Read-only presentation of the linked pregnancy. Strictly GET.
     */
    public function index(Request $request): View
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        // Presentation allow-list #1: overview fields only.
        $overview = [
            'status' => $patient->status !== null && $patient->status !== ''
                ? (string) $patient->status
                : null,
            'lmp' => $this->formatDate($patient->lmp),
            'edd' => $this->formatDate($patient->edd),
            'gravida' => $patient->gravida,
            'para' => $patient->para,
            'previous_cs' => $patient->previous_cs === null
                ? null
                : ((bool) $patient->previous_cs ? 'Yes' : 'No'),
            // Stored as an integer count; the clinic's intake form records
            // it as Yes/No (0/1). Anything else is shown as the count —
            // a NULL is unrecorded and is never converted into "No".
            'miscarriage' => $patient->miscarriage === null
                ? null
                : (((int) $patient->miscarriage) === 0
                    ? 'No'
                    : (((int) $patient->miscarriage) === 1
                        ? 'Yes'
                        : (string) $patient->miscarriage)),
        ];

        // Presentation allow-list #2: approved prenatal observations only.
        // Newest first; `id` is the stable tie-breaker for equal dates.
        $visits = $patient->prenatalVisits
            ->map(fn ($visit): array => [
                'visit_date' => $this->formatDate($visit->visit_date),
                'gestational_age' => $visit->gestational_age,
                'bp_sys' => $visit->bp_sys,
                'bp_dia' => $visit->bp_dia,
                'weight' => $visit->weight,
                'temperature' => $visit->temperature,
                'fundic_height' => $visit->fundic_height,
                'fetal_heart_tone' => $visit->fetal_heart_tone,
                'fetal_movement' => $visit->fetal_movement,
                'next_visit_date' => $this->formatDate($visit->next_visit_date),
            ])
            ->values()
            ->all();

        // Presentation allow-list #3: recorded conditions only.
        $medicalHistory = $patient->medicalHistory === null
            ? null
            : $this->presentMedicalHistory($patient->medicalHistory);

        // Phase 3E.3 presentation allow-lists: ultrasound scans (newest
        // first, id breaks same-day ties), the single birth plan, the
        // patient-facing delivery facts and every baby of this pregnancy.
        $ultrasounds = $patient->ultrasounds
            ->map(fn ($scan): array => $this->presentUltrasound($scan))
            ->values()
            ->all();

        $birthPlan = $patient->birthPlan === null
            ? null
            : $this->presentBirthPlan($patient->birthPlan);

        $delivery = $this->presentDelivery($patient);

        $babies = $patient->babies
            ->map(fn ($baby): array => $this->presentBaby($baby))
            ->values()
            ->all();

        return view('patient.medical-records.index', [
            'overview' => $overview,
            'visits' => $visits,
            'medicalHistory' => $medicalHistory,
            'ultrasounds' => $ultrasounds,
            'birthPlan' => $birthPlan,
            'delivery' => $delivery,
            'babies' => $babies,
        ]);
    }

    /**
     * Non-patient roles are denied here because this page exposes patient
     * clinical data. The portal middleware deliberately passes clinic
     * roles through (so the dashboard can redirect them), so the explicit
     * check stays — same contract as PatientPortalController::profile().
     */
    private function portalUserOrAbort(Request $request): User
    {
        $user = $request->user();

        if ($user === null || $user->role !== 'patient') {
            abort(403);
        }

        return $user;
    }

    /**
     * Resolve the record that belongs to THIS login, or refuse.
     *
     * Uses the `patientAccount` hasOne relationship itself (with an
     * explicit column allow-list), so an unlinked account resolves to
     * null and an archived account is hidden by Patient's SoftDeletes
     * global scope. The refusal reuses the Phase 2D wording and discloses
     * nothing about which case occurred.
     */
    private function resolveOwnPatient(User $user): Patient
    {
        $patient = $user->patientAccount()
            ->select(self::OVERVIEW_COLUMNS)
            ->with([
                'prenatalVisits' => fn ($query) => $query
                    ->select(self::VISIT_COLUMNS)
                    ->orderByDesc('visit_date')
                    ->orderByDesc('id'),
                'medicalHistory' => fn ($query) => $query->select(self::HISTORY_COLUMNS),
                // Phase 3E.3 — newest scan first; id breaks same-day ties.
                // SoftDeletes on Ultrasound/BirthPlan means trashed rows are
                // hidden by the global scope, exactly as in the clinic views.
                'ultrasounds' => fn ($query) => $query
                    ->select(self::ULTRASOUND_COLUMNS)
                    ->orderByDesc('scan_date')
                    ->orderByDesc('id'),
                'birthPlan' => fn ($query) => $query->select(self::BIRTH_PLAN_COLUMNS),
                'pregnancyOutcome' => fn ($query) => $query->select(self::OUTCOME_COLUMNS),
                'babies' => fn ($query) => $query
                    ->select(self::BABY_COLUMNS)
                    ->orderBy('id'),
            ])
            ->first();

        if ($patient === null) {
            abort(
                403,
                'Your patient account is currently unavailable. Please contact the clinic for assistance.'
            );
        }

        return $patient;
    }

    /**
     * Copy the medical history into a plain array keyed by readable
     * groups. Only the whitelisted condition keys can reach the view.
     *
     * @return array{groups: array<string, list<array{label: string, value: string}>>, other: string|null}
     */
    private function presentMedicalHistory($history): array
    {
        $conditions = [
            'Conditions Also Assessed During Prenatal Visits' => [
                'diabetes' => 'Diabetes',
                'anemia' => 'Anemia',
            ],
            'Chronic & Background Conditions' => [
                'epilepsy' => 'Epilepsy',
                'hypertension' => 'Hypertension',
                'asthma' => 'Asthma',
                'thyroid_disease' => 'Thyroid Disease',
                'heart_disease' => 'Heart Disease',
                'liver_disease' => 'Liver Disease',
                'mental_health_condition' => 'Mental Health Condition',
            ],
            'Lifestyle, History & Physical Findings' => [
                'smoking' => 'Smoking',
                'allergies' => 'Allergies',
                'drug_intake' => 'Drug Intake',
                'std_history' => 'STD History',
                'breast_mass' => 'Breast Mass',
            ],
            'Previously Reported or Recurring Concerns' => [
                'severe_headache' => 'Severe Headache',
                'visual_disturbance' => 'Visual Disturbance',
                'chest_pain' => 'Chest Pain',
                'shortness_breath' => 'Shortness of Breath',
            ],
        ];

        $groups = [];

        foreach ($conditions as $groupLabel => $fields) {
            $rows = [];

            foreach ($fields as $key => $label) {
                $rows[] = [
                    'label' => $label,
                    'value' => $history->{$key} ? 'Yes' : 'No',
                ];
            }

            $groups[$groupLabel] = $rows;
        }

        $other = $history->other_specify;

        return [
            'groups' => $groups,
            'other' => is_string($other) && trim($other) !== '' ? trim($other) : null,
        ];
    }

    /**
     * Phase 3E.3 — one ultrasound scan, reduced to approved observations.
     *
     * `estimated_fetal_weight` is stored in GRAMS (migration
     * 2026_03_22_042836) and is rendered with an explicit "g" so the number
     * can never be misread as kilograms. `gestational_age_scan` is an
     * integer week count cast to float; it is rendered to one decimal to
     * match the rest of this page.
     *
     * @return array<string, mixed>
     */
    private function presentUltrasound($scan): array
    {
        return [
            'scan_date' => $this->formatDate($scan->scan_date),
            'gestational_age_scan' => $scan->gestational_age_scan === null || $scan->gestational_age_scan === ''
                ? null
                : number_format((float) $scan->gestational_age_scan, 1, '.', '') . ' weeks',
            'estimated_fetal_weight' => $this->presentGrams($scan->estimated_fetal_weight),
            'fetal_heartbeat' => $this->presentText($scan->fetal_heartbeat),
            'fetal_movement' => $this->presentText($scan->fetal_movement),
            'presentation' => $this->presentText($scan->presentation),
            'amniotic_fluid' => $this->presentText($scan->amniotic_fluid),
            'placenta_position' => $this->presentText($scan->placenta_position),
        ];
    }

    /**
     * Phase 3E.3 — the single birth plan, reduced to patient-facing answers.
     *
     * The boolean columns are stored as 0/1 with a `0` database default, so
     * they are almost never NULL; a NULL is still treated as unrecorded and
     * is never converted into a "No" answer. Free-text answers are trimmed
     * and an empty string is unrecorded.
     *
     * @return array<string, array{label: string, value: string, kind: string}>
     */
    private function presentBirthPlan($plan): array
    {
        $answers = [
            ['Planned Prenatal Visits', $this->presentCount($plan->planned_visits), 'text'],
            ['Plan to Deliver in This Clinic', $this->presentYesNo($plan->deliver_in_clinic), 'yesno'],
            ['Planned Delivery Location', $this->presentText($plan->delivery_location), 'text'],
            ['Transportation Arrangement', $this->presentText($plan->transportation), 'text'],
            ['Transportation Will Cost Money', $this->presentYesNo($plan->transport_cost), 'yesno'],
            ['Planned Payment Method', $this->presentText($plan->payment_method), 'text'],
            ['Started Saving for Delivery', $this->presentYesNo($plan->saving_started), 'yesno'],
            ['Birth Companion', $this->presentText($plan->birth_companion), 'text'],
            ['Caregiver at Home', $this->presentText($plan->caregiver_home), 'text'],
            ['Plan to Have More Children', $this->presentYesNo($plan->plan_more_children), 'yesno'],
            ['Number of More Children Planned', $this->presentCount($plan->number_more_children), 'text'],
            ['Knows a Family Planning Method', $this->presentYesNo($plan->knows_fp_method), 'yesno'],
            ['Used Family Planning Before', $this->presentYesNo($plan->used_fp_before), 'yesno'],
            ['Family Planning Method', $this->presentText($plan->family_planning_method), 'text'],
            ['Family Planning Method Source', $this->presentText($plan->fp_source), 'text'],
        ];

        $rows = [];

        foreach ($answers as [$label, $value, $kind]) {
            if ($value === null) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $value,
                'kind' => $kind,
            ];
        }

        return $rows;
    }

    /**
     * Phase 3E.3 — patient-facing delivery facts only.
     *
     * A delivery record is presented only when at least one DELIVERED fact
     * was actually recorded (patients.delivery_date, outcome_type or
     * delivery_location). A `pregnancy_outcomes` row whose fact columns are
     * all NULL is documented as "no observed evidence yet" and therefore
     * renders the neutral empty state — it never implies that a delivery
     * happened. Nothing is inferred from the EDD, from a gap in prenatal
     * visits or from `patients.status`.
     *
     * `outcome_type` is displayed with an accurate human-readable label;
     * DELIVERED is the only persisted outcome value in the vocabulary
     * (PregnancyOutcomeVocabulary), and an unknown value is passed through
     * unchanged rather than being silently rewritten.
     *
     * @return array{outcome: ?string, delivery_date: ?string, delivery_location: ?string}|null
     */
    private function presentDelivery(Patient $patient): ?array
    {
        $outcome = $patient->pregnancyOutcome;

        $outcomeType = $outcome === null ? null : $this->presentText($outcome->outcome_type);
        $deliveryLocation = $outcome === null ? null : $this->presentText($outcome->delivery_location);
        $deliveryDate = $this->formatDate($patient->delivery_date);

        if ($outcomeType === null && $deliveryLocation === null && $deliveryDate === null) {
            return null;
        }

        return [
            'outcome' => $outcomeType === null
                ? null
                : ($outcomeType === PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED
                    ? 'Delivered'
                    : $outcomeType),
            'delivery_date' => $deliveryDate,
            'delivery_location' => $deliveryLocation === null
                ? null
                : PregnancyOutcomeVocabulary::deliveryLocationLabel($deliveryLocation),
        ];
    }

    /**
     * Phase 3E.3 — one baby, reduced to the verified newborn facts.
     *
     * Units follow the clinic's existing baby views: birth weight is
     * kilograms (decimal 5,2) via WeightFormatter, birth length is
     * centimetres (decimal 5,1), and the time of birth is a 12-hour clock.
     *
     * @return array<string, mixed>
     */
    private function presentBaby($baby): array
    {
        return [
            'name' => $this->presentBabyName($baby),
            'sex' => $this->presentText($baby->sex),
            'date_of_birth' => $this->formatDate($baby->date_of_birth),
            'time_of_birth' => $this->formatTime($baby->time_of_birth),
            'birth_weight' => $baby->birth_weight === null || $baby->birth_weight === ''
                ? null
                : \App\Support\WeightFormatter::formatKg($baby->birth_weight) . ' kg',
            'birth_length' => $baby->birth_length === null || $baby->birth_length === ''
                ? null
                : $baby->birth_length . ' cm',
        ];
    }

    /** Compose first / middle / last into one display name. */
    private function presentBabyName($baby): ?string
    {
        $parts = array_filter([
            $this->presentText($baby->first_name),
            $this->presentText($baby->middle_name),
            $this->presentText($baby->last_name),
        ], fn ($part) => $part !== null);

        return count($parts) === 0 ? null : implode(' ', $parts);
    }

    /** Trimmed non-empty string, or null when unrecorded. */
    private function presentText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /** Yes / No for the boolean birth-plan columns; NULL stays unrecorded. */
    private function presentYesNo(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ((int) $value) === 1 ? 'Yes' : 'No';
    }

    /** Integer counts (planned visits, planned children) as plain numbers. */
    private function presentCount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) (int) $value;
    }

    /** Grams with a trailing "g"; trailing zeros are trimmed. */
    private function presentGrams(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $grams = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return ($grams === '' || $grams === '-' ? '0' : $grams) . ' g';
    }

    /**
     * Existing portal date convention (M d, Y) — the same format used by
     * PrenatalVisit::getMonitoringNextVisitLabel() and the clinic's
     * prenatal views. Missing dates stay null so the view can render an
     * honest "Not recorded" instead of a fabricated value.
     */
    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Carbon\Carbon::parse($value)->format('M d, Y');
    }

    /**
     * Existing baby-view clock convention (h:i A, e.g. 03:25 PM).
     */
    private function formatTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Carbon\Carbon::parse($value)->format('h:i A');
    }
}
