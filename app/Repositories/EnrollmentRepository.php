<?php

namespace App\Repositories;

use App\Models\Enrollment;
use App\Support\Thai;

/**
 * รายชื่อเข้าร่วมโครงการ — อ่านจากฐานข้อมูล คืนค่าอาร์เรย์รูปแบบเดิม
 * ['id','code','hc','pa','joined','status','note']
 */
class EnrollmentRepository
{
    public const STATUSES = Enrollment::STATUSES;

    /** คลาส CSS ของป้ายสถานะ */
    public const STATUS_CLASS = [
        'สำเร็จ' => 'b-good',
        'กำลังดำเนินการ' => 'b-brand',
        'รอเริ่ม' => '',
        'ออกกลางคัน' => 'b-crit',
    ];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $cache = null;

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = [];

        $models = Enrollment::with(['household:id,hc', 'activity:id,pa'])
            ->orderBy('id')
            ->get();

        foreach ($models as $enrollment) {
            if (! $enrollment->household || ! $enrollment->activity) {
                continue;
            }

            $rows[] = [
                'id' => (string) $enrollment->id,
                'code' => $enrollment->code,
                'hc' => $enrollment->household->hc,
                'pa' => $enrollment->activity->pa,
                'joined' => $enrollment->joinedDate(),
                'status' => $enrollment->status,
                'income_before' => $enrollment->income_before === null ? null : (int) $enrollment->income_before,
                'income_after' => $enrollment->income_after === null ? null : (int) $enrollment->income_after,
                'note' => (string) $enrollment->note,
            ];
        }

