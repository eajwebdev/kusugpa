<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A fuel or other-account deduction of a planter, repaid on a weekly schedule like a cash advance
 * (no approval). Each scheduled week comes off that week's Check Voucher.
 */
class PlanterDeduction extends Model
{
    public const TYPES = [
        'fuel' => 'Fuel',
        'others' => 'Others',
    ];

    protected $table = 'planter_deductions';

    protected $fillable = [
        'reference_no', 'type', 'source', 'fuel_allowance_id', 'planter_code', 'planter_name', 'crop_year',
        'amount', 'liters', 'description', 'created_by',
    ];

    public function weeks()
    {
        return $this->hasMany(PlanterDeductionWeek::class)->orderByRaw('CAST(week_no AS UNSIGNED) ASC');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** FD20260001 for fuel, OD20260001 for others */
    public static function generateReferenceNo(string $type): string
    {
        return self::generateReferenceNos($type, 1)[0];
    }

    /**
     * $count consecutive reference numbers (bulk fuel upload). The sequence is read from the
     * highest number so far, ordered numerically so FD20260001 → FD202610000 keeps counting.
     */
    public static function generateReferenceNos(string $type, int $count): array
    {
        $prefix = ($type === 'fuel' ? 'FD' : 'OD') . date('Y');
        $last = self::where('reference_no', 'like', $prefix . '%')
            ->pluck('reference_no')
            ->map(fn ($ref) => (int) substr($ref, strlen($prefix)))
            ->max() ?? 0;

        return array_map(
            fn ($i) => $prefix . str_pad((string) ($last + $i), 4, '0', STR_PAD_LEFT),
            range(1, max(1, $count))
        );
    }
}
