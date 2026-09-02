<?php

namespace App\Repositories;

use App\Models\Household;
use App\Support\Thai;
use Illuminate\Database\Eloquent\Builder;

/**
 * ทะเบียนครัวเรือน — อ่าน/เขียนฐานข้อมูลจริงผ่าน Model Household
 *
 * ทุกเมธอดคืนค่าเป็นอาร์เรย์รูปแบบเดิม
 * ['id','hc','name','house','vill','moo','prov','dist','tam','phone','income','lat','lng']
 * จึงใช้กับหน้า Blade และ DataQualityAnalyzer เดิมได้ทันที
 */
class HouseholdRepository
{
    /** เป้าหมายจำนวนครัวเรือนที่บันทึกในปีนี้ */
    public const TARGET = 350;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(private EnrollmentRepository $enrollments) {}

    /** คิวรีพร้อมโหลดความสัมพันธ์ที่ต้องใช้แสดงผล */
    public function query(): Builder
    {
        return Household::query()->with(['tambon.district.province']);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = $this->query()
            ->orderBy('hc')
            ->get()
            ->map(fn (Household $h) => $h->toLegacyArray())
            ->all();
    }

    public function find(string $hc): ?array
    {
        foreach ($this->all() as $household) {
            if ($household['hc'] === $hc) {
                return $household;
            }
        }

        return null;
    }

    /** ดึง Model จริง (ใช้ตอนแก้ไข/ลบ) */
    public function model(string $hc): ?Household
    {
        return Household::where('hc', $hc)->first();
    }

    public function count(): int
    {
        return count($this->all());
    }

    /** @return array<int, array<string, mixed>> */
    public function withIncome(): array
    {
        return array_values(array_filter($this->all(), fn ($h) => $h['income'] !== null));
    }

