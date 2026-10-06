<?php

namespace App\Providers;

use App\Models\User;
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
    }
}
