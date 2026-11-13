<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One Generate on Print Vouchers: a crop year and week range, stamped with the date and time,
 * holding the planters whose vouchers it generated
 */
class VoucherBatch extends Model
{
    protected $fillable = ['crop_year', 'week_from', 'week_to', 'user_id', 'generated_by'];

    protected $casts = [
        'week_from' => 'integer',
        'week_to' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(VoucherBatchItem::class);
    }
}
