<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Summary;
use App\Models\TruckingAllowance;
use App\Models\FuelAllowance;
use App\Models\RentalAllowance;
use App\Models\UnderloadAllowance;
use App\Models\TransloadingAllowance;
use App\Models\AuditLog;
use App\Models\PlanterDeduction;
use App\Models\WeekNo;
use App\Services\PlanterProfileSync;
use App\Services\TlsHaulingLiquidationGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class UploadController extends Controller
{
    private const TEMPLATE_COLUMNS = [
        'summary' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'net_cane', 'net_amount'],
        'trucking' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'net_cane', 'ta_amount', 'trans_code'],
        'fci' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'fci_amount'],
        'fuel' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'liters', 'fuel_amount'],
        'rentals' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'rental_amount'],
        'underload' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'underload_amount'],
        'transloading' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'transloading_amount'],
        'mudpress' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'trans_code', 'charge_code', 'mpress'],
        'consolidated' => [
            'planter_code', 'assn_code', 'planter_name', 'assn_name', 'ta_wt', 'ta_amount',
            'emi_wt', 'emi_amount', 'pat_wt', 'pat_amount', 'cci_fa_wt', 'cci_fa_amt',
            'cci_fb_wt', 'cci_fb_amt', 'cci_fc_wt', 'cci_fc_amt', 'fuel_issuance_amt',
            'rental_amt', 'underload_amt', 'mudpress_amt', 'adj_amt',
        ],
        'quedan' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'qdn_no', 'tin_no', 'total_liens', 'sugar_lkg', 'labor_lkg'],
        'molasses' => ['crop_year', 'week_no', 'planter_code', 'planter_name', 'tin_no', 'mc_no', 'mol_net'],
        'tls' => [
            'reference_no', 'planter_code', 'planter_name', 'assn_code', 'assn_name', 'vehicle_type', 'plate_no',
            'tare_weight', 'gross_cane_weight', 'net_cane_wt', 'date_milled', 'trans_code', 'description', 'tdif_rate',
        ],
    ];

    /**
     * The CSV header of an upload type. With ?crop_year=&week_no= (picked in the upload form) and a
     * template that has those columns, a starter row carries them so the file shows which crop year
     * and week it is for; the upload refuses rows that do not match the selection.
     */
    public function downloadTemplate(Request $request, string $type)
    {
        abort_unless(isset(self::TEMPLATE_COLUMNS[$type]), 404);

        $columns = self::TEMPLATE_COLUMNS[$type];
        $cropYear = trim((string) $request->query('crop_year', ''));
        $weekNo = trim((string) $request->query('week_no', ''));
        $prefill = $cropYear !== '' && $weekNo !== '' && in_array('crop_year', $columns, true) && in_array('week_no', $columns, true);

        $row = null;
        $fileName = "kusugpa-{$type}-template.csv";
        if ($prefill) {
            $row = array_map(fn ($column) => match ($column) {
                'crop_year' => $cropYear,
                'week_no' => $weekNo,
                default => '',
            }, $columns);
            $fileName = 'kusugpa-' . $type . '-template-' . preg_replace('/[^0-9A-Za-z-]/', '', $cropYear) . '-wk' . preg_replace('/[^0-9A-Za-z]/', '', $weekNo) . '.csv';
        }

        return response()->streamDownload(function () use ($columns, $row) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $columns);
            if ($row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A row with only crop_year / week_no filled in (the template's starter row left unused) */
    private function isStarterRow(array $line, string $type): bool
    {
        $columns = self::TEMPLATE_COLUMNS[$type] ?? [];
        foreach ($line as $i => $cell) {
            if (trim((string) $cell) !== '' && !in_array($columns[$i] ?? null, ['crop_year', 'week_no'], true)) {
                return false;
            }
        }
        return true;
    }

    public function uploadCSV(Request $request, $type)
    {
        $permissionMap = [
            'summary' => 'upload-summary',
            'trucking' => 'upload-trucking',
            'fuel' => 'upload-fuel',
            'rentals' => 'upload-rentals',
            'underload' => 'upload-underload',
            'transloading' => 'upload-transloading',
            'fci' => 'upload-fci',
            'mudpress' => 'upload-mudpress',
            'consolidated' => 'upload-consolidated',
            'quedan' => 'upload-quedan',
            'molasses' => 'upload-molasses',
            'tls' => 'upload-tls',
        ];

        $requiredPermission= $permissionMap[$type] ?? null;

        if ($requiredPermission) {
            /** @var \App\Models\User $user */
            $user = auth()->user();
            
            if ($user->role !== 'Administrator' && $user->role !== 'super_admin') {
                $hasPermission = $user->hasPermission($requiredPermission);
                
                if (!$hasPermission) {
                    AuditLog::log('error', 'uploads', 'Unauthorized upload attempt: ' . $type);
                    return redirect()->back()
                        ->with('error', 'You do not have permission to upload ' . $type . ' files.');
                }
            }
        }

        $cropYear = $request->input('crop_year');
        $weekNo = $request->input('week_no');

        $request->validate([
            'file' => 'required|mimes:csv,txt|max:5120',
        ]);

        // Fuel rows become Fuel deductions for that crop year + week, so the week must exist
        if ($type === 'fuel') {
            $message = null;
            if (!$cropYear || !$weekNo) {
                $message = 'Select the Crop Year and Week No for the fuel upload.';
            } elseif (!WeekNo::where('crop_year', $cropYear)->where('week_no', $weekNo)->exists()) {
                $message = "Week {$weekNo} is not set up for crop year {$cropYear} in Crop & Week Management.";
            } elseif (PlanterDeduction::where('type', 'fuel')->where('source', 'upload')->where('crop_year', $cropYear)
                ->whereHas('weeks', fn ($q) => $q->where('week_no', (string) $weekNo))->exists()) {
                $message = "Uploaded fuel deductions for Crop Year {$cropYear} Week {$weekNo} already exist. Upload cancelled.";
            }
            if ($message) {
                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['error' => $message], 422);
                }
                return redirect()->back()->with('error', $message);
            }
        }

        try {
            // CHECK IF DATA ALREADY EXISTS FOR THIS WEEK/CROP YEAR
            $model = $this->getModel($type);
            $existingCount = $model::where('crop_year', $cropYear)
                ->where('week_no', $weekNo)
                ->count();

            if ($existingCount > 0) {
                $message = "Data for Crop Year {$cropYear} Week {$weekNo} already exists ({$existingCount} records). Upload cancelled.";
                
                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['error' => $message], 400);
                }
                return redirect()->back()->with('error', $message);
            }

            // Liquidation slips need the week end date from Crop & Week Management
            if ($type === 'tls' && !TlsHaulingLiquidationGenerator::weekEnding((string) $cropYear, (string) $weekNo)) {
                $message = "Week {$weekNo} of crop year {$cropYear} has no week end date. Set it in Crop & Week Management, then upload again.";
                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['error' => $message], 422);
                }
                return redirect()->back()->with('error', $message);
            }

            $file = $request->file('file');
            $csvData = file_get_contents($file);

            // Drop the UTF-8 BOM Excel adds, and read ANSI exports (Ñ saved as one byte) as Windows-1252
            $csvData = preg_replace('/^\xEF\xBB\xBF/', '', $csvData);
            if (!mb_check_encoding($csvData, 'UTF-8')) {
                $csvData = mb_convert_encoding($csvData, 'UTF-8', 'Windows-1252');
            }

            $delimiter= str_contains($csvData, "\t") ? "\t" : ',';
            
            Log::info('CSV Upload started', [
                'type' => $type,
                'delimiter' => $delimiter === "\t" ? 'tab' : 'comma',
                'file_size' => strlen($csvData)
            ]);
            
            $rows = explode("\n", trim($csvData));
            $lines = [];
            foreach ($rows as $row) {
                if (empty(trim($row))) continue;
                $line = str_getcsv($row, $delimiter);
                // Skip the template's starter row when nothing but crop year / week was filled in
                if ($this->isStarterRow($line, $type)) continue;
                $lines[] = $line;
            }
            
            // Files may come with or without the template's heading row: skip the first
            // row only when it is a header, otherwise import it as data.
            $hasHeader = !empty($lines) && $this->isHeaderRow($lines, $type);
            if ($hasHeader) {
                $header = array_shift($lines);
                Log::info('CSV header skipped', ['header' => $header, 'data_rows' => count($lines)]);
            }

            if (empty($lines)) {
                $message = 'The CSV has no data rows. Add records below the header and try again.';
                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['error' => $message], 422);
                }
                return redirect()->back()->with('error', $message);
            }
            
            $batchData = [];
            $errors = [];
            $userId = auth()->id();

            // The file's own crop year / week columns must match the selected ones
            if ($type === 'fuel') {
                $conflicts = $this->cropWeekConflicts($lines, (string) $cropYear, (string) $weekNo, $hasHeader ? 2 : 1);
                if (!empty($conflicts)) {
                    $message = "The file has rows for a different crop year / week than Crop Year {$cropYear} Week {$weekNo}: "
                        . implode('; ', array_slice($conflicts, 0, 5)) . (count($conflicts) > 5 ? '; ...' : '')
                        . '. Fix the file or select the matching crop year and week.';
                    if ($request->ajax() || $request->expectsJson()) {
                        return response()->json(['error' => $message], 422);
                    }
                    return redirect()->back()->with('error', $message);
                }
            }
            
            foreach ($lines as $index => $line) {
                if (empty(array_filter($line))) continue;

                $rowNumber = $index + ($hasHeader ? 2 : 1);
                
                $method = 'process' . ucfirst($type === 'rentals' ? 'rental' : $type) . 'Line';
                
                if (method_exists($this, $method)) {
                    $processed = $this->$method($line, $rowNumber, $userId);
                    
                    if ($processed['success']) {
                        $processed['data']['crop_year'] = $cropYear;
                        $processed['data']['week_no'] = $weekNo;
                        $batchData[] = $processed['data'];
                    } else {
                        $errors[] = $processed['error'];
                        Log::warning('CSV line error', ['line' => $rowNumber, 'error' => $processed['error']]);
                    }
                }
            }
            
            Log::info('CSV Processing complete', [
                'records_to_insert' => count($batchData),
                'errors' => count($errors)
            ]);
            
            $importedCount = 0;
            $liquidation = null;
            $fuelDeductions = 0;
            DB::transaction(function () use ($batchData, $model, $type, $cropYear, $weekNo, $userId, &$importedCount, &$errors, &$liquidation, &$fuelDeductions) {
                if ($type === 'fuel') {
                    // Row by row so each deduction links to its fuel_allowance row
                    $fuelDeductions = $this->importFuel($batchData, (string) $cropYear, (string) $weekNo, $userId, $importedCount);
                } elseif (!empty($batchData)) {
                    Log::info('Inserting into model', ['model' => $model, 'first_record' => $batchData[0]]);

                    foreach (array_chunk($batchData, 100) as $chunk) {
                        try {
                            $model::insert($chunk);
                            $importedCount += count($chunk);
                            Log::info('Chunk inserted', ['count' => count($chunk)]);
                        } catch (\Exception $e) {
                            Log::error('Bulk insert failed, trying individual records', ['error' => $e->getMessage()]);
                            foreach ($chunk as $record) {
                                try {
                                    $model::create($record);
                                    $importedCount++;
                                } catch (\Exception $e2) {
                                    Log::warning('Row insert failed', [
                                        'planter' => $record['planter_name'] ?? 'unknown',
                                        'error' => $e2->getMessage()
                                    ]);
                                    $errors[] = 'Skipped: ' . ($record['planter_name'] ?? 'unknown');
                                }
                            }
                        }
                    }
                }

                // TLS hauling rows become Hauling Liquidity entries in the same transaction
                if ($type === 'tls' && $importedCount > 0) {
                    $liquidation = app(TlsHaulingLiquidationGenerator::class)->generate((string) $cropYear, (string) $weekNo, $userId);
                }
            });

            AuditLog::log('upload', 'uploads', 'Uploaded ' . $type . ' CSV: ' . $importedCount . ' records imported', [
                'type' => $type,
                'file_name' => $file->getClientOriginalName(),
                'records_imported' => $importedCount,
                'records_skipped' => count($errors),
                'total_rows' => count($lines),
            ]);
            
            $message = "Successfully imported " . $importedCount . " records.";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " records skipped.";
            }

            if ($fuelDeductions > 0) {
                $message .= " Created {$fuelDeductions} Fuel deductions (Fuel & Others Deduction) for Week {$weekNo}.";
            }

            // New planters in the Consolidated upload get a Planter Profile (never a duplicate).
            // The upload is already saved, so a failed sync is reported but does not undo it.
            $planterSync = null;
            if ($type === 'consolidated' && $importedCount > 0) {
                try {
                    // Only the uploaded week is read; without a crop year / week the whole report is
                    $planterSync = app(PlanterProfileSync::class)->sync(
                        $cropYear ? (string) $cropYear : null,
                        $cropYear && $weekNo ? (string) $weekNo : null,
                    );
                    AuditLog::log('sync', 'planters', 'Synced planter profiles from consolidated upload', [
                        'crop_year' => $cropYear,
                        'week_no' => $weekNo,
                        'imported' => $planterSync['imported'],
                        'skipped' => $planterSync['skipped'],
                        'errors' => count($planterSync['errors']),
                    ]);
                    $message .= $planterSync['imported'] > 0
                        ? " Added {$planterSync['imported']} new planter(s) to Planter Profiles."
                        : ' No new planters to add to Planter Profiles.';
                } catch (\Throwable $e) {
                    Log::error('Planter profile sync after consolidated upload failed: ' . $e->getMessage());
                    $message .= ' Planter Profiles were not synced; use Sync Planters on Planter Profiles.';
                }
            }

            if ($liquidation) {
                AuditLog::log('create', 'hauling_liquidity', 'Generated ' . $liquidation['entries'] . ' hauling liquidation entries from TLS upload', [
                    'crop_year' => $cropYear,
                    'week_no' => $weekNo,
                    'haulers' => $liquidation['haulers'],
                ]);

                $message .= " Created {$liquidation['entries']} Hauler TLS LIQ entries for " . rtrim(implode(', ', $liquidation['haulers']), '.') . '.';
                if (!empty($liquidation['unassigned_plates'])) {
                    $message .= ' ' . count($liquidation['unassigned_plates']) . ' truck(s) need a payee picked on Edit (remembered for next week): '
                        . implode(', ', array_slice($liquidation['unassigned_plates'], 0, 8))
                        . (count($liquidation['unassigned_plates']) > 8 ? ', ...' : '') . '.';
                }
                if (!empty($liquidation['suspect_plates'])) {
                    $message .= ' Check plate no. ' . implode(', ', $liquidation['suspect_plates'])
                        . ' (Excel may have turned the plate into a date).';
                }
            }

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'imported' => $importedCount,
                    'errors' => count($errors),
                    'liquidations' => $liquidation['entries'] ?? null,
                    'fuel_deductions' => $type === 'fuel' ? $fuelDeductions : null,
                    'planters_added' => $planterSync['imported'] ?? null,
                ]);
            }
            
            return redirect()->back()->with('success', $message);
            
        } catch (\Exception $e) {
            Log::error('CSV Upload Error (' . $type . '): ' . $e->getMessage());
            
            AuditLog::log('error', 'uploads', 'CSV upload failed: ' . $type, [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName() ?? 'unknown',
            ]);
            
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to upload CSV: ' . $e->getMessage());
        }
    }

    /**
     * The first row is a header when it carries the template's column names (e.g. "planter_code"
     * or "Planter Code"), or when its planter code cell is text while the next row's is a number.
     */
    private function isHeaderRow(array $lines, string $type): bool
    {
        $columns = array_map([$this, 'normalizeHeading'], self::TEMPLATE_COLUMNS[$type] ?? []);
        $cells = array_map([$this, 'normalizeHeading'], $lines[0]);
        $nonEmpty = array_filter($cells, fn ($c) => $c !== '');

        if (empty($nonEmpty) || empty($columns)) {
            return false;
        }

        if (count(array_intersect($nonEmpty, $columns)) >= min(2, count($nonEmpty))) {
            return true;
        }

        $codeIndex = array_search('planter_code', $columns, true);
        if ($codeIndex === false) {
            return false;
        }
        $isCode = fn ($row) => is_numeric(trim((string) ($row[$codeIndex] ?? '')));
        $firstCode = trim((string) ($lines[0][$codeIndex] ?? ''));

        return $firstCode !== '' && !$isCode($lines[0]) && (!isset($lines[1]) || $isCode($lines[1]));
    }

    private function normalizeHeading($value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $value))), '_');
    }

    private function checkDuplicate($type, $record, $model)
    {
        switch ($type) {
            case 'quedan':
                return $model::where('crop_year', $record['crop_year'])
                    ->where('week_no', $record['week_no'])
                    ->where('planter_code', $record['planter_code'])
                    ->where('qdn_no', $record['qdn_no'])
                    ->exists();
            
            case 'molasses':
                return $model::where('crop_year', $record['crop_year'])
                    ->where('week_no', $record['week_no'])
                    ->where('planter_code', $record['planter_code'])
                    ->exists();
            
            case 'trucking':
                return $model::where('crop_year', $record['crop_year'])
                    ->where('week_no', $record['week_no'])
                    ->where('planter_code', $record['planter_code'])
                    ->where('trans_code', $record['trans_code'])
                    ->exists();
            
            case 'consolidated':
                return $model::where('crop_year', $record['crop_year'])
                    ->where('week_no', $record['week_no'])
                    ->where('planter_code', $record['planter_code'])
                    ->exists();
            
            case 'summary':
                return $model::where('crop_year', $record['crop_year'])
                    ->where('week_no', $record['week_no'])
                    ->where('planter_code', $record['planter_code'])
                    ->exists();
            
            default:
                // For fuel, rentals, underload, transloading, fci, mudpress
                return $model::where('crop_year', $record['crop_year'])
                    ->where('week_no', $record['week_no'])
                    ->where('planter_code', $record['planter_code'])
                    ->exists();
        }
    }
        
    private function processSummaryLine($line, $index, $userId)
    {
        if (count($line) < 6) {
            return ['success' => false, 'error' => "Line {$index}: Insufficient columns"];
        }
        
        return [
            'success' => true,
            'data' => [
                'crop_year' => (int) preg_replace('/[^0-9]/', '', trim($line[0])),
                'week_no' => (int) $line[1],
                'planter_code' => (int) $line[2],
                'planter_name' => trim($line[3]),
                'net_cane' => (float) str_replace(',', '', $line[4]),
                'net_amount' => (float) str_replace(',', '', $line[5]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }
    
    private function processTruckingLine($line, $index, $userId)
    {
        if (count($line) < 7) {
            return ['success' => false, 'error' => "Line {$index}: Expected 7 columns, got " . count($line)];
        }

        $planterName = trim($line[3]);
        $planterName = mb_convert_encoding($planterName, 'UTF-8', 'UTF-8');
        $planterName = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $planterName);

        return [
            'success' => true,
            'data' => [
                'crop_year' => trim($line[0]),
                'week_no' => trim($line[1]),
                'planter_code' => trim($line[2]),
                'planter_name' => $planterName,
                'net_cane' => (float) str_replace(',', '', trim($line[4])),
                'ta_amount' => (float) str_replace(',', '', trim($line[5])),
                'trans_code' => trim($line[6]),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }
    
    /**
     * crop_year, week_no, planter_code, planter_name, liters, fuel_amount.
     * Older 5-column files (no liters) are still read: the 5th column is then the amount.
     */
    private function processFuelLine($line, $index, $userId)
    {
        if (count($line) < 5) {
            return ['success' => false, 'error' => "Line {$index}: Insufficient columns"];
        }

        $number = fn ($value) => str_replace(',', '', trim((string) $value));
        $hasLiters = count($line) >= 6 && trim((string) $line[5]) !== '';
        $liters = $hasLiters ? $number($line[4]) : '';
        $amount = $number($hasLiters ? $line[5] : $line[4]);
        // Numeric codes are stored without leading zeros (00733 → 733), like the quedan, molasses and
        // planter profile codes, so the deduction reaches the planter's voucher
        $planterCode = trim((string) $line[2]);
        if (ctype_digit($planterCode)) {
            $planterCode = ltrim($planterCode, '0') ?: '0';
        }

        if ($planterCode === '') {
            return ['success' => false, 'error' => "Line {$index}: Missing planter_code"];
        }
        if (!is_numeric($amount)) {
            return ['success' => false, 'error' => "Line {$index}: fuel_amount is not a number"];
        }
        if ($liters !== '' && !is_numeric($liters)) {
            return ['success' => false, 'error' => "Line {$index}: liters is not a number"];
        }

        return [
            'success' => true,
            'data' => [
                'crop_year' => trim($line[0]),
                'week_no' => trim($line[1]),
                'planter_code' => $planterCode,
                'planter_name' => trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $line[3])),
                'liters' => $liters === '' ? null : round((float) $liters, 3),
                'fuel_amount' => round((float) $amount, 2),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }

    /**
     * Rows whose crop_year / week_no cells are filled in but differ from the selected crop year
     * and week (blank cells take the selected ones). Crop years compare by digits, so 2026-2027 = 20262027.
     */
    private function cropWeekConflicts(array $lines, string $cropYear, string $weekNo, int $firstRow): array
    {
        $digits = fn ($v) => preg_replace('/[^0-9]/', '', (string) $v);
        $conflicts = [];
        foreach ($lines as $i => $line) {
            if (empty(array_filter($line))) {
                continue;
            }
            $rowCrop = trim((string) ($line[0] ?? ''));
            $rowWeek = trim((string) ($line[1] ?? ''));
            $badCrop = $rowCrop !== '' && $digits($rowCrop) !== $digits($cropYear);
            $badWeek = $rowWeek !== '' && (!is_numeric($rowWeek) || (int) $rowWeek !== (int) $weekNo);
            if ($badCrop || $badWeek) {
                $conflicts[] = 'line ' . ($i + $firstRow) . ' (' . $rowCrop . ' / Wk ' . $rowWeek . ')';
            }
        }
        return $conflicts;
    }

    /**
     * Insert the fuel rows and create one Fuel deduction per row with an amount, each with its own
     * reference no., deducted in the uploaded week (Fuel & Others Deduction → Check Voucher FUEL
     * PAYMENT). Call inside a transaction.
     */
    private function importFuel(array $rows, string $cropYear, string $weekNo, $userId, int &$importedCount): int
    {
        if (empty($rows)) {
            return 0;
        }

        $dueDate = WeekNo::where('crop_year', $cropYear)->where('week_no', $weekNo)->value('week_end_date');
        $dueDate = $dueDate ? substr((string) $dueDate, 0, 10) : null;
        $withAmount = count(array_filter($rows, fn ($r) => $r['fuel_amount'] > 0));
        $references = $withAmount ? PlanterDeduction::generateReferenceNos('fuel', $withAmount) : [];
        $created = 0;

        foreach ($rows as $row) {
            $fuel = FuelAllowance::create($row);
            $importedCount++;

            if ($row['fuel_amount'] <= 0) {
                continue;
            }

            $liters = $row['liters'] !== null ? rtrim(rtrim(number_format($row['liters'], 3), '0'), '.') . ' L' : null;
            $deduction = PlanterDeduction::create([
                'reference_no' => $references[$created],
                'type' => 'fuel',
                'source' => 'upload',
                'fuel_allowance_id' => $fuel->id,
                'planter_code' => $row['planter_code'],
                'planter_name' => $row['planter_name'],
                'crop_year' => $cropYear,
                'amount' => $row['fuel_amount'],
                'liters' => $row['liters'],
                'description' => 'Fuel upload Wk ' . $weekNo . ($liters ? ' · ' . $liters : ''),
                'created_by' => $userId,
            ]);
            $deduction->weeks()->create([
                'crop_year' => $cropYear,
                'week_no' => $weekNo,
                'due_date' => $dueDate,
                'amount' => $row['fuel_amount'],
            ]);
            $created++;
        }

        return $created;
    }

    private function processFciLine($line, $index, $userId)
    {
        if (count($line) < 5) {
            return ['success' => false, 'error' => "Line {$index}: Insufficient columns"];
        }

        return [
            'success' => true,
            'data' => [
                'crop_year' => trim($line[0]),
                'week_no' => trim($line[1]),
                'planter_code' => trim($line[2]),
                'planter_name' => trim($line[3]),
                'fci_amount' => (float) str_replace(',', '', trim($line[4])),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }
    
    private function processRentalLine($line, $index, $userId)
    {
        if (count($line) < 5) {
            return ['success' => false, 'error' => "Line {$index}: Insufficient columns"];
        }
        
        return [
            'success' => true,
            'data' => [
                'crop_year' => (int) preg_replace('/[^0-9]/', '', trim($line[0])),
                'week_no' => (int) $line[1],
                'planter_code' => (int) $line[2],
                'planter_name' => trim($line[3]),
                'rental_amount' => (float) str_replace(',', '', $line[4]),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }
    
    private function processUnderloadLine($line, $index, $userId)
    {
        if (count($line) < 5) {
            return ['success' => false, 'error' => "Line {$index}: Insufficient columns"];
        }
        
        return [
            'success' => true,
            'data' => [
                'crop_year' => (int) preg_replace('/[^0-9]/', '', trim($line[0])),
                'week_no' => (int) $line[1],
                'planter_code' => (int) $line[2],
                'planter_name' => trim($line[3]),
                'underload_amount' => (float) str_replace(',', '', $line[4]),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }
    
    private function processTransloadingLine($line, $index, $userId)
    {
        if (count($line) < 5) {
            return ['success' => false, 'error' => "Line {$index}: Insufficient columns"];
        }
        
        return [
            'success' => true,
            'data' => [
                'crop_year' => (int) preg_replace('/[^0-9]/', '', trim($line[0])),
                'week_no' => (int) $line[1],
                'planter_code' => (int) $line[2],
                'planter_name' => trim($line[3]),
                'transloading_amount' => (float) str_replace(',', '', $line[4]),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }

    private function processMudpressLine($line, $index, $userId)
    {
        if (count($line) < 7) {
            return ['success' => false, 'error' => "Line {$index}: Insufficient columns. Expected 7 columns."];
        }

        return [
            'success' => true,
            'data' => [
                'crop_year' => trim($line[0]),
                'week_no' => trim($line[1]),
                'planter_code' => trim($line[2]),
                'planter_name' => trim($line[3]),
                'trans_code' => trim($line[4]),
                'charge_code' => trim($line[5]),
                'mpress' => (float) str_replace(',', '', $line[6]),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }
    
    private function getModel($type)
    {
        $models = [
            'summary' => \App\Models\Summary::class,
            'trucking' => \App\Models\TruckingAllowance::class,
            'fuel' => \App\Models\FuelAllowance::class,
            'rentals' => \App\Models\RentalAllowance::class,
            'underload' => \App\Models\UnderloadAllowance::class,
            'transloading' => \App\Models\TransloadingAllowance::class,
            'fci' => \App\Models\FreshCaneIncentive::class,
            'mudpress' => \App\Models\Mudpress::class,
            'consolidated' => \App\Models\ConsolidatedUpload::class,
            'quedan' => \App\Models\Quedan::class,
            'molasses' => \App\Models\Molass::class,
            'tls' => \App\Models\TlsHaulingUpload::class,
        ];
        
        if (!isset($models[$type])) {
            throw new \InvalidArgumentException("Invalid upload type: {$type}");
        }
        
        return $models[$type];
    }

    private function processConsolidatedLine($line, $index, $userId)
{
    if (count($line) < 21) {
        return ['success' => false, 'error' => "Line {$index}: Expected 21 columns, got " . count($line)];
    }

    $planterName = trim($line[2]);
    $assnName = trim($line[3]);
    $planterName = mb_convert_encoding($planterName, 'UTF-8', 'UTF-8');
    $planterName = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $planterName);
    $assnName = mb_convert_encoding($assnName, 'UTF-8', 'UTF-8');
    $assnName = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $assnName);

    $taAmount = (float) str_replace(',', '', trim($line[5]));
    $cciFaAmt = (float) str_replace(',', '', trim($line[11]));
    $cciFbAmt = (float) str_replace(',', '', trim($line[13]));
    $cciFcAmt = (float) str_replace(',', '', trim($line[15]));
    $mudpressAmt = (float) str_replace(',', '', trim($line[19]));
    $fuelAmt = (float) str_replace(',', '', trim($line[16]));
    $rentalAmt = (float) str_replace(',', '', trim($line[17]));
    $underloadAmt = (float) str_replace(',', '', trim($line[18]));

    // Compute total_summary
    $totalSummary = $taAmount + $cciFaAmt + $cciFbAmt + $cciFcAmt + $mudpressAmt - $fuelAmt - $rentalAmt - $underloadAmt;

    return [
        'success' => true,
        'data' => [
            'crop_year' => request()->get('crop_year'), 
            'week_no' => request()->get('week_no'),    
            'planter_code' => trim($line[0]),
            'assn_code' => trim($line[1]),
            'planter_name' => $planterName,
            'assn_name' => $assnName,
            'ta_wt' => (float) str_replace(',', '', trim($line[4])),
            'ta_amount' => $taAmount,
            'emi_wt' => (float) str_replace(',', '', trim($line[6])),
            'emi_amount' => (float) str_replace(',', '', trim($line[7])),
            'pat_wt' => (float) str_replace(',', '', trim($line[8])),
            'pat_amount' => (float) str_replace(',', '', trim($line[9])),
            'cci_fa_wt' => (float) str_replace(',', '', trim($line[10])),
            'cci_fa_amt' => $cciFaAmt,
            'cci_fb_wt' => (float) str_replace(',', '', trim($line[12])),
            'cci_fb_amt' => $cciFbAmt,
            'cci_fc_wt' => (float) str_replace(',', '', trim($line[14])),
            'cci_fc_amt' => $cciFcAmt,
            'fuel_issuance_amt' => $fuelAmt,
            'rental_amt' => $rentalAmt,
            'underload_amt' => $underloadAmt,
            'mudpress_amt' => $mudpressAmt,
            'adj_amt' => (float) str_replace(',', '', trim($line[20])),
            'total_summary' => $totalSummary,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    ];
}


private function processQuedanLine($line, $index, $userId)
{
    if (count($line) < 9) {
        return ['success' => false, 'error' => "Line {$index}: Expected 9 columns, got " . count($line)];
    }

    $planterName = trim($line[3]);
    $planterName = mb_convert_encoding($planterName, 'UTF-8', 'UTF-8');
    $planterName = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $planterName);

    return [
        'success' => true,
        'data' => [
            'crop_year' => trim($line[0]),
            'week_no' => trim($line[1]),
            'planter_code' => trim($line[2]),
            'planter_name' => $planterName,
            'qdn_no' => trim($line[4]),
            'tin_no' => trim($line[5]),
            'total_liens' => (float) str_replace(',', '', trim($line[6])),
            'sugar_lkg' => (float) str_replace(',', '', trim($line[7])),
            'labor_lkg' => (float) str_replace(',', '', trim($line[8])),
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    ];
}

private function processMolassesLine($line, $index, $userId)
{
    if (count($line) < 7) {
        return ['success' => false, 'error' => "Line {$index}: Expected 7 columns, got " . count($line)];
    }

    $planterName = trim($line[3]);
    $planterName = mb_convert_encoding($planterName, 'UTF-8', 'UTF-8');
    $planterName = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $planterName);

    return [
        'success' => true,
        'data' => [
            'crop_year' => trim($line[0]),
            'week_no' => trim($line[1]),
            'planter_code' => trim($line[2]),
            'planter_name' => $planterName,
            'tin_no' => trim($line[4]),
            'mc_no' => trim($line[5]),
            'mol_net' => (float) str_replace(',', '', trim($line[6])),
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    ];
}

private function processTlsLine($line, $index, $userId)
{
    if (count($line) < 14) {
        return ['success' => false, 'error' => "Line {$index}: Expected 14 columns, got " . count($line)];
    }

    $clean = fn ($value) => trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value)));
    // Weights edited in Excel can turn into dates ("Jan-00"); keep those as 0 instead of failing the row
    $number = fn ($value) => is_numeric($v = str_replace(',', '', trim((string) $value))) ? (float) $v : 0.0;

    $planterCode = trim($line[1]);
    $netCane = str_replace(',', '', trim($line[9]));

    if ($planterCode === '') {
        return ['success' => false, 'error' => "Line {$index}: Missing planter_code"];
    }
    if (!is_numeric($netCane)) {
        return ['success' => false, 'error' => "Line {$index}: net_cane_wt is not a number"];
    }

    return [
        'success' => true,
        'data' => [
            'reference_no' => trim($line[0]),
            'planter_code' => $planterCode,
            'planter_name' => $clean($line[2]),
            'assn_code' => trim($line[3]),
            'assn_name' => $clean($line[4]),
            'vehicle_type' => trim($line[5]),
            'plate_no' => strtoupper($clean($line[6])),
            'tare_weight' => $number($line[7]),
            'gross_cane_weight' => $number($line[8]),
            'net_cane_wt' => (float) $netCane,
            'date_milled' => trim($line[10]),
            'trans_code' => trim($line[11]),
            'description' => $clean($line[12]),
            'tdif_rate' => $number($line[13]),
            'hauling_liquidation_id' => null,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    ];
}
}
