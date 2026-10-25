<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CropYear;
use App\Models\WeekNo;
use App\Models\QuedanPrice;
use App\Models\MolassesPrice;
use App\Models\AuditLog;
use Carbon\Carbon;

class UpdatesController extends Controller
{
    public function addCropYear(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string|size:8|unique:crop_year,crop_year',
            'user_id' => 'required|exists:users,id',
        ]);

        $cropYear = CropYear::create($data);

        AuditLog::log('create', 'crop_year', 'Added crop year: ' . $cropYear->crop_year);

        return response()->json(['message' => 'Crop Year added successfully']);
    }

    public function addWeekNumber(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_no' => 'required|string',
            'week_start_date' => 'required|date_format:Y-m-d H:i:s',
            'week_end_date' => 'required|date_format:Y-m-d H:i:s|after:week_start_date',
            'user_id' => 'required|exists:users,id',
        ]);

        $startDateTime = Carbon::parse($data['week_start_date']);
        $endDateTime = Carbon::parse($data['week_end_date']);

        $week = WeekNo::create([
            'crop_year' => $data['crop_year'],
            'week_no' => $data['week_no'],
            'week_start_date' => $startDateTime,
            'week_end_date' => $endDateTime,
            'user_id' => $data['user_id'],
        ]);

        AuditLog::log('create', 'week', 'Added week ' . $week->week_no . ' for crop year ' . $week->crop_year, [
            'start' => $startDateTime->format('Y-m-d H:i:s'),
            'end' => $endDateTime->format('Y-m-d H:i:s'),
        ]);

        return response()->json(['message' => 'Week Number added successfully']);
    }

    public function getWeeksByCropYear(Request $request)
{
    $cropYear = $request->get('crop_year');
    
    if (!$cropYear) {
        return response()->json(['weeks' => []]);
    }
    
    $weeks = WeekNo::where('crop_year', $cropYear)
        ->select('week_no')
        ->orderBy('week_no')
        ->get();
    
    return response()->json(['weeks' => $weeks]);
}

    public function addQuedanPrice(Request $request)
    {
        $data = $request->validate([
            'quedan_type' => 'required|string',
            'quedan_price' => 'required|numeric',
            'crop_year' => 'required|string',
            'week_no' => 'required|string',
            'user_id' => 'required|exists:users,id',
        ]);

        // "b" and "B" are the same type; a week keeps one price per type (saving again replaces it)
        $data['quedan_type'] = strtoupper(trim($data['quedan_type']));
        $price = QuedanPrice::updateOrCreate(
            ['crop_year' => $data['crop_year'], 'week_no' => $data['week_no'], 'quedan_type' => $data['quedan_type']],
            $data
        );

        AuditLog::log($price->wasRecentlyCreated ? 'create' : 'update', 'quedan_price', 'Set quedan price: ₱' . number_format($price->quedan_price, 2) . ' (Type: ' . $price->quedan_type . ')', [
            'type' => $price->quedan_type,
            'price' => $price->quedan_price,
            'crop_year' => $price->crop_year,
            'week_no' => $price->week_no,
        ]);

        return response()->json(['message' => 'Quedan Price added successfully']);
    }

    public function addMolassesPrice(Request $request)
    {
        $data = $request->validate([
            'mol_price' => 'required|numeric',
            'crop_year' => 'required|string',
            'week_no' => 'required|string',
            'user_id' => 'required|exists:users,id',
        ]);

        // A week keeps one molasses price (saving again replaces it)
        $price = MolassesPrice::updateOrCreate(
            ['crop_year' => $data['crop_year'], 'week_no' => $data['week_no']],
            $data
        );

        AuditLog::log($price->wasRecentlyCreated ? 'create' : 'update', 'molasses_price', 'Set molasses price: ₱' . number_format($price->mol_price, 2), [
            'price' => $price->mol_price,
            'crop_year' => $price->crop_year,
            'week_no' => $price->week_no,
        ]);

        return response()->json(['message' => 'Molasses Price added successfully']);
    }
}
