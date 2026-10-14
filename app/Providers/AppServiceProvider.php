<?php

namespace App\Providers;

use App\Models\BorrowerRegistration;
use App\Models\BorrowingRequest;
use App\Models\User;
use App\Policies\BorrowerRegistrationPolicy;
use App\Policies\BorrowingRequestPolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(BorrowerRegistration::class, BorrowerRegistrationPolicy::class);
        Gate::policy(BorrowingRequest::class, BorrowingRequestPolicy::class);

        // Keep main's production trace privacy guard alongside Incoming policies.
        if (! config('app.debug')) {
            ini_set('zend.exception_ignore_args', '1');
        }
    }
}
