<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeekNo extends Model
{
    use HasFactory;

    protected $table = 'week_no';

    protected $fillable = [
        'crop_year',
        'week_no',
        'week_start_date',
        'week_end_date',
        'user_id',
    ];

  
    
    // Add accessors to format the date for the view
    public function getWeekStartDateAttribute($value)
    {
        return $value;
    }
    
    public function getWeekEndDateAttribute($value)
    {
        return $value;
    }

    /**
     * Every crop year's weeks in order, for report pages that list only the selected crop year's weeks
     *
     * @return \Illuminate\Support\Collection<int, array{crop_year: string, week_no: string}>
     */
    public static function byCropYear()
    {
        return static::orderByRaw('CAST(week_no AS UNSIGNED) ASC')
            ->get(['crop_year', 'week_no'])
            ->map(fn ($w) => ['crop_year' => (string) $w->crop_year, 'week_no' => (string) $w->week_no])
            ->unique(fn ($w) => $w['crop_year'] . '|' . $w['week_no'])
            ->values();
    }
}