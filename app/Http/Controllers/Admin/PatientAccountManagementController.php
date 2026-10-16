<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPatientAccountIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PatientSetupTokenService;
use App\Services\PatientSessionRevocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * DEPLA Phase 2 Sprint 1 + Sprint 2 - centralized Admin Patient Account
 * Management.
 *
 * WRITE BOUNDARY
 * --------------
 * One write surface exists here - the Sprint 2 account lifecycle via
 * SoftDeletes (deactivate = delete(), reactivate = restore()). No permanent
 * deletion exists; `users` rows, passwords, must_change_password flags,
 * pregnancy links, pointers and every clinical record are preserved
 * byte-for-byte across a cycle. Every deactivation/reactivation also
 * actively revokes the patient's live access: the remember token is rotated,
 * the patient's `sessions` rows are deleted, ordinary and namespaced setup
 * tokens in password_reset_tokens are purged, and a cache revocation marker
 * is written so the driver-independent middleware can reject any session
 * that still resolves. Reauthentication is required after reactivation.
 *
 * The former Sprint 1 "Send Password Reset Link" action was removed as
 * redundant: patients already reset their own passwords through the
 * patient-facing Forgot Password flow on the login page, and that flow is
 * untouched here.
 *
 * STATUS DOCTRINE (identical to the mandate, never email_verified_at)
 * -------------------------------------------------------------------
 *   deactivated  = deleted_at IS NOT NULL
 *   pending      = deleted_at IS NULL AND must_change_password = true
 *   active       = deleted_at IS NULL AND must_change_password = false
 *
 * AUTHORIZATION
 * -------------
 * Three independent layers: the 'auth' + 'clinic' route group, the
 * AdminMiddleware on the route group, and the in-controller re-checks
 * below. Hiding the sidebar link is cosmetic only.
 */
class PatientAccountManagementController extends Controller
{
    /**
     * Admin-only defense-in-depth check (on top of AdminMiddleware and
     * AdminPatientAccountIndexRequest::authorize()), mirroring the
     * PatientAccountController resend boundary.
     */
    private function assertAdmin(?object $actor): void
    {
        abort_unless(
            $actor instanceof User
                && ! $actor->trashed()
                && $actor->role === 'admin',
            403
        );
    }

    public function __construct(
        private readonly PatientSessionRevocationService $revocation,
    ) {
    }

    /**
     * Actively revoke every live credential of one patient account.
     *
     * Called inside the deactivate/reactivate transaction while the model is
     * in a live (non-trashed) state so the remember-token rotation is a
     * plain UPDATE. Four independent credentials are destroyed:
     *
     *  1. The remember token is rotated, so every remember-me recaller
     *     cookie already issued to the patient fails the provider's
     *     hash_equals check on its next use.
     *  2. Every `sessions` row owned by this user is deleted (scoped strictly
     *     by the indexed user_id column - other users' sessions, including
     *     the acting admin's, are never touched).
     *  3. Ordinary password-reset tokens (keyed by the plain email) are
     *     purged, so a previously emailed reset link can no longer be used.
     *  4. Namespaced first-time-setup tokens (keyed setup:{id}) are purged
     *     the same way.
     *
     * The password itself and must_change_password are never modified here.
     */
    private function revokePatientAccess(User $account): void
    {
        $account->forceFill(['remember_token' => Str::random(60)])->save();

        DB::table('sessions')
            ->where('user_id', $account->getKey())
            ->delete();

        DB::table('password_reset_tokens')
            ->where('email', $account->email)
            ->delete();

        DB::table('password_reset_tokens')
            ->where('email', PatientSetupTokenService::KEY_PREFIX.$account->getKey())
            ->delete();
    }

