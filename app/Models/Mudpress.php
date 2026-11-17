<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mudpress extends Model
{
    use \App\Models\Concerns\NormalizesPlanterCode;

    use HasFactory;

    protected $table = 'mudpress';

    protected $fillable = [
        'crop_year',
        'week_no',
        'planter_code',
        'planter_name',
        'trans_code',
        'charge_code',
        'mpress',
        'user_id',
    ];
}