<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hauler extends Model
{
    use HasFactory;

    protected $table = 'haulers';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function liquidations()
    {
        return $this->hasMany(HaulingLiquidation::class);
    }
}
