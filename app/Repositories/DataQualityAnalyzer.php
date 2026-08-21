<?php

namespace App\Repositories;

use App\Support\Thai;

/**
 * ตรวจสอบคุณภาพข้อมูล (เทียบเท่าฟังก์ชัน ISS() ในไฟล์ HTML ต้นฉบับ)
 *
 * แต่ละประเด็นมีโครงสร้าง:
 *   cat   หมวด: dup | miss | mism | typo | inc | area
 *   sev   ความรุนแรง: critical | serious | warning
 *   title หัวข้อ
 *   desc  รายละเอียด (มี HTML ได้ — ข้อมูลผู้ใช้ถูก escape แล้ว)
 *   hcs   รหัส HC ที่เกี่ยวข้อง
 *   fix   ปุ่มแก้ไขที่ควรแสดง: merge | edit | bulk | fixdist | area
 */
class DataQualityAnalyzer
{
    /** ป้ายกำกับความรุนแรง: [คลาส CSS, ข้อความ] */
    public const SEVERITY = [
        'critical' => ['b-crit', 'วิกฤต'],
        'serious' => ['b-ser', 'ควรแก้เร็ว'],
        'warning' => ['b-warn', 'เฝ้าระวัง'],
    ];

    /** หมวดที่ใช้เป็นตัวกรองในหน้าตรวจสอบคุณภาพข้อมูล */
    public const CATEGORIES = [
        ['all', 'ทุกประเด็น', 'shield'],
        ['dup', 'ครัวเรือนซ้ำ', 'copy'],
        ['miss', 'ข้อมูลไม่ครบ', 'warn'],
        ['mism', 'พื้นที่ไม่สอดคล้อง', 'map'],
        ['typo', 'สะกดผิด', 'edit'],
        ['inc', 'ยังไม่บันทึก', 'info'],
        ['area', 'ชุดข้อมูลพื้นที่', 'sheet'],
    ];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(
        private HouseholdRepository $households,
        private EnrollmentRepository $enrollments,
        private AreaRepository $areas,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function issues(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $hh = $this->households->all();
        $total = max(1, count($hh));
        $out = [];

        /* ---------- ครัวเรือนซ้ำซ้อน (ชื่อ + บ้านเลขที่ ตรงกัน) ---------- */
        $groups = [];

        foreach ($hh as $h) {
            $groups[Thai::nrm($h['name']).'|'.Thai::nrm($h['house'])][] = $h;
        }

        foreach ($groups as $g) {
            if (count($g) > 1) {
                $codes = array_column($g, 'hc');
                $out[] = [
                    'cat' => 'dup', 'sev' => 'critical', 'title' => 'ครัวเรือนซ้ำซ้อน',
                    'desc' => '<b>'.e($g[0]['name']).'</b> บ้านเลขที่ '.e($g[0]['house'])
                        .' ปรากฏ '.count($g).' รายการ: '.implode(' · ', array_map('e', $codes)),
                    'hcs' => $codes, 'fix' => 'merge',
                ];
            }
        }

        /* ---------- ไม่ระบุตำบล ---------- */
        $noTam = array_values(array_filter($hh, fn ($h) => ! $h['tam']));

        if ($noTam) {
            $codes = array_column($noTam, 'hc');
            $out[] = [
                'cat' => 'miss', 'sev' => 'critical', 'title' => 'ไม่ระบุตำบล',
                'desc' => count($noTam).' ครัวเรือนมีช่องตำบลว่าง ทำให้จับคู่รหัสพื้นที่และออกรหัส HC ไม่ได้ — '
                    .implode(' · ', array_map('e', $codes)),
                'hcs' => $codes, 'fix' => count($noTam) === 1 ? 'edit' : 'bulk',
            ];
        }

        /* ---------- อำเภอไม่ตรงกับตำบล (จับกลุ่มตามคู่ ตำบล → อำเภอที่บันทึกผิด) ---------- */
        $tamToDist = $this->areas->tamToDist();
        $mismatch = [];

        foreach ($hh as $h) {
            $right = $tamToDist[Thai::tamName($h['tam'])] ?? null;

            if ($h['tam'] && $right && $right !== Thai::nrm($h['dist'])) {
                $mismatch[$h['tam'].'→'.$h['dist']][] = $h;
            }
        }

        foreach ($mismatch as $g) {
            $h = $g[0];
            $right = $tamToDist[Thai::tamName($h['tam'])];
            $out[] = [
                'cat' => 'mism', 'sev' => 'serious', 'title' => 'อำเภอไม่ตรงกับตำบล',
                'desc' => '<b>'.count($g).' ครัวเรือน</b> ในตำบล'.e(Thai::tamName($h['tam']))
                    .' (รหัส '.Thai::tamCode($h['tam']).') บันทึกอำเภอเป็น «'.e($h['dist'])
                    .'» แต่ตำบลนี้อยู่ในอำเภอ «'.e($right).'» ตามชีตข้อมูลจังหวัด',
                'hcs' => array_column($g, 'hc'), 'fix' => 'fixdist', 'to' => $right,
            ];
        }

        /* ---------- กลุ่มการสะกดผิด ---------- */
        $typo = function (callable $pred, string $title, string $tip) use ($hh, &$out) {
            $g = array_values(array_filter($hh, $pred));

            if (! $g) {
                return;
            }

            $sample = array_map(
                fn ($h) => '<b>'.e($h['hc']).'</b> «'.e($h['name']).'»',
                array_slice($g, 0, 6)
            );

            $out[] = [
                'cat' => 'typo', 'sev' => 'warning', 'title' => $title,
                'desc' => count($g).' รายการ — '.$tip.': '.implode(' · ', $sample)
                    .(count($g) > 6 ? ' และอีก '.(count($g) - 6).' รายการ' : ''),
                'hcs' => array_column($g, 'hc'), 'fix' => count($g) === 1 ? 'edit' : 'bulk',
            ];
        };

        $typo(
            fn ($h) => Thai::hasDoubleMark($h['name']) || Thai::hasDoubleMark($h['vill']),
            'สระ/วรรณยุกต์ซ้ำติดกัน',
            'พิมพ์สระหรือวรรณยุกต์ซ้ำ ทำให้ค้นหาไม่พบ'
        );
        $typo(
            fn ($h) => ! Thai::hasValidPrefix($h['name']),
            'คำนำหน้าชื่อไม่ถูกรูป',
            'ไม่ตรงกับ นาย/นาง/นางสาว/เด็กชาย/เด็กหญิง'
        );
        $typo(
            fn ($h) => $h['vill'] && ! in_array(trim($h['vill']), Thai::VILLAGES, true),
            'ชื่อหมู่บ้านไม่ตรงกับชุดข้อมูลอ้างอิง',
            'สะกดต่างจากหมู่บ้านมาตรฐาน'
        );

        /* ---------- ข้อมูลที่ยังไม่บันทึก ---------- */
        $noIncome = array_values(array_filter($hh, fn ($h) => $h['income'] === null));

        if ($noIncome) {
            $out[] = [
                'cat' => 'inc', 'sev' => 'warning', 'title' => 'ไม่มีข้อมูลรายได้ BL',
                'desc' => count($noIncome).' ครัวเรือน ('.round(count($noIncome) / $total * 100).'%)'
                    .' ยังไม่บันทึกรายได้ — กระทบการคำนวณ SROI และการจัดกลุ่มเป้าหมาย',
                'hcs' => array_column(array_slice($noIncome, 0, 60), 'hc'), 'fix' => 'bulk',
            ];
        }

        $noPhone = array_values(array_filter($hh, fn ($h) => ! $h['phone']));

        if ($noPhone) {
            $out[] = [
                'cat' => 'inc', 'sev' => 'warning', 'title' => 'ไม่มีเบอร์ติดต่อ',
                'desc' => count($noPhone).' ครัวเรือนไม่มีเบอร์โทรศัพท์หรือแหล่งอ้างอิง ทำให้ติดตามผลลำบาก',
                'hcs' => array_column(array_slice($noPhone, 0, 60), 'hc'), 'fix' => 'bulk',
            ];
        }

        $noEn = array_values(array_filter(
            $hh,
            fn ($h) => count($this->enrollments->forHousehold($h['hc'])) === 0
        ));

        if ($noEn) {
            $out[] = [
                'cat' => 'inc', 'sev' => 'warning', 'title' => 'ยังไม่เข้าร่วมกิจกรรมใด',
                'desc' => count($noEn).' ครัวเรือนอยู่ในทะเบียนแต่ยังไม่ถูกจับคู่กับกิจกรรมใด',
                'hcs' => array_column(array_slice($noEn, 0, 60), 'hc'), 'fix' => 'bulk',
            ];
        }

        /* ---------- ชุดข้อมูลพื้นที่ ---------- */
        $areas = $this->areas->all();
        $areaCount = [];

        foreach ($areas as $a) {
            $k = $a['prov'].$a['dist'].$a['tam'].$a['code'];
            $areaCount[$k] = ($areaCount[$k] ?? 0) + 1;
        }

        foreach ($areaCount as $n) {
            if ($n > 1) {
                $out[] = [
                    'cat' => 'area', 'sev' => 'serious', 'title' => 'ตำบลซ้ำในชุดข้อมูลพื้นที่',
                    'desc' => '«ลำใหม่(5)» ถูกบันทึก 2 แถวในชีตข้อมูลจังหวัด — ควรเหลือแถวเดียว',
                    'hcs' => [], 'fix' => 'area',
                ];
            }
        }

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($a) => $a['code'],
            array_filter($areas, fn ($a) => $a['code'] !== 99)
        ), fn ($code) => $code !== null)));
        sort($codes);
        $gaps = [];

        /* ต้องมีรหัสอย่างน้อย 1 ตัวก่อน ไม่งั้น max() จะ error เมื่อฐานข้อมูลยังว่าง */
        if ($codes !== []) {
            $maxCode = (int) max($codes);

            for ($i = 1; $i <= $maxCode; $i++) {
                if (! in_array($i, $codes, true)) {
                    $gaps[] = $i;
                }
            }

            if ($gaps) {
                $out[] = [
                    'cat' => 'area', 'sev' => 'warning', 'title' => 'รหัสพื้นที่ขาดช่วง',
                    'desc' => 'รหัส '.implode(', ', $gaps).' ไม่ปรากฏในชุดข้อมูลพื้นที่ แต่ลำดับรหัสเดินถึง '
                        .$maxCode.' — อาจมีตำบลที่ยังไม่บันทึก',
                    'hcs' => [], 'fix' => 'area',
                ];
            }
        }

        $code99 = array_values(array_filter($areas, fn ($a) => $a['code'] === 99));

        if (count($code99) > 1) {
            $out[] = [
                'cat' => 'area', 'sev' => 'warning', 'title' => 'รหัส 99 ใช้ซ้ำหลายตำบล',
                'desc' => count($code99).' ตำบลใช้รหัส 99 ร่วมกัน ('
                    .e(implode(', ', array_column($code99, 'tam')))
                    .') — ควรกำหนดรหัสเฉพาะเพื่อออกเลข HC ได้',
                'hcs' => [], 'fix' => 'area',
            ];
        }

        $sheetSpell = array_filter($areas, fn ($a) => $a['dist'] === 'กรงปีนัง');
        $hhSpell = array_filter($hh, fn ($h) => $h['dist'] === 'กรงปินัง');

        if ($sheetSpell && $hhSpell) {
            $out[] = [
                'cat' => 'area', 'sev' => 'serious', 'title' => 'ชื่ออำเภอสะกดไม่ตรงกันระหว่างชีต',
                'desc' => 'ชีตข้อมูลจังหวัดใช้ «กรงปีนัง» แต่ชีตครัวเรือนใช้ «กรงปินัง» — ทำให้ VLOOKUP / JOIN ไม่พบข้อมูล',
                'hcs' => [], 'fix' => 'area',
            ];
        }

        /* ---------- เรียงตามความรุนแรง ---------- */
        $rank = ['critical' => 0, 'serious' => 1, 'warning' => 2];
        usort($out, fn ($a, $b) => $rank[$a['sev']] <=> $rank[$b['sev']]);

        return $this->cache = $out;
    }

    /** ประเด็นในหมวดที่เลือก @return array<int, array<string, mixed>> */
    public function byCategory(string $cat = 'all'): array
    {
        if ($cat === 'all') {
            return $this->issues();
        }

        return array_values(array_filter($this->issues(), fn ($i) => $i['cat'] === $cat));
    }

    /** ประเด็นที่เกี่ยวข้องกับครัวเรือนหนึ่ง @return array<int, array<string, mixed>> */
    public function forHousehold(string $hc): array
    {
        return array_values(array_filter(
            $this->issues(),
            fn ($i) => in_array($hc, $i['hcs'], true)
        ));
    }

    /** true ถ้าครัวเรือนนี้มีประเด็นระดับ critical/serious */
    public function isFlagged(string $hc): bool
    {
        foreach ($this->issues() as $i) {
            if ($i['sev'] !== 'warning' && in_array($hc, $i['hcs'], true)) {
                return true;
            }
        }

        return false;
    }

    /** จำนวนประเด็นตามความรุนแรง */
    public function countBySeverity(string $sev): int
    {
        return count(array_filter($this->issues(), fn ($i) => $i['sev'] === $sev));
    }

    /** จำนวนประเด็นตามหมวด */
    public function countByCategory(string $cat): int
    {
        return count($this->byCategory($cat));
    }
}