        return $this->cache = $rows;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $enrollment) {
            if ($enrollment['id'] === $id) {
                return $enrollment;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function forHousehold(string $hc): array
    {
        return array_values(array_filter($this->all(), fn ($e) => $e['hc'] === $hc));
    }

    /** @return array<int, array<string, mixed>> */
    public function forActivity(string $pa): array
    {
        return array_values(array_filter($this->all(), fn ($e) => $e['pa'] === $pa));
    }

    public function countForActivity(string $pa): int
    {
        return count($this->forActivity($pa));
    }

    public function uniqueHouseholdCount(): int
    {
        return count(array_unique(array_column($this->all(), 'hc')));
    }

    public function activeActivityCount(): int
    {
        return count(array_unique(array_column($this->all(), 'pa')));
    }

    /** @return array<string, int> */
    public function statusCounts(array $rows): array
    {
        $out = [];

        foreach ($rows as $enrollment) {
            $out[$enrollment['status']] = ($out[$enrollment['status']] ?? 0) + 1;
        }

        arsort($out);

        return $out;
    }

    /**
     * รายได้ก่อนเข้าร่วมของรายการหนึ่ง
     *
     * ใช้ค่าที่จดไว้กับรายการเป็นหลัก ถ้ายังไม่มี (แถวเก่าที่ยังไม่ได้เติมย้อนหลัง
     * หรือยังไม่ได้รัน migration) ค่อยถอยไปอ่านรายได้ปัจจุบันของครัวเรือน
     * จะได้ไม่มีช่องว่างในรายงานช่วงที่ยังเปลี่ยนผ่าน
     *
     * @param  array<string, mixed>  $enrollment
     */
    public static function incomeBeforeOf(array $enrollment, ?HouseholdRepository $households = null): ?int
    {
        if (($enrollment['income_before'] ?? null) !== null) {
            return (int) $enrollment['income_before'];
        }

        $households ??= app(HouseholdRepository::class);
        $household = $households->find($enrollment['hc']);

        return $household['income'] ?? null;
    }

    /**
     * สรุปรายได้ก่อน/หลังเข้าร่วม ของรายการลงทะเบียนทั้งระบบ
     *
     * «ส่วนต่าง» คิดจากรายคนที่มีตัวเลขครบทั้งสองฝั่งเท่านั้น ไม่ใช่เอาค่าเฉลี่ยสองชุดมาลบกัน
     * เพราะสองชุดนั้นมาจากคนละกลุ่มตัวอย่าง (คนที่ยังไม่จบยังไม่มีรายได้หลังเข้าร่วม)
     * ถ้าลบตรง ๆ ตัวเลขจะเพี้ยนโดยดูเหมือนถูกต้อง
     *
     * @return array{total:int, beforeCount:int, afterCount:int, pairCount:int,
     *               beforeAvg:int|null, afterAvg:int|null, diffAvg:int|null}
     */
    public function incomeSummary(): array
    {
        /** @var HouseholdRepository $households */
        $households = app(HouseholdRepository::class);

        $before = [];
        $after = [];
        $pairs = [];
        $rows = $this->all();

        foreach ($rows as $enrollment) {
            $incomeAfter = $enrollment['income_after'] ?? null;

            if ($incomeAfter !== null) {
                $after[] = $incomeAfter;
            }

            $incomeBefore = self::incomeBeforeOf($enrollment, $households);

            if ($incomeBefore !== null) {
                $before[] = $incomeBefore;

                if ($incomeAfter !== null) {
                    $pairs[] = $incomeAfter - $incomeBefore;
                }
            }
        }

        $avg = fn (array $v) => $v === [] ? null : (int) round(array_sum($v) / count($v));

        return [
            'total' => count($rows),
            'beforeCount' => count($before),
            'afterCount' => count($after),
            'pairCount' => count($pairs),
            'beforeAvg' => $avg($before),
            'afterAvg' => $avg($after),
            'diffAvg' => $avg($pairs),
        ];
    }

    /**
     * รายการลงทะเบียนพร้อมข้อมูลครัวเรือน (h) และกิจกรรม (p)
     *
     * @param  array{pg?:string,pa?:string,q?:string,st?:string,vill?:string,sort?:string,dir?:int}  $f
     * @return array<int, array<string, mixed>>
     */
    public function rows(array $f = []): array
    {
        /** @var HouseholdRepository $households */
        $households = app(HouseholdRepository::class);
        /** @var ActivityRepository $activities */
        $activities = app(ActivityRepository::class);

        $rows = [];

        foreach ($this->all() as $enrollment) {
            $household = $households->find($enrollment['hc']);

            if (! $household) {
                continue;
            }

            $enrollment['h'] = $household;
            $enrollment['p'] = $activities->find($enrollment['pa']);
            $rows[] = $enrollment;
        }

        $pg = $f['pg'] ?? '';
        $pa = $f['pa'] ?? '';
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $st = $f['st'] ?? '';
        $vill = $f['vill'] ?? '';
        $prov = trim((string) ($f['prov'] ?? ''));
        $dist = trim((string) ($f['dist'] ?? ''));
        $tam = trim((string) ($f['tam'] ?? ''));

        $rows = array_values(array_filter($rows, function ($e) use ($pg, $pa, $q, $st, $vill, $prov, $dist, $tam) {
            /* พื้นที่ — เทียบแบบตัดช่องว่างหน้าหลัง กันข้อมูลที่มีเว้นวรรคเกิน
               ชื่ออำเภอ/จังหวัดมาจากตำบลของครัวเรือน จึงไม่มีทางขัดกับตำบลเอง */
            if ($prov && trim((string) ($e['h']['prov'] ?? '')) !== $prov) {
                return false;
            }

            if ($dist && trim((string) ($e['h']['dist'] ?? '')) !== $dist) {
                return false;
            }

            if ($tam && trim((string) ($e['h']['tam'] ?? '')) !== $tam) {
                return false;
            }

            /* กรองตามโครงการหลักก่อน แล้วค่อยกรองกิจกรรมย่อยในโครงการนั้น */
            if ($pg && (string) ($e['p']['program_id'] ?? '') !== (string) $pg) {
                return false;
            }

            if ($pa && $e['pa'] !== $pa) {
                return false;
            }

            if ($st && $e['status'] !== $st) {
                return false;
            }

            if ($vill && $e['h']['vill'] !== $vill) {
                return false;
            }

            if ($q) {
                $hay = mb_strtolower(implode(' ', [
                    $e['hc'], $e['h']['name'], $e['h']['house'], $e['h']['vill'],
                    $e['pa'], $e['p']['name'] ?? '',
                ]));

                if (! str_contains($hay, $q)) {
                    return false;
                }
            }

            return true;
        }));

        $sort = $f['sort'] ?? 'hc';
        $dir = ($f['dir'] ?? 1) < 0 ? -1 : 1;

        usort($rows, function ($x, $y) use ($sort, $dir) {
            /* เรียงตามรายได้ก่อนเข้าร่วมที่จดไว้กับรายการนี้ ไม่ใช่รายได้ปัจจุบันของครัวเรือน
               ให้ตรงกับตัวเลขที่แสดงในคอลัมน์นั้นจริง ๆ */
            if ($sort === 'income') {
                $a = self::incomeBeforeOf($x) ?? -1;
                $b = self::incomeBeforeOf($y) ?? -1;

                return ($a <=> $b) * $dir;
            }

            /* หมู่เป็นตัวเลข ต้องเทียบแบบตัวเลข ไม่งั้นได้ลำดับ 1, 10, 2, 3
               ค่าว่างไปท้ายสุดเสมอ */
            if ($sort === 'moo') {
                $a = ($x['h']['moo'] ?? '') === '' || $x['h']['moo'] === null ? PHP_INT_MAX : (int) $x['h']['moo'];
                $b = ($y['h']['moo'] ?? '') === '' || $y['h']['moo'] === null ? PHP_INT_MAX : (int) $y['h']['moo'];

                return ($a <=> $b) * $dir;
            }

            [$a, $b] = match ($sort) {
                'name' => [$x['h']['name'], $y['h']['name']],
                'vill' => [$x['h']['vill'], $y['h']['vill']],
                'tam' => [$x['h']['tam'], $y['h']['tam']],
                'dist' => [$x['h']['dist'], $y['h']['dist']],
                'pa' => [$x['pa'], $y['pa']],
                'joined' => [$x['joined'], $y['joined']],
                'status' => [$x['status'], $y['status']],
                default => [$x['hc'], $y['hc']],
            };

            return Thai::compare($a, $b) * $dir;
        });

        return $rows;
    }

    public function nextId(): string
    {
        return Enrollment::nextCode();
    }

    /** ล้างแคชหลังบันทึกข้อมูล */
    public function forget(): void
    {
        $this->cache = null;
    }
}
