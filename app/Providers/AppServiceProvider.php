<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use App\View\Composers\NotificationComposer;
use App\View\Composers\PatientNotificationComposer;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    $this->abortWhenTestingWithCachedConfig();

    Mail::extend('brevo', function () {
        return new BrevoTransport();
    });

    View::composer('layouts.app', NotificationComposer::class);
    View::composer('patient.layout', PatientNotificationComposer::class);

    $this->registerPatientAppointmentWriteRateLimiter();

    if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)) {
        URL::forceScheme('https');
    }
    }

    /**
     * Phase 3D.4B (P1) - one conservative write budget for the four
     * Patient Portal appointment write routes (submit, cancel, request
     * cancellation, request reschedule).
     *
     * DEFENSE NOTES
     *
     * - Keyed per AUTHENTICATED USER id, not per IP: patients behind a
     *   shared clinic/NAT address must never throttle each other, and one
     *   account cannot spend another account's budget. The 'auth' route
     *   middleware runs BEFORE 'throttle' (framework middleware priority),
     *   so a signed-in patient is always resolved here from the session;
     *   the IP branch is only a defensive fallback for the (currently
     *   unreachable) unauthenticated case and keeps a guest flood from
     *   sharing a single unbounded bucket.
     *
     * - ONE shared bucket for all four endpoints: 10 write attempts per
     *   minute in total for that patient. A legitimate patient submits,
     *   cancels or reschedules a handful of times per session, so 10/min
     *   is generous while still bounding double-click storms, script
     *   abuse and enumeration of foreign appointment ids. Reads (GET
     *   list/form), Admin/Staff calendar management and notification
     *   read actions are NOT part of this limiter.
     *
     * - Named-limiter form ('throttle:patient-appointment-writes') rather
     *   than the numeric 'throttle:10,1' shorthand, because the numeric
     *   shorthand would key on the request IP and the per-user policy is
     *   the whole point of this control.
     *
     * - The cache driver is whatever the application already uses (the
     *   shared array/file/database cache); no new infrastructure, no new
     *   table and no schema change is introduced.
     */
    private function registerPatientAppointmentWriteRateLimiter(): void
    {
        RateLimiter::for('patient-appointment-writes', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(10)->by(
                $user !== null
                    ? 'patient-appointment-write:user:' . $user->getAuthIdentifier()
                    : 'patient-appointment-write:ip:' . $request->ip()
            );
        });
    }

    /**
     * Safety tripwire: abort boot when tests start with a cached
     * configuration.
     *
     * While bootstrap/cache/config.php exists, phpunit.xml env overrides
     * are ignored entirely, so a test run would silently resolve the real
     * MySQL database (maternity_system1) and RefreshDatabase would wipe it.
     * getenv() reads the real process environment (set by phpunit.xml
     * before the application boots) and is unaffected by the config cache,
     * which is why it is used instead of config('app.env').
     *
     * Normal local (APP_ENV=local) and production operation is unaffected.
     */
    private function abortWhenTestingWithCachedConfig(): void
    {
        if (getenv('APP_ENV') === 'testing'
            && file_exists($this->app->bootstrapPath('cache/config.php'))) {
            throw new RuntimeException(
                'Configuration cache detected while APP_ENV=testing. '
                . 'Tests must never boot with a cached configuration because '
                . 'it bypasses phpunit.xml and can target the production MySQL '
                . 'database. Run "php artisan optimize:clear" before running '
                . 'tests, then run the tests again.'
            );
        }
    }
}
