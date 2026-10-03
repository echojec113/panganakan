<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use App\View\Composers\NotificationComposer;
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

    if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)) {
        URL::forceScheme('https');
    }
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
