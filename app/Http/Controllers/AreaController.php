<?php

namespace App\Http\Controllers;

use App\Repositories\AreaRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\HouseholdRepository;
use App\Support\Thai;

class AreaController extends Controller
{
    public function index(
        AreaRepository $areas,
        HouseholdRepository $households,
        DataQualityAnalyzer $quality,
    ) {
        /* จำนวนแถวที่ (จังหวัด+อำเภอ+ตำบล+รหัส) ซ้ำกัน — ใช้ติดป้าย "ซ้ำ" */
        $dupKeys = [];

        foreach ($areas->all() as $a) {
            $k = $a['prov'].$a['dist'].$a['tam'].$a['code'];
            $dupKeys[$k] = ($dupKeys[$k] ?? 0) + 1;
        }

        return view('areas.index', [
            'navKey' => 'ar',
            'grouped' => $areas->groupedByProvince(),
            'provinceCount' => count($areas->provinces()),
            'tambonCount' => $areas->tambonCount(),
            'total' => count($areas->all()),
            'householdsByTambon' => $households->countByTambon(),
            'dupKeys' => $dupKeys,
            'issues' => $quality->byCategory('area'),
            'nrm' => fn (string $s) => Thai::nrm($s),
        ]);
    }
}
