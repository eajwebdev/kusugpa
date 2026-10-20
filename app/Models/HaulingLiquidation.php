<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HaulingLiquidation extends Model
{
    use HasFactory;

    protected $table = 'hauling_liquidations';

    protected $fillable = [
        'hauler_id', 'crop_year', 'week_no', 'week_ending', 'date_released',
        'planter_code', 'payee_name', 'cv_no', 'check_no', 'truck_code', 'tonnage', 'rate_per_ton',
        'gross_amount', 'total_add', 'total_less', 'net_amount', 'created_by',
    ];

    protected $casts = [
        'week_ending' => 'date:Y-m-d',
        'date_released' => 'date:Y-m-d',
    ];

    public function hauler()
    {
        return $this->belongsTo(Hauler::class);
    }

    public function planter()
    {
        return $this->belongsTo(PlanterProfile::class, 'planter_code', 'planter_code');
    }

    public function items()
    {
        return $this->hasMany(HaulingLiquidationItem::class)->orderBy('sort_order');
    }

    public function addItems()
    {
        return $this->items()->where('type', 'add');
    }

    public function lessItems()
    {
        return $this->items()->where('type', 'less');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * "20262027" -> "2026-2027"
     */
    public function getCropYearLabelAttribute(): string
    {
        $cy = (string) $this->crop_year;
        return preg_match('/^\d{8}$/', $cy) ? substr($cy, 0, 4) . '-' . substr($cy, 4) : $cy;
    }
}
