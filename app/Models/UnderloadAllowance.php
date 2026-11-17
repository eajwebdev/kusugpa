<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnderloadAllowance extends Model
{
    use \App\Models\Concerns\NormalizesPlanterCode;

    use HasFactory;

    protected $table = 'underload_allowance';

    protected $fillable = [
        'crop_year',
        'week_no',
        'planter_code',
        'planter_name',
        'underload_amount',
        'user_id',
    ];
}