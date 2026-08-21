<?php

namespace App\Models;

use App\Support\Thai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ครัวเรือนในทะเบียน (ตาราง households)
 */
class Household extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hc', 'full_name', 'house_no',
        'village_name', 'moo', 'tambon_id', 'phone', 'income_bl', 'lat', 'lng',
        'source', 'status', 'note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'income_bl' => 'decimal:2',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    /* ------------------------------------------------------ ความสัมพันธ์ ---- */

    public function tambon(): BelongsTo
    {
        return $this->belongsTo(Tambon::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /* ----------------------------------------------------------- ตัวช่วย ---- */

    /** ชื่อเต็ม เช่น "นางสาวซูรียะห์ มูซอ" — เก็บเป็นคอลัมน์เดียว full_name */
    public function fullName(): string
    {
        return (string) $this->full_name;
    }

    /** ตำบลพร้อมรหัสในรูปแบบเดิม "ปุโรง(10)" */
    public function tambonLabel(): string
    {
        if (! $this->tambon) {
            return '';
        }

        return $this->tambon->name.'('.$this->tambon->area_code.')';
    }

    /**
     * แปลงเป็นอาร์เรย์รูปแบบเดียวกับชุดข้อมูลเดิม
     * เพื่อให้หน้า Blade และตัวตรวจคุณภาพข้อมูลใช้งานได้โดยไม่ต้องแก้
     *
     * @return array<string, mixed>
     */
    public function toLegacyArray(): array
    {
        $tambon = $this->tambon;
        $district = $tambon?->district;

        return [
            'id' => $this->id,
            'hc' => $this->hc,
            'name' => $this->fullName(),
            'house' => $this->house_no,
            'vill' => (string) $this->village_name,
            'moo' => $this->moo,
            'prov' => $district->province->name ?? '',
            'dist' => $district->name ?? '',
            'tam' => $this->tambonLabel(),
            'phone' => (string) $this->phone,
            'income' => $this->income_bl === null ? null : (int) $this->income_bl,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
            'source' => $this->source,
            'note' => $this->note,
        ];
    }

    /* ------------------------------------------------------------- scope ---- */

    /** ค้นหาจาก HC · ชื่อ · บ้านเลขที่ · เบอร์โทร · หมู่บ้าน · ตำบล */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('hc', 'like', $like)
                ->orWhere('full_name', 'like', $like)
                ->orWhere('house_no', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('village_name', 'like', $like)
                ->orWhereHas('tambon', fn (Builder $t) => $t->where('name', 'like', $like));
        });
    }

    /** ออกรหัส HC ถัดไปจากรหัสพื้นที่ เช่น 10 → PY671000105 */
    public static function nextHc(?int $areaCode, int $fiscalYear = 67): string
    {
        $max = 0;

        foreach (static::withTrashed()->pluck('hc') as $hc) {
            $max = max($max, (int) mb_substr((string) $hc, 6));
        }

        return 'PY'.$fiscalYear
            .str_pad((string) ($areaCode ?? 0), 2, '0', STR_PAD_LEFT)
            .str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * จดชื่อหมู่บ้านเข้าคลังคำแนะนำ (ตาราง villages)
     *
     * ชื่อที่ใช้จริงอยู่ในคอลัมน์ households.village_name แล้ว
     * ตาราง villages เหลือหน้าที่เดียวคือเป็นคลังคำให้ตัวช่วยกรองตอนพิมพ์
     * จึงยิ่งใช้ยิ่งมีชื่อให้เลือกครบขึ้นเอง
     */
    public static function rememberVillage(?int $tambonId, ?string $name, ?int $moo): void
    {
        $name = Thai::dedup((string) $name);

        if (! $tambonId || $name === '') {
            return;
        }

        Village::firstOrCreate(['tambon_id' => $tambonId, 'name' => $name, 'moo' => $moo]);
    }

    /**
     * ชื่อหมู่บ้านสำหรับตัวช่วยกรองตอนพิมพ์ แยกตามตำบล
     *
     * รวมสองแหล่ง: ชื่อที่เคยกรอกจริงในทะเบียน + คลังคำในตาราง villages
     *
     * @return array<int, array<int, string>> [tambon_id => [ชื่อหมู่บ้าน, ...]]
     */
    public static function villageSuggestions(): array
    {
        $out = [];

        foreach (static::withTrashed()->whereNotNull('tambon_id')->get(['tambon_id', 'village_name']) as $household) {
            $name = trim((string) $household->village_name);

            if ($name !== '') {
                $out[$household->tambon_id][$name] = true;
            }
        }

        foreach (Village::whereNotNull('tambon_id')->get(['tambon_id', 'name']) as $village) {
            $name = trim((string) $village->name);

            if ($name !== '') {
                $out[$village->tambon_id][$name] = true;
            }
        }

        foreach ($out as $tambonId => $names) {
            $list = array_keys($names);
            usort($list, fn ($a, $b) => Thai::compare($a, $b));
            $out[$tambonId] = $list;
        }

        return $out;
    }
}
