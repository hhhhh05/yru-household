<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** กิจกรรมภายใต้โครงการหลัก */
class Activity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pa', 'program_id', 'fiscal_year', 'name', 'budget', 'target_households', 'unit',
        'lecturer_name', 'lecturer_phone', 'lecturer_id_card', 'workload_per_week', 'description',
    ];

    /*
    | หมายเหตุความเป็นส่วนตัว: ถ้าต้องการเข้ารหัสเลขประจำตัวประชาชนในฐานข้อมูล
    | เพิ่มบรรทัดนี้ใน $casts →  'lecturer_id_card' => 'encrypted',
    | (ค้นหาด้วยเลขบัตรจะทำไม่ได้อีก และต้องเก็บ APP_KEY ให้ดี ไม่งั้นอ่านข้อมูลเดิมไม่ออก)
    */

    protected $casts = [
        'fiscal_year' => 'integer',
        'budget' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return array<string, mixed> รูปแบบเดียวกับชุดข้อมูลเดิม */
    public function toLegacyArray(): array
    {
        return [
            'id' => $this->id,
            'pa' => $this->pa,
            'name' => $this->name,
            'budget' => (int) $this->budget,
            'fy' => $this->fiscal_year,
            'program' => $this->program->name ?? '',
            'program_id' => $this->program_id,
            'target' => $this->target_households,
            'unit' => $this->unit,
            'lecturer' => $this->lecturer_name,
            'lecturer_phone' => $this->lecturer_phone,
            'lecturer_id_card' => $this->lecturer_id_card,
            'workload' => $this->workload_per_week,
            'description' => $this->description,
        ];
    }

    /**
     * ออกรหัส PA ถัดไปของปีงบนั้น
     *
     * ใช้ «ลำดับสูงสุดที่มีอยู่ + 1» ไม่ใช่ «จำนวนแถว + 1»
     * เพราะถ้าลำดับมีช่องว่าง (เช่นเคยลบทิ้ง หรือ import มาไม่ครบ)
     * การนับจำนวนแถวจะออกรหัสที่มีอยู่แล้วซ้ำ → ชน unique ของคอลัมน์ pa
     * และวนหาต่อจนได้รหัสที่ยังว่างจริง เผื่อมีรหัสข้ามปีปนอยู่
     */
    public static function nextPa(int $fiscalYear): string
    {
        $prefix = 'LP'.mb_substr((string) $fiscalYear, -2);

        /* ลำดับสูงสุดของรหัสที่ขึ้นต้นด้วย prefix นี้ (นับรวมที่ลบแบบ soft delete) */
        $max = 0;

        foreach (static::withTrashed()->where('pa', 'like', $prefix.'%')->pluck('pa') as $pa) {
            $max = max($max, (int) mb_substr((string) $pa, mb_strlen($prefix)));
        }

        /* กันชนซ้ำอีกชั้น — เดินหาลำดับที่ยังว่างจริง */
        do {
            $max++;
            $candidate = $prefix.str_pad((string) $max, 3, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('pa', $candidate)->exists());

        return $candidate;
    }
}
