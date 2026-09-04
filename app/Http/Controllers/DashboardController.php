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
        $budgetByYear = $activities->budgetByYear();
        $areaLevels = $households->countByAreaLevel();

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
            'areaCounts' => $households->areaCounts(),
            'areaLevels' => $areaLevels,
            'income' => $enrollments->incomeSummary(),
            'statusCounts' => $enrollments->statusCounts($enrollments->all()),
            'statusClass' => EnrollmentRepository::STATUS_CLASS,
            'activities' => $activities->all(),
            'issues' => $quality->issues(),
            'criticalCount' => $quality->countBySeverity('critical'),
            'seriousCount' => $quality->countBySeverity('serious'),
        ]);
    }
}
