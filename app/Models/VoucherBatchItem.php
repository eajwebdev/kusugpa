<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A planter's voucher in a generated group, with the person authorized to receive it (if any)
 */
class VoucherBatchItem extends Model
{
    protected $fillable = [
        'voucher_batch_id', 'crop_year', 'week_from', 'week_to',
        'planter_code', 'planter_name', 'net_paid', 'receiver_name',
    ];

    protected $casts = [
        'voucher_batch_id' => 'integer',
        'week_from' => 'integer',
        'week_to' => 'integer',
        'net_paid' => 'float',
    ];

    public function batch()
    {
        return $this->belongsTo(VoucherBatch::class, 'voucher_batch_id');
    }
}
