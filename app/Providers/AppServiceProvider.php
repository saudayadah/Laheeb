<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // The owner can do everything, regardless of individual permissions.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('owner') ? true : null;
        });

        // Strong passwords in production; relaxed locally so demo logins work.
        Password::defaults(function () {
            return $this->app->isProduction()
                ? Password::min(10)->letters()->numbers()->uncompromised()
                : Password::min(8);
        });

        $this->applyMailSettings();
    }

    /**
     * Outgoing mail configured from the settings screen (stored in the DB)
     * overrides .env, so the owner never needs a terminal. Any failure here
     * (missing table during install, bad cipher) silently keeps .env values.
     */
    private function applyMailSettings(): void
    {
        // config:cache boots the app to snapshot the config — skipping here
        // keeps the decrypted SMTP password OUT of bootstrap/cache/config.php.
        // The override still applies on every real run (web and queue alike).
        if ($this->app->runningInConsole()
            && array_intersect(['config:cache', 'optimize'], $_SERVER['argv'] ?? []) !== []) {
            return;
        }

        try {
            $host = AppSettings::get('mail_host');

            if (! is_string($host) || $host === '') {
                return;
            }

            $port = (int) AppSettings::get('mail_port', 465);
            $encrypted = (string) AppSettings::get('mail_password');

            // A rotated APP_KEY must not silently fall back to the log mailer:
            // keep SMTP active with no password so the failure is loud and honest.
            $password = null;
            if ($encrypted !== '') {
                try {
                    $password = Crypt::decryptString($encrypted);
                } catch (\Throwable) {
                    $password = null;
                }
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.scheme' => $port === 465 ? 'smtps' : null,
                'mail.mailers.smtp.timeout' => 15, // a wrong host should fail fast, not hang the request
                'mail.mailers.smtp.username' => AppSettings::get('mail_username'),
                'mail.mailers.smtp.password' => $password,
                'mail.from.address' => AppSettings::get('mail_username'),
                'mail.from.name' => (string) (AppSettings::get('mail_from_name')
                    ?: AppSettings::get('bakery_name_ar')),
            ]);
        } catch (\Throwable) {
            // Keep whatever .env provides.
        }
    }
}
