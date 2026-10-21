<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TlsHaulingUpload extends Model
{
    use HasFactory;

    protected $table = 'tls_hauling_uploads';

    protected $fillable = [
        'crop_year', 'week_no', 'reference_no', 'planter_code', 'planter_name',
        'assn_code', 'assn_name', 'vehicle_type', 'plate_no', 'tare_weight',
        'gross_cane_weight', 'net_cane_wt', 'date_milled', 'trans_code',
        'description', 'tdif_rate', 'hauling_liquidation_id', 'user_id',
    ];

    public function liquidation()
    {
        return $this->belongsTo(HaulingLiquidation::class, 'hauling_liquidation_id');
    }

    /** "T 0003623351", as the mill prints the Hda ticket */
    public function getTicketNoAttribute(): string
    {
        return trim($this->vehicle_type . ' ' . str_pad((string) $this->reference_no, 10, '0', STR_PAD_LEFT));
    }

    /** Full dates print as m/d/Y H:i:s; values Excel cut to "mm:ss.0" print as they are */
    public function getDateMilledLabelAttribute(): string
    {
        $value = trim((string) $this->date_milled);
        if (preg_match('#\d{1,4}[/-]\d{1,2}[/-]\d{1,4}#', $value)) {
            try {
                return \Illuminate\Support\Carbon::parse($value)->format('m/d/Y H:i:s');
            } catch (\Exception $e) {
            }
        }

        return $value;
    }
}
