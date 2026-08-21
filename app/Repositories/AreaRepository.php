<?php

namespace App\Repositories;

use App\Models\Province;
use App\Models\Tambon;
use App\Support\Thai;

/**
 * ชุดข้อมูลพื้นที่ — อ่านจากฐานข้อมูล (provinces / districts / tambons)
 *
 * คืนค่าเป็นอาร์เรย์รูปแบบเดิม ['id','prov','dist','tam','code']
 * เพื่อให้หน้า Blade และตัวตรวจคุณภาพข้อมูลใช้งานได้โดยไม่ต้องแก้
 */
class AreaRepository
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $cache = null;

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = [];

        $tambons = Tambon::with('district.province')
            ->orderBy('district_id')
            ->orderBy('area_code')
            ->get();

        foreach ($tambons as $tambon) {
            $rows[] = [
                'id' => $tambon->id,
                'prov' => $tambon->district->province->name ?? '',
                'dist' => $tambon->district->name ?? '',
                'tam' => $tambon->name,
                'code' => $tambon->area_code,
            ];
        }

        return $this->cache = $rows;
    }

    public function find(int $id): ?array
    {
        foreach ($this->all() as $area) {
            if ($area['id'] === $id) {
                return $area;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function provinces(): array
    {
        return Province::orderBy('id')->pluck('name')->all();
    }

    /** @return array<int, string> */
    public function districtsIn(?string $prov = null): array
    {
        $out = [];

        foreach ($this->all() as $area) {
            if (! $prov || $area['prov'] === $prov) {
                $out[$area['dist']] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * รายชื่อตำบลพร้อมรหัสในรูป "ปุโรง(10)"
     *
     * @return array<int, string>
     */
    public function tambonsIn(?string $prov = null, ?string $dist = null): array
    {
        $out = [];

        foreach ($this->all() as $area) {
            $okProv = ! $prov || $area['prov'] === $prov;
            $okDist = ! $dist || Thai::nrm($area['dist']) === Thai::nrm($dist);

            if ($okProv && $okDist) {
                $out[$area['tam'].'('.$area['code'].')'] = true;
            }
        }

        return array_keys($out);
    }

    /** แปลงข้อความ "ปุโรง(10)" เป็น tambon_id */
    public function idFromLabel(?string $label): ?int
    {
        if (! $label) {
            return null;
        }

        $name = Thai::dedup(Thai::tamName($label));
        $code = Thai::tamCode($label);

        foreach ($this->all() as $area) {
            if ($area['tam'] === $name && (string) $area['code'] === (string) $code) {
                return $area['id'];
            }
        }

        foreach ($this->all() as $area) {
            if ($area['tam'] === $name) {
                return $area['id'];
            }
        }

        return null;
    }

    /**
     * ป้ายตำบลใน dropdown ("ปุโรง(10)") → tambon_id
     * ใช้ให้ฝั่ง JS จับคู่ตำบลที่เลือกกับรายชื่อหมู่บ้านของตำบลนั้น
     *
     * @return array<string, int>
     */
    public function labelToId(): array
    {
        $out = [];

        foreach ($this->all() as $area) {
            $out[$area['tam'].'('.$area['code'].')'] = $area['id'];
            $out[$area['tam']] ??= $area['id'];
        }

        return $out;
    }

    /** @return array<string, string> ตำบล → อำเภอ */
    public function tamToDist(): array
    {
        $map = [];

        foreach ($this->all() as $area) {
            if ($area['code'] !== 99) {
                $map[$area['tam']] = Thai::nrm($area['dist']);
            }
        }

        return $map;
    }

    public function tambonCount(): int
    {
        return count(array_unique(array_column($this->all(), 'tam')));
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function groupedByProvince(): array
    {
        $out = [];

        foreach ($this->all() as $area) {
            $out[$area['prov']][] = $area;
        }

        return $out;
    }
}
