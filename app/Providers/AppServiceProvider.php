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
        // PDFs only use local fonts and images: never fetch URLs, and read files only from these folders
        // (the default chroot is the whole project, .env included)
        config([
            'dompdf.options.enable_remote' => false,
            'dompdf.options.chroot' => [
                resource_path('fonts'),
                public_path('img'),
                storage_path('fonts'),
                base_path('vendor/dompdf/dompdf/lib'),
            ],
        ]);

        // Any Password::defaults() rule follows the same strength policy as password changes
        Password::defaults(fn () => PasswordPolicy::strength());
    }
}
