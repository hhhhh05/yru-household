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
        $rows = [['PA', 'ชื่อโครงการ', 'ชื่อกิจกรรม', 'งบประมาณ', 'ปีงบประมาณ', 'ครัวเรือนเข้าร่วม']];

        foreach ($this->activities->all() as $p) {
            $rows[] = [
                $p['pa'], $p['program'], $p['name'], $p['budget'], $p['fy'],
                $this->enrollments->countForActivity($p['pa']),
            ];
        }

        return ['โครงการกิจกรรม.csv', $rows];
    }

    private function enrollmentRows(Request $request): array
    {
        $rows = [[
            'HC', 'ชื่อ - สกุล', 'บ้านเลขที่', 'บ้าน/ชุมชน', 'หมู่', 'ตำบล', 'อำเภอ', 'เบอร์โทรศัพท์',
            'PA', 'ชื่อกิจกรรม', 'ปีงบ', 'วันที่เข้าร่วม', 'สถานะ', 'รายได้ BL',
        ]];

        foreach ($this->enrollments->rows($request->query()) as $e) {
            $rows[] = [
                $e['hc'], $e['h']['name'], $e['h']['house'], $e['h']['vill'], $e['h']['moo'],
                $e['h']['tam'], $e['h']['dist'], $e['h']['phone'], $e['pa'],
                $e['p']['name'] ?? '', $e['p']['fy'] ?? '', $e['joined'], $e['status'], $e['h']['income'] ?? '',
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
