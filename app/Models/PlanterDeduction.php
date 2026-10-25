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
        'reference_no', 'type', 'planter_code', 'planter_name', 'crop_year',
        'amount', 'description', 'created_by',
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
        $prefix = ($type === 'fuel' ? 'FD' : 'OD') . date('Y');
        $last = self::where('reference_no', 'like', $prefix . '%')->orderByDesc('reference_no')->value('reference_no');
        $sequence = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
