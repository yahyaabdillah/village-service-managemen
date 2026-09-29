<?php

namespace App\Providers;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Gate;
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
        // Super Admin is defined by role, not by holding every permission row: a permission
        // added later is granted to it automatically, and nobody can lock the account out by
        // editing the matrix.
        Gate::before(fn ($user, string $ability) => method_exists($user, 'hasRole') && $user->hasRole(PermissionCatalog::SUPER_ROLE) ? true : null);
    }
}
