<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreshCaneIncentive extends Model
{
    use HasFactory;

    protected $fillable = [
        'crop_year',
        'week_no',
        'planter_code',
        'planter_name',
        'fci_amount',
        'user_id',
    ];
}
