<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelAllowance extends Model
{
    use HasFactory;

    protected $table = 'fuel_allowance';

    protected $fillable = [
        'crop_year',
        'week_no',
        'planter_code',
        'planter_name',
        'liters',
        'fuel_amount',
        'user_id',
    ];
}