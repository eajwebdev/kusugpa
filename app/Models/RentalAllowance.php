<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalAllowance extends Model
{
    use \App\Models\Concerns\NormalizesPlanterCode;

    use HasFactory;

    protected $table = 'rental_allowance';

    protected $fillable = [
        'crop_year',
        'week_no',
        'planter_code',
        'planter_name',
        'rental_amount',
        'user_id',
    ];
}