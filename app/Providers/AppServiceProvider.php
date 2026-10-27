<?php

namespace App\Providers;

use App\Support\PasswordPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // PDFs embed the Tinos report font; embed only the glyphs each document uses to keep files small
        config(['dompdf.options.enable_font_subsetting' => true]);

        // Any Password::defaults() rule follows the same strength policy as password changes
        Password::defaults(fn () => PasswordPolicy::strength());
    }
}