    /**
     * Patient Account Management listing: real summary counts, validated
     * search/status controls, server-side pagination (10 per page) with
     * query-string preservation.
     *
     * Summary counts intentionally describe ALL patient accounts (they do
     * not jump around while typing a search) - the same doctrine the
     * Patient Records statistics already use. Only the LIST is filtered.
     */
    public function index(AdminPatientAccountIndexRequest $request): View
    {
        $this->assertAdmin($request->user());

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $status = (string) ($validated['status'] ?? 'all');

        $base = User::withTrashed()->where('role', 'patient');

        $summaryCounts = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)
                ->whereNull('deleted_at')
                ->where('must_change_password', false)
                ->count(),
            'pending' => (clone $base)
                ->whereNull('deleted_at')
                ->where('must_change_password', true)
                ->count(),
            // Not a summary card (the mandate asks for three), but the
            // Deactivated status filter still shows its real live count so
            // the archive is discoverable without guessing.
            'deactivated' => (clone $base)->whereNotNull('deleted_at')->count(),
        ];

        $query = (clone $base)
            ->with(['patientAccount:id,user_id,first_name,middle_name,last_name'])
            ->withCount('pregnancyLinks');

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    // The account display name is copied from the patient at
                    // provisioning time; a later staff edit of the patient
                    // name must still be searchable, so the linked live
                    // pregnancy's name columns are searched as well.
                    ->orWhereHas('patientAccount', function ($patientQuery) use ($search) {
                        $patientQuery->where(function ($nameQuery) use ($search) {
                            $nameQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    });
            });
        }

        match ($status) {
            'active' => $query
                ->whereNull('deleted_at')
                ->where('must_change_password', false),
            'pending' => $query
                ->whereNull('deleted_at')
                ->where('must_change_password', true),
            'deactivated' => $query->whereNotNull('deleted_at'),
            default => null,
        };

        $accounts = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.patient-accounts.index', [
            'accounts' => $accounts,
            'summaryCounts' => $summaryCounts,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Deactivate a PATIENT Portal account (Sprint 2 + session revocation).
     *
     * Inside one transaction: `deleted_at` is stamped, every live credential
     * the patient could still use is destroyed (remember token rotated,
     * `sessions` rows deleted, ordinary and setup tokens in
     * password_reset_tokens purged), and the audit row is written. The
     * password, must_change_password (so a Pending account returns to Pending
     * on reactivation), the patients pointer, every user_pregnancy_links row
     * and every clinical record stay untouched.
     *
     * After the transaction commits, a cache revocation marker is written so
     * the driver-independent middleware can reject any session that still
     * resolves. Login, the password broker and PatientSetupTokenService also
     * refuse the account through the SoftDeletingScope or their own
     * live-account recheck. The row is never hard-deleted and never leaves
     * the audit trail.
     *
     * Repeated submissions are refused with a predictable flash and no
     * duplicate audit row. Only patient portal ids are addressable; a
     * staff/admin id resolves to 404 (no authorization oracle).
     */
    public function deactivate(Request $request, User $account): RedirectResponse
    {
        $actor = $request->user();
        $this->assertAdmin($actor);

        abort_unless($account->role === 'patient', 404);

        $back = redirect()->route('patient-accounts.index', $request->query());

        if ($account->trashed()) {
            return $back->with(
                'error',
                'This account is already deactivated. Reactivate it to restore patient access.'
            );
        }

        DB::transaction(function () use ($account, $actor): void {
            $this->revokePatientAccess($account);

            $account->delete();

            AuditLog::create([
                'user_id' => $actor->id,
                'patient_id' => $account->patientAccount()->value('id'),
                'action' => 'PATIENT_ACCOUNT_DEACTIVATED',
                'module' => 'PATIENT',
                // ID-only: never the email, the name or any clinical value.
                'description' => sprintf(
                    'Admin deactivated patient portal account (user ID %d).',
                    $account->id
                ),
            ]);
        });

        $this->revocation->markRevoked($account);

        return $back->with(
            'success',
            'Patient account deactivated. The patient can no longer sign in until the account is reactivated.'
        );
    }

    /**
     * Reactivate a deactivated PATIENT Portal account (Sprint 2 + session revocation).
     *
     * Mirror of deactivate(): inside one transaction the SoftDeletes
     * restore() runs, the same credential revocation applies (remember token
     * rotated, `sessions` rows deleted, ordinary and setup tokens purged) and
     * one audit row is written. The account returns to exactly the status it
     * had before deactivation (Active if setup was completed, Pending Setup if
     * must_change_password is still true), because that flag was never
     * modified. A cache revocation marker is written after the transaction so
     * the middleware requires a fresh login even for a session that was alive
     * before reactivation. Same 404/403 doctrine and the same predictable
     * repeated-submission flash as deactivate().
     */
    public function reactivate(Request $request, User $account): RedirectResponse
    {
        $actor = $request->user();
        $this->assertAdmin($actor);

        abort_unless($account->role === 'patient', 404);

        $back = redirect()->route('patient-accounts.index', $request->query());

        if (! $account->trashed()) {
            return $back->with(
                'error',
                'This account is already active. No changes were made.'
            );
        }

        DB::transaction(function () use ($account, $actor): void {
            $account->restore();

            $this->revokePatientAccess($account);

            AuditLog::create([
                'user_id' => $actor->id,
                'patient_id' => $account->patientAccount()->value('id'),
                'action' => 'PATIENT_ACCOUNT_REACTIVATED',
                'module' => 'PATIENT',
                'description' => sprintf(
                    'Admin reactivated patient portal account (user ID %d).',
                    $account->id
                ),
            ]);
        });

        $this->revocation->markRevoked($account);

        return $back->with(
            'success',
            'Patient account reactivated. The patient can sign in again with their existing password or complete a pending setup.'
        );
    }
}