    public function medianIncome(): float
    {
        $values = array_column($this->withIncome(), 'income');
        sort($values);
        $n = count($values);

        if ($n === 0) {
            return 0;
        }

        return $n % 2 ? (float) $values[intdiv($n - 1, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }

    /** @return array<int, string> */
    public function villages(): array
    {
        $out = [];

        foreach ($this->all() as $household) {
            if ($household['vill'] !== '') {
                $out[$household['vill']] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * จัดกลุ่มตามหมู่บ้าน (ตัดสระ/วรรณยุกต์ซ้ำก่อนจัดกลุ่ม)
     *
     * @return array<int, array{name:string, n:int, moo:int|null, variants:array<int,string>}>
     */
    public function byVillage(): array
    {
        $groups = [];

        foreach ($this->all() as $household) {
            $key = Thai::dedup($household['vill']) ?: '(ไม่ระบุ)';

            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => $key, 'n' => 0, 'moo' => $household['moo'], 'variants' => []];
            }

            $groups[$key]['n']++;
            $groups[$key]['variants'][trim($household['vill'])] = true;
        }

        $out = [];

        foreach ($groups as $group) {
            $group['variants'] = array_keys($group['variants']);
            $out[] = $group;
        }

        usort($out, fn ($a, $b) => $b['n'] <=> $a['n']);

        return $out;
    }

    /**
     * นับพื้นที่แบบไม่ซ้ำ — จังหวัด · อำเภอ · ตำบล · หมู่บ้าน
     *
     * หมู่บ้าน นับจาก «ชื่อหมู่บ้าน + หมู่ + ตำบล» — ชื่อหมู่บ้านและเลขหมู่ซ้ำกันได้ข้ามตำบล
     * จึงต้องมีตำบลกำกับ ไม่งั้นหมู่บ้านคนละตำบลจะถูกยุบเป็นแห่งเดียว
     * ชื่อหมู่บ้านตัดสระ/วรรณยุกต์ซ้ำก่อน (Thai::dedup) เพื่อไม่ให้สะกดต่างกันนิดเดียวกลายเป็นคนละแห่ง
     *
     * @param  array<int, array<string, mixed>>|null  $rows  ไม่ระบุ = ทั้งทะเบียน
     * @return array{prov:int, dist:int, tam:int, vill:int}
     */
    public function areaCounts(?array $rows = null): array
    {
        $bucket = ['prov' => [], 'dist' => [], 'tam' => [], 'vill' => []];

        foreach ($rows ?? $this->all() as $household) {
            $prov = trim((string) ($household['prov'] ?? ''));
            $dist = trim((string) ($household['dist'] ?? ''));
            $tam = trim((string) ($household['tam'] ?? ''));
            $vill = Thai::dedup(trim((string) ($household['vill'] ?? '')));
            $moo = trim((string) ($household['moo'] ?? ''));

            /* ไม่มีข้อมูลพื้นที่เลย → ไม่นับ ดีกว่านับเป็นพื้นที่ปลอม 1 แห่ง */
            if ($prov === '') {
                continue;
            }

            $bucket['prov'][$prov] = true;

            if ($dist === '') {
                continue;
            }

            $bucket['dist'][$prov.'›'.$dist] = true;

            if ($tam === '') {
                continue;
            }

            $bucket['tam'][$prov.'›'.$dist.'›'.$tam] = true;

            /* นับเฉพาะที่มีชื่อหมู่บ้านจริง — มีแต่เลขหมู่ไม่นับ
               เพราะยังไม่รู้ว่าเป็นหมู่บ้านไหน ถ้านับไปจะกลายเป็นหมู่บ้านลอย ๆ ในตัวเลขสรุป */
            if ($vill === '') {
                continue;
            }

            /* กุญแจหมู่บ้าน = ชื่อหมู่บ้าน + หมู่ + ตำบล */
            $bucket['vill'][$tam.'›'.$vill.'#'.$moo] = true;
        }

        return [
            'prov' => count($bucket['prov']),
            'dist' => count($bucket['dist']),
            'tam' => count($bucket['tam']),
            'vill' => count($bucket['vill']),
        ];
    }

    /**
     * รายการพื้นที่ที่มีใช้จริง สำหรับทำช่องกรอง จังหวัด/อำเภอ/ตำบล
     *
     * คืนเป็น «สายพื้นที่» ไม่ซ้ำ เรียงตามชื่อ เพื่อให้หน้าเว็บกรองต่อเป็นชั้น ๆ ได้
     * ดึงจากข้อมูลจริงเท่านั้น ตัวเลือกจึงไม่มีอันที่เลือกแล้วผลลัพธ์ว่าง
     *
     * @param  array<int, array<string, mixed>>|null  $rows  ไม่ระบุ = ทั้งทะเบียน
     * @return array<int, array{prov:string, dist:string, tam:string}>
     */
    public function areaOptions(?array $rows = null): array
    {
        $seen = [];

        foreach ($rows ?? $this->all() as $household) {
            $prov = trim((string) ($household['prov'] ?? ''));

            if ($prov === '') {
                continue;
            }

            $dist = trim((string) ($household['dist'] ?? ''));
            $tam = trim((string) ($household['tam'] ?? ''));

            $seen[$prov.'›'.$dist.'›'.$tam] = ['prov' => $prov, 'dist' => $dist, 'tam' => $tam];
        }

        $out = array_values($seen);

        usort($out, fn ($a, $b) => Thai::compare($a['prov'], $b['prov'])
            ?: (Thai::compare($a['dist'], $b['dist'])
            ?: Thai::compare($a['tam'], $b['tam'])));

        return $out;
    }

    /** @return array<string, int> */
    public function countByTambon(): array
    {
        $out = [];

        foreach ($this->all() as $household) {
            $key = Thai::nrm($household['tam']);
            $out[$key] = ($out[$key] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * กรอง + เรียงลำดับ (กรองด้วย SQL · เรียงลำดับภาษาไทยด้วย PHP)
     *
     * @param  array{q?:string,prov?:string,dist?:string,tam?:string,vill?:string,inc?:string,sort?:string,dir?:int}  $f
     * @return array<int, array<string, mixed>>
     */
    public function filter(array $f = []): array
    {
        $query = $this->query()->search($f['q'] ?? null);

        if ($prov = $f['prov'] ?? '') {
            $query->whereHas('tambon.district.province', fn (Builder $q) => $q->where('name', $prov));
        }

        if ($dist = $f['dist'] ?? '') {
            $query->whereHas('tambon.district', fn (Builder $q) => $q->where('name', $dist));
        }

        if ($tam = $f['tam'] ?? '') {
            $name = Thai::tamName($tam);
            $code = Thai::tamCode($tam);
            $query->whereHas('tambon', function (Builder $q) use ($name, $code) {
                $q->where('name', $name);

                if ($code !== null) {
                    $q->where('area_code', $code);
                }
            });
        }

        if ($vill = $f['vill'] ?? '') {
            $query->where('village_name', $vill);
        }

        $inc = $f['inc'] ?? '';

        if ($inc === 'y') {
            $query->whereNotNull('income_bl');
        } elseif ($inc === 'n') {
            $query->whereNull('income_bl');
        } elseif ($inc === 'p') {
            $query->has('enrollments');
        } elseif ($inc === 'np') {
            $query->doesntHave('enrollments');
        }

        $rows = $query->get()->map(fn (Household $h) => $h->toLegacyArray())->all();

        $sort = $f['sort'] ?? 'hc';
        $dir = ($f['dir'] ?? 1) < 0 ? -1 : 1;

        usort($rows, function ($x, $y) use ($sort, $dir) {
            if ($sort === 'income') {
                return (($x['income'] ?? -1) <=> ($y['income'] ?? -1)) * $dir;
            }

            if ($sort === 'pj') {
                $a = count($this->enrollments->forHousehold($x['hc']));
                $b = count($this->enrollments->forHousehold($y['hc']));

                return ($a <=> $b) * $dir;
            }

            return Thai::compare((string) ($x[$sort] ?? ''), (string) ($y[$sort] ?? '')) * $dir;
        });

        return $rows;
    }

    /** ออกรหัส HC ถัดไปจากรหัสพื้นที่ */
    public function nextHc(?int $code): string
    {
        return Household::nextHc($code);
    }

    /** ความสมบูรณ์ของข้อมูล 0..1 */
    public function completeness(): float
    {
        $fields = ['name', 'house', 'vill', 'moo', 'dist', 'tam', 'phone', 'income'];
        $ok = 0;

        foreach ($this->all() as $household) {
            foreach ($fields as $field) {
                if (($household[$field] ?? null) !== null && $household[$field] !== '') {
                    $ok++;
                }
            }
        }

        return $ok / max(1, $this->count() * count($fields));
    }

    /** ล้างแคชหลังบันทึกข้อมูล */
    public function forget(): void
    {
        $this->cache = null;
    }
}
