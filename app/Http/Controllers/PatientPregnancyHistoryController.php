<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PresentsPregnancyRecords;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * DEPLA Phase 3H.4 / 3H.4A — Patient Portal "Pregnancy History" (read-only,
 * DELIVERED episodes only).
 *
 * TWO GET routes, NO writes of any kind: no POST/PUT/PATCH/DELETE route
 * exists for pregnancy history, no switching or linking endpoint exists
 * anywhere in the system, and no GET request here may modify
 * user_pregnancy_links, patients or any clinical row. Ownership transfers
 * stay exclusively in the staff-only PregnancyContinuationService
 * transaction. There is deliberately NO "Link Previous Pregnancy" feature.
 *
 * SCOPE (delivered-only, lifecycle-automatic)
 * -------------------------------------------
 * Pregnancy History lists DELIVERED pregnancy episodes owned by the
 * login, INCLUDING the current pointer episode once its status becomes
 * DELIVERED - delivery recording alone makes an owned episode visible on
 * the next request, with no Start New Pregnancy step, no background job
 * and no linking action required. ONGOING pregnancies never appear here:
 * the current pregnancy belongs on the Patient Dashboard and My Medical
 * Records (/patient/medical-records), which remains unchanged and
 * pointer-scoped. The canonical delivered status value is the exact
 * string 'DELIVERED' (Patient::isDelivered(), Patient.php:203-206; the
 * same literal used by PregnancyContinuationService).
 *
 * OWNERSHIP CONTRACT (STRICT VERIFIED LINKS)
 * ------------------------------------------
 * Episodes are authorized ONLY by an explicit user_pregnancy_links
 * membership row for (authenticated user, requested episode). The
 * current-pregnancy pointer (patients.user_id) alone never substitutes
 * for that evidence: a pointer-only episode without a membership row is
 * not listed and is refused by show(). Names, birthdates, emails, phone
 * numbers, addresses and any demographic similarity are NEVER used to
 * authorize or discover episodes, and no link is ever created or
 * repaired during a GET.
 *
 * A URL episode ID cannot bypass ownership: a foreign ID has no membership
 * row (404), an unlinked episode has no membership row (404), an ONGOING
 * episode fails the delivered-only rule (404), an archived episode is
 * hidden by Patient's SoftDeletes scope (404), and a nonexistent ID
 * resolves to nothing (404).
 *
 * ELIGIBILITY. The middleware chain
 * ['auth', 'patient-portal-account', 'must-change-password', 'no-store']
 * refuses guests, unlinked accounts and archived pointers before this
 * controller runs. The explicit patient-role check below mirrors
 * PatientMedicalRecordsController because the portal middleware passes
 * clinic roles through. A valid non-archived current pointer remains a
 * precondition for BOTH routes (defence in depth over the middleware).
 *
 * PRIVACY. Episode rows are selected through minimal safe columns for the
 * listing; record rendering reuses the shared PresentsPregnancyRecords
 * allow-lists (identical seven sections, identical exclusions: no risk/ML
 * fields, no staff notes, no ultrasound remarks or file paths, no outcome
 * confirmation provenance, no referral or audit data).
 */
class PatientPregnancyHistoryController extends Controller
{
    use PresentsPregnancyRecords;

    /**
     * The canonical delivered status value, matching Patient::isDelivered()
     * and the literal used by the continuation service. Never guessed,
     * never a new vocabulary entry.
     */
    private const DELIVERED_STATUS = 'DELIVERED';

    /**
     * Listing columns for the delivered-pregnancy cards. `id` is required
     * only so the historical show route can be addressed from the card
     * link; it is never rendered as visible text. No email, contact
     * number, birthdate, address, civil status or philhealth field is
     * selected - the cards show safe dates and obstetric counts only.
     */
    private const HISTORY_CARD_COLUMNS = [
        'id',
        'status',
        'lmp',
        'edd',
        'gravida',
        'para',
        'delivery_date',
    ];

