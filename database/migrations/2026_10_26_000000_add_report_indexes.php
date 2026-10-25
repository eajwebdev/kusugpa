<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the report filters and the scrolling tables (crop year + week / status, planter,
 * dates). They only speed up lookups: no data is changed.
 *
 * Whole columns are indexed so MySQL can count and group straight from the index (60x faster on a
 * large crop year than a prefix index, which makes it read every row). Where a table's row format
 * cannot hold a key that long, the index falls back to short prefixes (crop year "20262027" is 8
 * characters, week numbers 1-2). Tables or indexes that are missing / already there are skipped.
 */
return new class extends Migration
{
    /** index name => [table, [column => fallback MySQL prefix length or null]] */
    private const INDEXES = [
        'quedans_crop_year_week_no_idx' => ['quedans', ['crop_year' => 12, 'week_no' => 8]],
        'quedans_crop_year_status_idx' => ['quedans', ['crop_year' => 12, 'status' => 12]],
        'molasses_crop_year_week_no_idx' => ['molasses', ['crop_year' => 12, 'week_no' => 8]],
        'molasses_crop_year_status_idx' => ['molasses', ['crop_year' => 12, 'status' => 12]],
        'consolidated_uploads_crop_year_week_no_idx' => ['consolidated_uploads', ['crop_year' => 12, 'week_no' => 8]],
        'trucking_allowance_crop_year_week_no_idx' => ['trucking_allowance', ['crop_year' => 12, 'week_no' => 8]],
        'trucking_allowance_planter_code_idx' => ['trucking_allowance', ['planter_code' => 20]],
        'summary_crop_year_week_no_idx' => ['summary', ['crop_year' => 12, 'week_no' => 8]],
        'quedan_price_crop_year_week_no_idx' => ['quedan_price', ['crop_year' => 12, 'week_no' => 8]],
        'mol_price_crop_year_week_no_idx' => ['mol_price', ['crop_year' => 12, 'week_no' => 8]],
        'week_no_crop_year_week_no_idx' => ['week_no', ['crop_year' => 12, 'week_no' => 8]],
        'fuel_allowance_crop_year_week_no_idx' => ['fuel_allowance', ['crop_year' => 12, 'week_no' => 8, 'planter_code' => 20]],
        'cash_advances_crop_year_status_idx' => ['cash_advances', ['crop_year' => 12, 'status' => null]],
        'cash_advance_amortizations_due_date_idx' => ['cash_advance_amortizations', ['due_date' => null]],
        'loans_crop_year_status_idx' => ['loans', ['crop_year' => 12, 'status' => null]],
        'loan_amortizations_due_date_idx' => ['loan_amortizations', ['due_date' => null]],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => [$table, $columns]) {
            if (!Schema::hasTable($table) || $this->hasIndex($table, $name)) {
                continue;
            }
            foreach (array_keys($columns) as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue 2;
                }
            }

            if (DB::getDriverName() === 'mysql') {
                $types = collect(DB::select('SHOW COLUMNS FROM `' . $table . '`'))->mapWithKeys(fn ($c) => [$c->Field => strtolower($c->Type)]);
                $isText = fn ($column) => str_starts_with($types[$column] ?? '', 'varchar') || str_contains($types[$column] ?? '', 'text');
                $whole = collect(array_keys($columns))
                    // A TEXT column cannot be indexed whole
                    ->map(fn ($column) => '`' . $column . '`' . (str_contains($types[$column] ?? '', 'text') ? '(' . ($columns[$column] ?: 20) . ')' : ''))
                    ->implode(', ');
                $prefixed = collect($columns)
                    ->map(fn ($length, $column) => '`' . $column . '`' . ($length && $isText($column) ? '(' . $length . ')' : ''))
                    ->implode(', ');
                try {
                    DB::statement('ALTER TABLE `' . $table . '` ADD INDEX `' . $name . '` (' . $whole . ')');
                } catch (\Illuminate\Database\QueryException $e) {
                    // "Specified key was too long" on an older row format: index the column prefixes instead
                    DB::statement('ALTER TABLE `' . $table . '` ADD INDEX `' . $name . '` (' . $prefixed . ')');
                }
            } else {
                Schema::table($table, fn ($t) => $t->index(array_keys($columns), $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $name => [$table]) {
            if (Schema::hasTable($table) && $this->hasIndex($table, $name)) {
                Schema::table($table, fn ($t) => $t->dropIndex($name));
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return match (DB::getDriverName()) {
            'mysql' => DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$name]) !== [],
            'sqlite' => collect(DB::select('PRAGMA index_list(`' . $table . '`)'))->contains('name', $name),
            default => false,
        };
    }
};
