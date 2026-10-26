<?php

namespace App\Http\Controllers;

use App\Models\ConsolidatedUpload;
use App\Models\CropYear;
use App\Services\DashboardAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Data of the dashboard tabs, loaded when a tab is opened (and again when the crop year / week changes)
 */
class DashboardAnalyticsController extends Controller
{
    public function tab(Request $request, string $tab)
    {
        $data = $request->validate([
            'crop_year' => 'nullable|string|max:20',
            'week_no' => 'nullable|integer|min:1|max:99',
        ]);

        $access = DashboardAnalytics::accessFor($request->user());
        $allowed = [
            'modules' => true,
            'production' => true,
            'balance' => true,
            'finance' => $access['finance'],
            'collections' => $access['finance'],
            'proceeds' => $access['proceeds'],
            'hauling' => $access['hauling'],
        ];
        if (empty($allowed[$tab])) {
            return response()->json(['message' => 'You do not have permission to view this tab.'], 403);
        }

        $cropYear = $data['crop_year'] ?? null;
        if (!$cropYear) {
            $cropYear = ConsolidatedUpload::orderBy('crop_year', 'desc')->value('crop_year')
                ?? CropYear::orderBy('crop_year', 'desc')->value('crop_year');
        }
        if (!$cropYear) {
            return response()->json(['message' => 'No crop year is set up yet.', 'empty' => true]);
        }

        $analytics = new DashboardAnalytics($cropYear, $data['week_no'] ?? null, $access);

        try {
            return response()->json($analytics->{$tab}());
        } catch (\Throwable $e) {
            Log::error("Dashboard tab {$tab} failed: " . $e->getMessage(), ['crop_year' => $cropYear, 'week_no' => $data['week_no'] ?? null]);
            return response()->json(['message' => 'Could not load this tab. Please try again.'], 500);
        }
    }
}
