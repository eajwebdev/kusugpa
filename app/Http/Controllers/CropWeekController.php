<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CropYear;
use App\Models\WeekNo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CropWeekController extends Controller
{
    /**
     * Display the crop year and week management page
     */
    public function index()
{
    $cropYears = CropYear::orderBy('crop_year', 'desc')->get();
    $weeks = WeekNo::orderBy('crop_year', 'desc')
    ->orderByRaw('CAST(week_no AS UNSIGNED) ASC')  // Sort numerically
    ->get();
    
    // Prepare data for JavaScript
    $cropYearsData = $cropYears->map(function($c) {
        return [
            'id' => $c->id,
            'crop_year' => $c->crop_year,
            'weeks_count' => WeekNo::where('crop_year', $c->crop_year)->count() // Fix: count weeks directly
        ];
    });
    
    $weeksData = $weeks->map(function($w) {
        return [
            'id' => $w->id,
            'crop_year' => $w->crop_year,
            'week_no' => $w->week_no,
            'week_start_date' => $w->week_start_date, // Keep original string format
            'week_end_date' => $w->week_end_date, // Keep original string format
        ];
    });
    
    return view('crop-weeks.index', compact('cropYears', 'weeks', 'cropYearsData', 'weeksData'));
}

    /**
     * Update crop year — always returns JSON so fetch().json() never receives HTML.
     */
    public function updateCropYear(Request $request, $cropYear)
    {
        $model = $cropYear instanceof CropYear ? $cropYear : CropYear::find($request->route('cropYear'));

        if (!$model) {
            return response()->json(['message' => 'Crop year not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'crop_year' => 'required|string|size:8|unique:crop_year,crop_year,' . $model->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first('crop_year'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $model->update(['crop_year' => $request->crop_year]);

            $username = optional(auth()->user())->username ?? 'system';
            Log::info('Crop Year updated: ' . $model->crop_year . ' by user: ' . $username);

            return response()->json(['message' => 'Crop Year updated successfully']);
        } catch (\Throwable $e) {
            Log::error('Crop Year update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Server error while updating crop year.'], 500);
        }
    }

    /**
     * Delete crop year — always returns JSON.
     */
    public function destroyCropYear(Request $request, $cropYear)
    {
        $model = $cropYear instanceof CropYear ? $cropYear : CropYear::find($request->route('cropYear'));

        if (!$model) {
            return response()->json(['message' => 'Crop year not found.'], 404);
        }

        try {
            // Check if crop year has weeks
            $weekCount = WeekNo::where('crop_year', $model->crop_year)->count();

            if ($weekCount > 0) {
                return response()->json([
                    'message' => 'Cannot delete crop year with ' . $weekCount . ' associated week(s). Delete the weeks first.'
                ], 400);
            }

            $deleted = $model->crop_year;
            $model->delete();

            $username = optional(auth()->user())->username ?? 'system';
            Log::info('Crop Year deleted: ' . $deleted . ' by user: ' . $username);

            return response()->json(['message' => 'Crop Year deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('Crop Year delete failed: ' . $e->getMessage());
            return response()->json(['message' => 'Server error while deleting crop year.'], 500);
        }
    }

    /**
     * Update week number — always returns JSON.
     */
    public function updateWeek(Request $request, $weekNo)
    {
        $model = $weekNo instanceof WeekNo ? $weekNo : WeekNo::find($request->route('weekNo'));

        if (!$model) {
            return response()->json(['message' => 'Week not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'crop_year' => 'required|string|size:8',
            'week_no' => 'required|integer|min:1|max:52',
            'week_start_date' => 'required|date',
            'week_end_date' => 'required|date|after:week_start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $model->update([
                'crop_year' => $request->crop_year,
                'week_no' => $request->week_no,
                'week_start_date' => $request->week_start_date,
                'week_end_date' => $request->week_end_date,
            ]);

            $username = optional(auth()->user())->username ?? 'system';
            Log::info('Week updated: ' . $model->id . ' by user: ' . $username);

            return response()->json(['message' => 'Week updated successfully']);
        } catch (\Throwable $e) {
            Log::error('Week update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Server error while updating week.'], 500);
        }
    }

    /**
     * Delete week number — always returns JSON.
     */
    public function destroyWeek(Request $request, $weekNo)
    {
        $model = $weekNo instanceof WeekNo ? $weekNo : WeekNo::find($request->route('weekNo'));

        if (!$model) {
            return response()->json(['message' => 'Week not found.'], 404);
        }

        try {
            $id = $model->id;
            $model->delete();

            $username = optional(auth()->user())->username ?? 'system';
            Log::info('Week deleted: ' . $id . ' by user: ' . $username);

            return response()->json(['message' => 'Week deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('Week delete failed: ' . $e->getMessage());
            return response()->json(['message' => 'Server error while deleting week.'], 500);
        }
    }
}
