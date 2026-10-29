<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Loan attachments were saved under storage/app/public, which /storage serves to anyone with the path.
 * Move them to storage/app (same relative path), reachable only through the token-checked routes.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('loan_attachments')) {
            return;
        }

        $public = Storage::disk('public');
        $private = Storage::disk('local');

        foreach (DB::table('loan_attachments')->whereNotNull('file_path')->pluck('file_path') as $path) {
            if (!$public->exists($path)) {
                continue;
            }
            if (!$private->exists($path)) {
                $private->writeStream($path, $public->readStream($path));
            }
            // Remove the public copy only once the private one is complete
            if ($private->exists($path) && $private->size($path) === $public->size($path)) {
                $public->delete($path);
            }
        }
    }

    public function down()
    {
        // Attachments stay private
    }
};