    /**
     * GET /patient/pregnancies — owned DELIVERED pregnancies (previous
     * episodes AND the current pointer episode once delivered).
     */
    public function index(Request $request): View
    {
        $user = $this->portalUserOrAbort($request);

        // Precondition: a valid non-archived current pointer (the account
        // must be a live portal login). Used only as the eligibility
        // precondition - never as an authorization source for episodes.
        $current = $this->resolveCurrentPointer($user);

        // Strict links-driven delivered episodes: only rows the user holds
        // verified membership for; archived episodes hidden by SoftDeletes;
        // canonical DELIVERED status only (an ONGOING or any other-status
        // row never appears - including an ONGOING pointer episode, which
        // the status filter excludes automatically). A DELIVERED pointer
        // episode IS listed: delivery recording alone surfaces it here.
        // Ordering is clinically meaningful (most recent delivery first:
        // delivery date, else EDD, else LMP) with id as the deterministic
        // tie-breaker. No artificial limit - every eligible episode is
        // listed.
        $previous = Patient::query()
            ->select(self::HISTORY_CARD_COLUMNS)
            ->whereIn('id', function ($query) use ($user): void {
                $query->select('patient_id')
                    ->from('user_pregnancy_links')
                    ->where('user_id', $user->id);
            })
            ->where('status', self::DELIVERED_STATUS)
            ->orderByDesc(DB::raw('COALESCE(delivery_date, edd, lmp)'))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Patient $episode): array => [
                // episodeId addresses the historical show route (membership-
                // and delivered-checked there); it is used only to build the
                // View Medical Records link and is NEVER rendered as visible
                // text.
                'episodeId' => $episode->id,
                'status' => $this->presentText($episode->status),
                'lmp' => $this->formatDate($episode->lmp),
                'edd' => $this->formatDate($episode->edd),
                'delivery_date' => $this->formatDate($episode->delivery_date),
                'gravida' => $episode->gravida,
                'para' => $episode->para,
            ])
            ->all();

        // No "current" payload: Phase 3H.4A removed the Current Pregnancy
        // card. Current pregnancy information lives on the Dashboard and
        // My Medical Records only.
        return view('patient.pregnancies.index', [
            'previous' => $previous,
        ]);
    }

    /**
     * GET /patient/pregnancies/{episodeId} — read-only records of ONE
     * owned DELIVERED episode, rendered by the shared seven-tab template
     * with a Pregnancy History back-link. Strictly GET; loads the episode
     * only after the membership row has been verified, then enforces the
     * delivered-only scope (an ONGOING, archived, foreign or unlinked
     * episode is a 404).
     */
    public function show(Request $request, int $episodeId): View
    {
        $user = $this->portalUserOrAbort($request);

        // Precondition (defence in depth, mirrors the middleware): a
        // portal login must still hold a valid non-archived pointer.
        $current = $this->resolveCurrentPointer($user);

        // STRICT membership first, episode load second. Missing link,
        // foreign ID, unlinked episode, archived episode and nonexistent
        // ID all land here as 404 - no fallback to the pointer, no
        // demographic lookup, no link repair.
        $episode = $this->resolveVerifiedEpisodeWithRecords($user, $episodeId);

        if ($episode === null) {
            abort(404);
        }

        // Delivered-only scope. An ONGOING historical row or the current
        // pointer episode while still ONGOING is not part of Pregnancy
        // History - the current pregnancy is served exclusively by
        // /patient/medical-records. A DELIVERED pointer episode IS in
        // scope: ownership was already proven by the membership row above,
        // and delivery recording alone is what surfaced it here.
        if ($episode->status !== self::DELIVERED_STATUS) {
            abort(404);
        }

        return view('patient.medical-records.index', array_merge(
            $this->presentEpisode($episode),
            [
                'backRoute' => 'patient.pregnancies.index',
                'backLabel' => 'Back to Pregnancy History',
                'historical' => true,
            ]
        ));
    }

    /**
     * Non-patient roles are denied (clinic roles pass the portal
     * middleware through) - same contract as PatientMedicalRecordsController.
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
     * The account's CURRENT pregnancy via the Phase 2A pointer, with the
     * safe listing columns only. Unlinked or archived -> null -> 403 with
     * the standard Phase 2D wording (the middleware normally catches this
     * first; the repetition is defence in depth). Used only as the
     * eligibility precondition - never as an authorization source for
     * listed or shown episodes, which always require a membership row.
     */
    private function resolveCurrentPointer(User $user): Patient
    {
        $current = $user->patientAccount()
            ->select(self::HISTORY_CARD_COLUMNS)
            ->first();

        if ($current === null) {
            abort(
                403,
                'Your patient account is currently unavailable. Please contact the clinic for assistance.'
            );
        }

        return $current;
    }
}
