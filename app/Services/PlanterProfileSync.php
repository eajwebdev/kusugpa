<?php

namespace App\Services;

use App\Models\ConsolidatedUpload;
use App\Models\PlanterProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds a Planter Profile for each planter in the Consolidated report that has none yet.
 * Used by Sync Planters and, after each Consolidated upload, for the uploaded week.
 *
 * A planter is never added twice: codes are compared ignoring spacing, case and leading
 * zeros ("00123" = "123", "ab 12" = "AB12"), existing profiles are never changed, and
 * one sync runs at a time so two uploads at once cannot both add the same planter.
 */
class PlanterProfileSync
{
    /**
     * @return array{planters: int, imported: int, skipped: int, errors: string[]}
     */
    public function sync(?string $cropYear = null, ?string $weekNo = null): array
    {
        return Cache::lock('planter-profile-sync', 60)->block(30, fn () => $this->run($cropYear, $weekNo));
    }

    /** Planter code as compared: no spaces, upper case, no leading zeros */
    public static function key($code): string
    {
        $code = strtoupper(preg_replace('/\s+/', '', trim((string) $code)));
        $stripped = ltrim($code, '0');

        return $stripped === '' ? $code : $stripped;
    }

    private function run(?string $cropYear, ?string $weekNo): array
    {
        $existing = PlanterProfile::pluck('planter_code')
            ->mapWithKeys(fn ($code) => [self::key($code) => true])
            ->all();

        // Latest upload first, so a new planter gets the name from their most recent record
        $query = ConsolidatedUpload::select('planter_code', 'planter_name', 'crop_year')
            ->orderByDesc('crop_year')
            ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
            ->orderByDesc('id');
        if ($cropYear !== null) {
            $query->where('crop_year', $cropYear);
        }
        if ($weekNo !== null) {
            $query->whereIn('week_no', array_values(array_unique([$weekNo, ltrim($weekNo, '0') ?: '0', str_pad(ltrim($weekNo, '0'), 2, '0', STR_PAD_LEFT)])));
        }
        $rows = $query->get();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $seen = [];

        DB::transaction(function () use ($rows, &$existing, &$seen, &$imported, &$skipped, &$errors) {
            foreach ($rows as $row) {
                $code = trim((string) $row->planter_code);
                $name = trim((string) $row->planter_name);

                if ($code === '' || $name === '') {
                    $errors[] = "Empty data: code={$code}, name={$name}";
                    continue;
                }

                $key = self::key($code);
                // Same planter appears once per week in the upload; count each planter once
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                if (isset($existing[$key])) {
                    $skipped++;
                    continue;
                }

                try {
                    PlanterProfile::create([
                        'planter_code' => $code,
                        'planter_name' => $name,
                        'status' => 'active',
                        'crop_year' => $row->crop_year,
                    ]);
                    $existing[$key] = true;
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Error on {$code}: " . $e->getMessage();
                }
            }
        });

        return ['planters' => count($seen), 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }
}
