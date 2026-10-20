<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HaulingLiquidationItem extends Model
{
    use HasFactory;

    protected $table = 'hauling_liquidation_items';

    protected $fillable = [
        'hauling_liquidation_id',
        'type',
        'description',
        'amount',
        'sort_order',
    ];

    public function liquidation()
    {
        return $this->belongsTo(HaulingLiquidation::class, 'hauling_liquidation_id');
    }
}
