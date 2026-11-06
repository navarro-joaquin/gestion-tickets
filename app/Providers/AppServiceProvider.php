<?php

namespace App\Providers;

use App\Domain\Ticketing\Ticket;
use App\Domain\Users\UserRole;
use App\Models\User;
use App\Policies\TicketPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?: $request->ip(),
            );
        });

        RateLimiter::for('public-catalog', function (Request $request): Limit {
            return Limit::perMinute(
                (int) config('tickets.rate_limits.catalog_per_minute'),
            )->by($request->ip());
        });

        RateLimiter::for('public-tickets', function (Request $request): Limit {
            return Limit::perMinute(
                (int) config('tickets.rate_limits.tickets_per_minute'),
            )->by($request->ip());
        });

        RateLimiter::for('public-display', function (Request $request): Limit {
            return Limit::perMinute(
                (int) config('tickets.rate_limits.display_per_minute'),
            )->by($request->ip());
        });

        Gate::policy(Ticket::class, TicketPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->is_active && $user->role === UserRole::Admin
                ? true
                : null;
        });
    }
}
