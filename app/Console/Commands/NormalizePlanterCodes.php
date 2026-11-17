<?php

namespace App\Console\Commands;

use App\Support\PlanterCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts the planter codes already saved into the 5-digit form with leading zeros (9997 → 09997),
 * so a planter uploaded both ways is one planter again. New uploads are saved this way already.
 */
class NormalizePlanterCodes extends Command
{
    protected $signature = 'planters:normalize-codes {--dry-run : Only show what would change}';

    protected $description = 'Fix planter codes to 5 digits with leading zeros (9997 → 09997) in every table';

    /** Every model with a planter_code column */
    private const MODELS = [
        \App\Models\Quedan::class, \App\Models\Molass::class, \App\Models\ConsolidatedUpload::class,
        \App\Models\TruckingAllowance::class, \App\Models\FuelAllowance::class, \App\Models\RentalAllowance::class,
        \App\Models\UnderloadAllowance::class, \App\Models\TransloadingAllowance::class, \App\Models\FreshCaneIncentive::class,
        \App\Models\Mudpress::class, \App\Models\Summary::class, \App\Models\TlsHaulingUpload::class,
        \App\Models\HaulingLiquidation::class, \App\Models\PlanterDeduction::class, \App\Models\Loan::class,
        \App\Models\CashAdvance::class, \App\Models\VoucherBatchItem::class, \App\Models\PlanterProfile::class,
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $rows = [];
        $conflicts = [];

        // All tables or none: a failure part way leaves every code as it was
        if (!$dry) {
            DB::beginTransaction();
        }
        try {
            $this->fixTables($dry, $rows, $conflicts);
            if (!$dry) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (!$dry) {
                DB::rollBack();
            }
            $this->error('Nothing was changed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Table', 'Codes fixed', 'Rows ' . ($dry ? 'to update' : 'updated')], $rows);
        foreach ($conflicts as $conflict) {
            $this->warn($conflict);
        }
        $this->info($dry ? 'Dry run: nothing was changed. Run without --dry-run to apply.' : 'Planter codes fixed.');

        return self::SUCCESS;
    }

    private function fixTables(bool $dry, array &$rows, array &$conflicts): void
    {
        foreach (array_unique(array_map(fn ($m) => (new $m)->getTable(), self::MODELS)) as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'planter_code')) {
                continue;
            }
            // A number column cannot keep leading zeros (09997 is stored as 9997): leave it and say so
            $type = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)->where('COLUMN_NAME', 'planter_code')->value('DATA_TYPE');
            if ($type !== null && !in_array(strtolower($type), ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'], true)) {
                $conflicts[] = "{$table}.planter_code is a {$type} column and cannot keep leading zeros - skipped";
                continue;
            }

            // Planter profiles (one per code, few rows): code by code, so 9997 is not renamed onto an existing 09997
            if ($table === 'planter_profiles') {
                // [old, new] pairs: numeric codes would turn into integer array keys
                $codes = DB::table($table)->whereNotNull('planter_code')->pluck('planter_code')
                    ->map(fn ($c) => [(string) $c, PlanterCode::normalize($c)])
                    ->filter(fn ($pair) => $pair[0] !== $pair[1]);
                $existing = DB::table($table)->pluck('planter_code')->map(fn ($c) => (string) $c)->all();
                $changed = 0;
                foreach ($codes as [$old, $new]) {
                    if (in_array($new, $existing, true)) {
                        $conflicts[] = "planter_profiles: {$old} and {$new} are both profiles - keep one and deactivate or delete the other";
                        continue;
                    }
                    $changed += $dry ? 1 : DB::table($table)->where('planter_code', $old)->update(['planter_code' => $new]);
                }
                $rows[] = [$table, $codes->count(), $changed];
                continue;
            }

            // Every other table in one statement: numeric codes of up to 5 digits (after their leading zeros)
            // that are not already in the 5-digit form
            $target = "LPAD(TRIM(LEADING '0' FROM TRIM(planter_code)), " . PlanterCode::LENGTH . ", '0')";
            $where = "TRIM(planter_code) REGEXP '^[0-9]+$' AND TRIM(LEADING '0' FROM TRIM(planter_code)) <> ''"
                . " AND CHAR_LENGTH(TRIM(LEADING '0' FROM TRIM(planter_code))) <= " . PlanterCode::LENGTH
                . " AND BINARY planter_code <> BINARY {$target}";
            $found = DB::table($table)->whereRaw($where)
                ->selectRaw('COUNT(*) AS n, COUNT(DISTINCT planter_code) AS codes')->first();
            if (!$found || (int) $found->n === 0) {
                continue;
            }
            $changed = $dry ? (int) $found->n : DB::table($table)->whereRaw($where)->update(['planter_code' => DB::raw($target)]);
            $rows[] = [$table, (int) $found->codes, $changed];
        }
    }
}
