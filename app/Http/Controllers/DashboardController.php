<?php

namespace App\Http\Controllers;

use App\Repositories\ActivityRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;

class DashboardController extends Controller
{
    public function __invoke(
        HouseholdRepository $households,
        ActivityRepository $activities,
        EnrollmentRepository $enrollments,
        DataQualityAnalyzer $quality,
    ) {
        $villages = $households->byVillage();
        $budgetByYear = $activities->budgetByYear();

        return view('dashboard', [
            'navKey' => 'home',
            'total' => $households->count(),
            'target' => HouseholdRepository::TARGET,
            'incomeCount' => count($households->withIncome()),
            'medianIncome' => $households->medianIncome(),
            'enrolledCount' => $enrollments->uniqueHouseholdCount(),
            'enrollmentCount' => count($enrollments->all()),
            'activityCount' => $activities->count(),
            'programCount' => $activities->programCount(),
            'programCountByYear' => $activities->programCountByYear(),
            'activityCountByYear' => $activities->activityCountByYear(),
            'budget' => $activities->totalBudget(),
            'budgetByYear' => $budgetByYear,
            'maxYearBudget' => max($budgetByYear ?: [1]),
            'villages' => $villages,
            'areaCounts' => $households->areaCounts(),
            'maxVillage' => max(array_column($villages, 'n') ?: [1]),
            'activities' => $activities->all(),
            'issues' => $quality->issues(),
            'criticalCount' => $quality->countBySeverity('critical'),
            'seriousCount' => $quality->countBySeverity('serious'),
        ]);
    }
}
