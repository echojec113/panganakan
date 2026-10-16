<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DEPLA Phase 1.5 — first-time Patient Portal setup tokens.
 *
 * Purpose-separated from the ordinary Forgot Password broker WITHOUT a schema
 * change. The password_reset_tokens table's `email` column doubles as a
 * key namespace: ordinary reset tokens live under the account's plain email
 * address (the stock DatabaseTokenRepository convention), while setup tokens
 * live under `setup:{user_id}`. The two keyspaces are disjoint by
 * construction - the users.email unique index plus the `email` validation
 * rule guarantee no real account address can ever equal `setup:{id}` - so:
 *
 *   - Acceptance: the stock broker can never find a setup row (it queries
 *     where('email', <plain email>)); this service can never find a reset
 *     row (it queries where('email', 'setup:{id}')).
 *   - Invalidation: create()/consume() here touch only the `setup:{id}`
 *     key; the stock broker's deleteExisting() touches only plain-email
 *     keys. Requesting one kind of link can never invalidate the other.
 *   - Expiration: this service enforces its own 24-hour (1,440-minute)
 *     lifetime; the stock `users` broker keeps its 60-minute expiration
 *     untouched (PasswordBrokerManager converts the config minutes).
 *
 * Defense in depth: the stored hash is computed over
 * `setup:{id}:{plaintext}`, not the bare plaintext, so even a physically
 * transplanted row could not be accepted cross-purpose.
 *
 * Strict single-use: consume() runs the hash check, an eligibility recheck
 * (patient role, live account, must_change_password still true) and the
 * password write inside ONE DB::transaction holding lockForUpdate() on the
 * token row, so a competing request for the same token blocks, then fails
 * without changing the password. Token creation (create()) is a transactional
 * delete-then-insert on the same row key, so a resend atomically supersedes
 * any previous setup token.
 *
 * Lock ordering inside consume(): token row first, then the users row.
 * No other code path locks both in the reverse order.
 */
class PatientSetupTokenService
{
    /** First-time setup links live for exactly 24 hours (1,440 minutes). */
    public const EXPIRE_SECONDS = 24 * 60 * 60;

    /** Minimum seconds between two setup-token creations for one account. */
    public const THROTTLE_SECONDS = 60;

    /** Key namespace separating setup rows from ordinary reset rows. */
    public const KEY_PREFIX = 'setup:';

    /**
     * Issue a new setup token for the account, atomically superseding any
     * previous one. Returns the plaintext token (the ONLY copy; it is never
     * stored, logged or returned to any browser response).
     */
    public function create(User $user): string
    {
        $token = Str::random(64);
        $key = $this->keyFor($user);

        DB::transaction(function () use ($key, $user, $token): void {
            // Delete-then-insert mirrors DatabaseTokenRepository::create():
            // a resend invalidates every previous setup token immediately.
            DB::table('password_reset_tokens')->where('email', $key)->delete();

            DB::table('password_reset_tokens')->insert([
                'email' => $key,
                'token' => Hash::make($this->hashInput($user, $token)),
                'created_at' => now(),
            ]);
        });

        return $token;
    }

    /**
     * Read-only validity check (GET / refresh never consumes). Honors the
     * 24-hour lifetime and the purpose-bound hash.
     */
    public function exists(User $user, string $token): bool
    {
        $row = $this->row($user);

        if (! $row || $this->expired($row)) {
            return false;
        }

        return Hash::check($this->hashInput($user, $token), $row->token);
    }

    /**
     * True when a setup token for this account was created less than
     * THROTTLE_SECONDS ago (mirrors the broker's RESET_THROTTLED window).
     */
    public function recentlyCreated(User $user): bool
    {
        $row = $this->row($user);

        if (! $row || ! isset($row->created_at)) {
            return false;
        }

        return Carbon::parse($row->created_at)
            ->addSeconds(self::THROTTLE_SECONDS)
            ->isFuture();
    }

    /**
     * Whether the account may still complete a first-time setup at all
     * (independent of any token). Archived accounts fail via the SoftDeletes
     * default scope before this is even reached from the HTTP layer.
     */
    public function isEligible(?User $user): bool
    {
        return $user instanceof User
            && ! $user->trashed()
            && $user->role === 'patient'
            && (bool) $user->must_change_password;
    }

    /**
     * STRICT SINGLE-USE consumption.
     *
     * Everything happens inside one transaction while the token row is
     * locked:
     *   1. lock the token row and re-verify hash + 24-hour expiry under the
     *      lock (a concurrent consume that committed first leaves no row, so
     *      the second caller fails here);
     *   2. re-read the user under a row lock and recheck eligibility
     *      (a resend racing this consume cannot resurrect an already
     *      activated account - the flag recheck refuses);
     *   3. set the password, rotate the remember token, clear
     *      must_change_password;
     *   4. delete the token row;
     *   5. write the PATIENT_SETUP_COMPLETED audit row.
     *
     * Returns false without touching anything when the token or the account
     * is ineligible. SQLite's single-writer model and the row lock make the
     * sequential double-submit case deterministic; the parallel-race
     * guarantee on MySQL/InnoDB comes from lockForUpdate() on the token row.
     */
    public function consume(User $user, string $token, #[\SensitiveParameter] string $password): bool
    {
        return DB::transaction(function () use ($user, $token, $password): bool {
            $key = $this->keyFor($user);

            $row = DB::table('password_reset_tokens')
                ->where('email', $key)
                ->lockForUpdate()
                ->first();

            if (! $row || $this->expired($row) || ! Hash::check($this->hashInput($user, $token), $row->token)) {
                return false;
            }

            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if (! $this->isEligible($locked)) {
                return false;
            }

            $locked->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'must_change_password' => false,
            ])->save();

            DB::table('password_reset_tokens')->where('email', $key)->delete();

            $patientId = $locked->patientAccount()->value('id');

            AuditLog::create([
                'user_id' => $locked->id,
                'patient_id' => $patientId,
                'action' => 'PATIENT_SETUP_COMPLETED',
                'module' => 'PATIENT',
                'description' => sprintf(
                    'Patient portal first-time password setup completed for user ID %d (patient ID %s).',
                    $locked->id,
                    $patientId ?? 'unknown'
                ),
            ]);

            return true;
        });
    }

    /**
     * The namespaced row key. Uses the user id (not the email address) so
     * the composite length can never exceed the 255-char column and no
     * address text is stored on setup rows.
     */
    private function keyFor(User $user): string
    {
        return self::KEY_PREFIX . $user->getKey();
    }

    /**
     * Purpose-bound hash input: a bare reset token hashed elsewhere can never
     * validate against a setup row and vice versa.
     */
    private function hashInput(User $user, string $token): string
    {
        return $this->keyFor($user) . ':' . $token;
    }

    private function row(User $user): ?object
    {
        return DB::table('password_reset_tokens')
            ->where('email', $this->keyFor($user))
            ->first();
    }

    private function expired(object $row): bool
    {
        if (! isset($row->created_at)) {
            return true;
        }

        return Carbon::parse($row->created_at)
            ->addSeconds(self::EXPIRE_SECONDS)
            ->isPast();
    }
}
