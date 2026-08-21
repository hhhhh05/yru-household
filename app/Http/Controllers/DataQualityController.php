<?php

namespace App\Http\Controllers;

use App\Repositories\DataQualityAnalyzer;
use App\Repositories\HouseholdRepository;
use Illuminate\Http\Request;

class DataQualityController extends Controller
{
    public function index(
        Request $request,
        DataQualityAnalyzer $quality,
        HouseholdRepository $households,
    ) {
        $cat = (string) $request->query('cat', 'all');
        $valid = array_column(DataQualityAnalyzer::CATEGORIES, 0);

        if (! in_array($cat, $valid, true)) {
            $cat = 'all';
        }

        return view('quality.index', [
            'navKey' => 'dq',
            'cat' => $cat,
            'issues' => $quality->issues(),
            'list' => $quality->byCategory($cat),
            'categories' => DataQualityAnalyzer::CATEGORIES,
            'counts' => collect($valid)->mapWithKeys(
                fn ($c) => [$c => $quality->countByCategory($c)]
            )->all(),
            'critical' => $quality->countBySeverity('critical'),
            'serious' => $quality->countBySeverity('serious'),
            'warning' => $quality->countBySeverity('warning'),
            'completeness' => $households->completeness(),
        ]);
    }
}
