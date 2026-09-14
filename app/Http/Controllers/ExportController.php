<?php

namespace App\Http\Controllers;

use App\Repositories\ActivityRepository;
use App\Repositories\AreaRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;
use App\Support\Thai;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ส่งออกข้อมูลเป็นไฟล์ CSV (UTF-8 BOM เปิดใน Excel / Google Sheets ได้ทันที)
 * ทำงานได้จริงแม้ยังไม่เชื่อมฐานข้อมูล เพราะอ่านจากชุดข้อมูลใน app/Data
 */
class ExportController extends Controller
{
    public function __construct(
        private HouseholdRepository $households,
        private ActivityRepository $activities,
        private EnrollmentRepository $enrollments,
        private AreaRepository $areas,
        private DataQualityAnalyzer $quality,
    ) {}

    public function __invoke(Request $request, string $type): StreamedResponse
    {
        [$filename, $rows] = match ($type) {
            'households' => $this->households($request),
            'activities' => $this->activityRows(),
            'enrollments' => $this->enrollmentRows($request),
            'areas' => $this->areaRows(),
            'quality' => $this->qualityRows(),
            default => abort(404),
        };

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");     // BOM สำหรับ Excel

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function households(Request $request): array
    {
        $rows = [[
            'HC', 'ชื่อ - สกุล', 'บ้านเลขที่', 'บ้าน/ชุมชน', 'หมู่', 'จังหวัด', 'อำเภอ', 'ตำบล',
            'เบอร์โทรศัพท์/แหล่งอ้างอิง', 'รายได้ BL', 'จำนวนกิจกรรม',
        ]];

        /* ถ้ามีการเลือกรายการไว้ ให้ส่งออกเฉพาะที่เลือก มิฉะนั้นใช้ตัวกรองปัจจุบัน */
        $selected = array_values(array_filter(explode(',', (string) $request->query('hcs'))));

        $list = $selected
            ? array_values(array_filter($this->households->all(), fn ($h) => in_array($h['hc'], $selected, true)))
            : $this->households->filter($request->query());

        foreach ($list as $h) {
            $rows[] = [
                $h['hc'], $h['name'], $h['house'], $h['vill'], $h['moo'], $h['prov'], $h['dist'], $h['tam'],
                $h['phone'], $h['income'] ?? '', count($this->enrollments->forHousehold($h['hc'])),
            ];
        }

        return ['ทะเบียนครัวเรือน.csv', $rows];
    }

    private function activityRows(): array
    {
        $rows = [['PA', 'ชื่อโครงการ', 'ชื่อกิจกรรม', 'งบประมาณ', 'ปีงบประมาณ',
            'คณะ/หน่วยงาน', 'เจ้าหน้าที่ผู้รับผิดชอบ', 'ครัวเรือนเข้าร่วม']];

        foreach ($this->activities->all() as $p) {
            $rows[] = [
                $p['pa'], $p['program'], $p['name'], $p['budget'], $p['fy'],
                $p['unit'] ?? '', $p['officer'] ?? '',
                $this->enrollments->countForActivity($p['pa']),
            ];
        }

        return ['โครงการกิจกรรม.csv', $rows];
    }

    /**
     * ส่งออกรายชื่อเข้าร่วม — ครบทุกคอลัมน์ที่ระบบเก็บ
     *
     * ยึดตัวกรองเดียวกับหน้าเว็บ (ส่ง query string เดิมเข้าไป) ไฟล์ที่ได้จึงตรงกับที่เห็นบนหน้าจอ
     * รายได้ก่อนเข้าร่วมใช้ค่าที่จดไว้กับรายการนั้น ไม่ใช่รายได้ปัจจุบันของครัวเรือน
     * และมีคอลัมน์บอกที่มาไว้ด้วย เพราะแถวเก่าบางแถวยังต้องอ้างอิงจากทะเบียน
     */
    private function enrollmentRows(Request $request): array
    {
        $rows = [[
            'รหัสรายการ', 'HC', 'ชื่อ - สกุล', 'บ้านเลขที่', 'หมู่บ้าน/ชุมชน', 'หมู่',
            'ตำบล', 'รหัสตำบล', 'อำเภอ', 'จังหวัด', 'เบอร์โทรศัพท์',
            'PA', 'ชื่อกิจกรรม', 'โครงการหลัก', 'ปีงบ', 'คณะ/หน่วยงานรับผิดชอบ',
            'วันที่เข้าร่วม (พ.ศ.)', 'สถานะ',
            'รายได้ก่อนเข้าร่วม', 'ที่มารายได้ก่อนเข้าร่วม', 'รายได้หลังเข้าร่วม', 'ส่วนต่างรายได้',
            'หมายเหตุ',
        ]];

        foreach ($this->enrollments->rows($request->query()) as $e) {
            $before = $e['income_before'] ?? null;
            $source = $before !== null ? 'บันทึกไว้กับรายการ' : ($e['h']['income'] !== null ? 'อ้างอิงทะเบียนครัวเรือน' : '');
            $before ??= $e['h']['income'];
            $after = $e['income_after'] ?? null;

            $rows[] = [
                $e['code'] ?? '',
                $e['hc'],
                $e['h']['name'],
                $e['h']['house'],
                $e['h']['vill'],
                $e['h']['moo'],
                Thai::tamName((string) $e['h']['tam']),
                Thai::tamCode((string) $e['h']['tam']),
                $e['h']['dist'],
                $e['h']['prov'],
                $e['h']['phone'],
                $e['pa'],
                $e['p']['name'] ?? '',
                $e['p']['program'] ?? '',
                $e['p']['fy'] ?? '',
                $e['p']['unit'] ?? '',
                $e['joined'],
                $e['status'],
                $before ?? '',
                $source,
                $after ?? '',
                /* ส่วนต่างคิดได้เฉพาะรายที่มีตัวเลขครบทั้งสองฝั่ง — ไม่งั้นเว้นว่าง
                   ดีกว่าใส่ 0 ซึ่งอ่านแล้วเข้าใจผิดว่ารายได้ไม่เปลี่ยน */
                ($before !== null && $after !== null) ? $after - $before : '',
                $e['note'],
            ];
        }

        return ['รายชื่อเข้าร่วมโครงการ.csv', $rows];
    }

    private function areaRows(): array
    {
        $rows = [['จังหวัด', 'อำเภอ', 'ตำบล', 'รหัสพื้นที่', 'ครัวเรือนในระบบ']];
        $counts = $this->households->countByTambon();

        foreach ($this->areas->all() as $a) {
            $key = Thai::nrm($a['tam'].'('.$a['code'].')');
            $rows[] = [$a['prov'], $a['dist'], $a['tam'], $a['code'], $counts[$key] ?? 0];
        }

        return ['ข้อมูลพื้นที่.csv', $rows];
    }

    private function qualityRows(): array
    {
        $rows = [['ระดับ', 'ประเด็น', 'รายละเอียด', 'รหัสที่เกี่ยวข้อง']];

        foreach ($this->quality->issues() as $i) {
            $rows[] = [
                DataQualityAnalyzer::SEVERITY[$i['sev']][1],
                $i['title'],
                html_entity_decode(strip_tags($i['desc']), ENT_QUOTES, 'UTF-8'),
                implode(' ', $i['hcs']),
            ];
        }

        return ['รายงานคุณภาพข้อมูล.csv', $rows];
    }
}
