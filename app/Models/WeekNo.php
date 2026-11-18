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
     * [start, end] dates of a crop year week, or null when the week has no dates
     */
    public static function dateRange($cropYear, $weekNo): ?array
    {
        $week = static::where('crop_year', $cropYear)->where('week_no', (string) $weekNo)->first();

        return $week && $week->week_start_date && $week->week_end_date
            ? [$week->week_start_date, $week->week_end_date]
            : null;
    }

    /**
     * Weeks for a "Week Bought" dropdown, newest first: the crop year's weeks that have started,
     * plus, for quedans, any week with a quedan price (a quedan's bought week is its price's week).
     * Shared by the Registry / Buy pages and the Proceeds & Deductions report.
     */
    public static function boughtWeekOptions(?string $cropYear, bool $withPriceWeeks = false, string $priceModel = QuedanPrice::class)
    {
        if (!$cropYear) {
            return collect();
        }

        $weeks = static::where('crop_year', $cropYear)
            ->get(['week_no', 'week_start_date', 'week_end_date'])
            ->keyBy(fn ($w) => (string) $w->week_no);

        $weekNos = $weeks->filter(fn ($w) => $w->week_start_date && \Carbon\Carbon::parse($w->week_start_date)->lte(now()))->keys();
        if ($withPriceWeeks) {
            $weekNos = $weekNos->merge($priceModel::where('crop_year', $cropYear)->distinct()->pluck('week_no')->map(fn ($w) => (string) (int) $w));
        }

        return $weekNos->unique()
            ->sortByDesc(fn ($w) => (int) $w)
            ->map(function ($weekNo) use ($weeks) {
                $w = $weeks->get($weekNo);
                $dates = $w && $w->week_start_date && $w->week_end_date
                    ? ' (' . \Carbon\Carbon::parse($w->week_start_date)->format('M j') . ' – ' . \Carbon\Carbon::parse($w->week_end_date)->format('M j') . ')'
                    : '';

                return ['week_no' => (string) $weekNo, 'label' => 'Week ' . $weekNo . $dates];
            })
            ->values();
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