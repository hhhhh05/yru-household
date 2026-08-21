<?php

namespace App\Http\Controllers;

use App\Repositories\ActivityRepository;
use App\Repositories\AreaRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;
use Illuminate\Http\Request;

class ImportExportController extends Controller
{
    /** ลิงก์ชีตต้นทาง */
    public const SHEET_URL = 'https://docs.google.com/spreadsheets/d/1oQGycD-d-gq470IZIepZtnWxSalwfUrCpl_8Mkw9YeE/edit';

    /** การจับคู่คอลัมน์ต้นทาง → ฟิลด์ในระบบ */
    public const COLUMN_MAP = [
        ['HC', 'hc', 'รหัสครัวเรือน'],
        ['ชื่อ - สกุล', 'name', 'ชื่อ-สกุล'],
        ['บ้านเลขที่', 'house', 'บ้านเลขที่'],
        ['บ้าน/ชุมชน', 'vill', 'หมู่บ้าน/ชุมชน'],
        ['หมู่', 'moo', 'หมู่ที่'],
        ['จังหวัด', 'prov', 'จังหวัด'],
        ['อำเภอ', 'dist', 'อำเภอ'],
        ['ตำบล', 'tam', 'ตำบล (พร้อมรหัส)'],
        ['เบอร์โทรศัพท์/แหล่งอ้างอิง', 'phone', 'เบอร์ติดต่อ'],
        ['รายได้ BL', 'income', 'รายได้ BL (บาท/ปี)'],
    ];

    public function index(
        Request $request,
        HouseholdRepository $households,
        ActivityRepository $activities,
        EnrollmentRepository $enrollments,
        AreaRepository $areas,
        DataQualityAnalyzer $quality,
    ) {
        $step = max(1, min(4, (int) $request->query('step', 1)));
        $issues = $quality->issues();

        /* จำนวนแถวที่ต้องแก้ก่อน / มีคำเตือน */
        $errorRows = count(array_filter($households->all(), fn ($h) => ! $h['tam']));
        $warnRows = 0;

        foreach ($issues as $i) {
            if ($i['sev'] !== 'critical') {
                $warnRows += count($i['hcs']);
            }
        }

        return view('io.index', [
            'navKey' => 'io',
            'step' => $step,
            'sheetUrl' => self::SHEET_URL,
            'columnMap' => self::COLUMN_MAP,
            'issues' => $issues,
            'errorRows' => $errorRows,
            'warnRows' => $warnRows,
            'householdCount' => $households->count(),
            'activityCount' => $activities->count(),
            'enrollmentCount' => count($enrollments->all()),
            'areaCount' => count($areas->all()),
            'issueCount' => count($issues),
        ]);
    }
}
