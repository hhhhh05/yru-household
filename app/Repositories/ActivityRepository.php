<?php

namespace App\Repositories;

use App\Models\Activity;
use App\Models\Program;

/**
 * โครงการ / กิจกรรม — อ่านจากฐานข้อมูล คืนค่าอาร์เรย์รูปแบบเดิม
 * ['pa','name','budget','fy','program']
 */
class ActivityRepository
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $cache = null;

    /** @var array<int, string>|null */
    private ?array $programs = null;

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = Activity::with('program')
            ->orderBy('fiscal_year')
            ->orderBy('pa')
            ->get()
            ->map(fn (Activity $a) => $a->toLegacyArray())
            ->all();

        return $this->cache = $rows;
    }

    /** @return array<int, string> ปีงบ → ชื่อโครงการหลัก */
    public function programs(): array
    {
        if ($this->programs !== null) {
            return $this->programs;
        }

        return $this->programs = Program::orderBy('fiscal_year')
            ->pluck('name', 'fiscal_year')
            ->all();
    }

    /**
     * โครงการหลักทั้งหมดแยกตามปีงบ (ใช้ทำ dropdown ที่เลือกจากตาราง programs)
     *
     * @return array<int, array<int, array{id:int, name:string}>>
     */
    public function programsByYear(): array
    {
        $out = [];

        foreach (Program::orderBy('fiscal_year')->orderBy('id')->get() as $program) {
            $out[$program->fiscal_year][] = ['id' => $program->id, 'name' => $program->name];
        }

        return $out;
    }

    /**
     * ปีงบที่ให้เลือกในฟอร์ม — อ้างอิงจากตาราง programs โดยตรง
     * (ปีงบเป็นของโครงการหลัก กิจกรรมจึงเลือกได้เฉพาะปีที่มีโครงการหลักอยู่แล้ว
     *  ถ้าต้องการปีใหม่ ให้เพิ่มผ่านตัวเลือก «เพิ่มปีงบใหม่» ในฟอร์ม ซึ่งจะสร้างโครงการหลักของปีนั้นให้)
     *
     * @return array<int, int>
     */
    public function yearOptions(): array
    {
        $years = Program::query()
            ->select('fiscal_year')
            ->distinct()
            ->orderBy('fiscal_year')
            ->pluck('fiscal_year')
            ->map(fn ($y) => (int) $y)
            ->all();

        /* ยังไม่มีโครงการหลักเลย → ให้เริ่มที่ปีงบปัจจุบัน */
        return $years ?: [(int) now()->year + 543];
    }

    public function find(string $pa): ?array
    {
        foreach ($this->all() as $activity) {
            if ($activity['pa'] === $pa) {
                return $activity;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function totalBudget(): int
    {
        return (int) array_sum(array_column($this->all(), 'budget'));
    }

    /** @return array<int, int> */
    public function fiscalYears(): array
    {
        $years = array_values(array_unique(array_column($this->all(), 'fy')));
        sort($years);

        return $years;
    }

    /** @return array<int, int> */
    public function budgetByYear(): array
    {
        $out = [];

        foreach ($this->all() as $activity) {
            $out[$activity['fy']] = ($out[$activity['fy']] ?? 0) + $activity['budget'];
        }

        ksort($out);

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function filter(?string $fy = null, ?string $q = null, ?string $programId = null): array
    {
        $rows = $this->all();

        if ($fy) {
            $rows = array_filter($rows, fn ($p) => (string) $p['fy'] === (string) $fy);
        }

        if ($programId) {
            $rows = array_filter($rows, fn ($p) => (string) ($p['program_id'] ?? '') === (string) $programId);
        }

        if ($q = mb_strtolower(trim((string) $q))) {
            $rows = array_filter($rows, fn ($p) => str_contains(mb_strtolower($p['pa'].' '.$p['name'].' '.($p['program'] ?? '')), $q));
        }

        return array_values($rows);
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function groupByYear(array $rows): array
    {
        $out = [];

        foreach ($rows as $activity) {
            $out[$activity['fy']][] = $activity;
        }

        ksort($out);

        return $out;
    }

    /**
     * แบ่งกิจกรรมเป็น 2 ชั้น: ปีงบประมาณ → โครงการหลัก
     *
     * แต่ละชั้นมียอดรวมงบและจำนวนกิจกรรมติดมาด้วย จึงไม่ต้องคำนวณซ้ำในหน้า Blade
     * กิจกรรมที่ยังไม่ผูกโครงการหลัก (program_id ว่าง) จะถูกรวมไว้กลุ่ม '0'
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{budget:int, count:int, programs:array<int|string, array{name:string, budget:int, rows:array<int, array<string, mixed>>}>}>
     */
    public function groupByProgram(array $rows): array
    {
        $out = [];

        foreach ($rows as $activity) {
            $fy = $activity['fy'];
            $pid = $activity['program_id'] ?: 0;
            $budget = (int) $activity['budget'];

            $out[$fy]['budget'] = ($out[$fy]['budget'] ?? 0) + $budget;
            $out[$fy]['count'] = ($out[$fy]['count'] ?? 0) + 1;

            $group = &$out[$fy]['programs'][$pid];
            $group['name'] = $activity['program'] ?: 'ยังไม่ผูกโครงการหลัก';
            $group['budget'] = ($group['budget'] ?? 0) + $budget;
            $group['rows'][] = $activity;
            unset($group);
        }

        ksort($out);

        /* ในแต่ละปีงบ เรียงโครงการหลักตามงบมาก → น้อย เพื่อให้เห็นก้อนใหญ่ก่อน */
        foreach ($out as $fy => $year) {
            uasort($out[$fy]['programs'], fn ($a, $b) => $b['budget'] <=> $a['budget']);
        }

        return $out;
    }

    public function nextPa(int $fy): string
    {
        return Activity::nextPa($fy);
    }
}
