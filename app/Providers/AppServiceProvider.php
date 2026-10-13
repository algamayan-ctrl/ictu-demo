<?php

namespace App\Providers;

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
        /*
         * Outside debug mode, a logged stack trace must not quote the values
         * passed to each call: they can be the start of a document or a
         * person's details. Set here so it holds on any host, whatever its
         * php.ini says. Debug mode keeps full traces for development.
         */
        if (! config('app.debug')) {
            ini_set('zend.exception_ignore_args', '1');
        }
    }
}
