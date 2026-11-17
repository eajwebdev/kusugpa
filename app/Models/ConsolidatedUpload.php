<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsolidatedUpload extends Model
{
    use \App\Models\Concerns\NormalizesPlanterCode;

    use HasFactory;

    protected $table = 'consolidated_uploads';

    protected $fillable = [
    'crop_year', 'week_no','planter_code', 'assn_code', 'planter_name', 'assn_name',
    'ta_wt', 'ta_amount', 'emi_wt', 'emi_amount',
    'pat_wt', 'pat_amount', 'cci_fa_wt', 'cci_fa_amt',
    'cci_fb_wt', 'cci_fb_amt', 'cci_fc_wt', 'cci_fc_amt',
    'fuel_issuance_amt', 'rental_amt', 'underload_amt',
    'mudpress_amt', 'adj_amt', 'total_summary', 'user_id',
];
}
