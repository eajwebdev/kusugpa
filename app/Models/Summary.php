<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    use \App\Models\Concerns\NormalizesPlanterCode;

    use HasFactory;

    protected $table = 'summary';

        protected $fillable = [
        'crop_year',
        'week_no',
        'planter_code',
        'planter_name',
        'net_cane',
        'net_amount',

    ];
}
