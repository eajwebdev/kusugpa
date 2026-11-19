<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A planter's voucher in a generated group, with the person authorized to receive it (if any)
 */
class VoucherBatchItem extends Model
{
    use \App\Models\Concerns\NormalizesPlanterCode;

    protected $fillable = [
        'voucher_batch_id', 'crop_year', 'week_from', 'week_to',
        'planter_code', 'planter_name', 'net_paid', 'receiver_name', 'bought_week',
        'voucher_key', 'quedan_week', 'qdn_no', 'quedan_count', 'molasses_count',
    ];

    protected $casts = [
        'voucher_batch_id' => 'integer',
        'week_from' => 'integer',
        'week_to' => 'integer',
        'bought_week' => 'integer',
        'quedan_week' => 'integer',
        'quedan_count' => 'integer',
        'molasses_count' => 'integer',
        'net_paid' => 'float',
    ];

    public function batch()
    {
        return $this->belongsTo(VoucherBatch::class, 'voucher_batch_id');
    }
}
