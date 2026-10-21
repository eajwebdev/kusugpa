<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rebuild a week's auto-created Hauler TLS LIQ entries from its TLS upload; edited entries are kept
Artisan::command('tls:regenerate {crop_year} {week_no}', function (string $crop_year, string $week_no) {
    $result = \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\TlsHaulingLiquidationGenerator::class)
        ->regenerate($crop_year, $week_no, null));

    $this->info("Removed {$result['removed']} auto-created entries, kept {$result['kept']} edited ones, created {$result['entries']} entries (one per truck).");
    if ($result['unassigned_plates']) {
        $this->line(count($result['unassigned_plates']) . ' truck(s) need a payee picked on Edit.');
    }
})->purpose('Rebuild a week of TLS hauling liquidation entries, one per truck');
