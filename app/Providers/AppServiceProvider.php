<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
    }
}
