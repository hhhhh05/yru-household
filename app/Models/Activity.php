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
     * ออกรหัส PA ถัดไป — รูปแบบ LP + ปีงบ 2 หลัก + ลำดับโครงการ 2 หลัก + ลำดับกิจกรรม 2 หลัก
     * ตัวอย่าง LP690101 = ปีงบ 2569 · โครงการหลักลำดับที่ 1 · กิจกรรมลำดับที่ 1
     *
     * ลำดับกิจกรรมนับต่อเนื่องทั้งปีงบ (ไม่เริ่มใหม่ทุกโครงการ) เลขท้ายจึงไม่ซ้ำกันทั้งปี
     *
     * ใช้ «ลำดับสูงสุดที่มีอยู่ + 1» ไม่ใช่ «จำนวนแถว + 1» เพราะถ้าลำดับมีช่องว่าง
     * (เคยลบทิ้ง หรือ import มาไม่ครบ) การนับจำนวนแถวจะออกรหัสที่มีอยู่แล้วซ้ำ
     */
    public static function nextPa(int $fiscalYear, ?int $programId = null): string
    {
        $yearPrefix = 'LP'.mb_substr((string) $fiscalYear, -2);
        $programSeq = str_pad((string) self::programSequence($fiscalYear, $programId), 2, '0', STR_PAD_LEFT);

        /* ลำดับกิจกรรมสูงสุดของปีงบนี้ นับรวมทุกโครงการและรวมที่ลบแบบ soft delete */
        $max = 0;

        foreach (static::withTrashed()->where('pa', 'like', $yearPrefix.'%')->pluck('pa') as $pa) {
            $max = max($max, self::activitySequence((string) $pa, $yearPrefix));
        }

        /* กันชนซ้ำอีกชั้น — เดินหาลำดับที่ยังว่างจริง */
        do {
            $max++;
            $candidate = $yearPrefix.$programSeq.str_pad((string) $max, 2, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('pa', $candidate)->exists());

        return $candidate;
    }

    /**
     * โครงการหลักนี้เป็นลำดับที่เท่าไรของปีงบนั้น (เรียงตามลำดับที่สร้าง)
     * ไม่ระบุโครงการ หรือหาไม่เจอ → คืน 0 เพื่อให้เห็นชัดว่ายังไม่ได้ผูกโครงการ
     */
    private static function programSequence(int $fiscalYear, ?int $programId): int
    {
        if (! $programId) {
            return 0;
        }

        $ids = Program::where('fiscal_year', $fiscalYear)->orderBy('id')->pluck('id')->all();
        $position = array_search($programId, $ids, true);

        return $position === false ? 0 : $position + 1;
    }

    /**
     * อ่านลำดับกิจกรรมออกจากรหัส PA — รองรับทั้งรูปแบบเก่าและใหม่
     *   เก่า  LP69001   → ส่วนท้าย 3 หลัก = 1
     *   ใหม่  LP690101  → ส่วนท้าย 2 หลัก = 1
     * จำเป็นเพราะฐานข้อมูลมีรหัสรูปแบบเก่าอยู่แล้ว ถ้าอ่านผิดจะออกรหัสใหม่ทับของเดิม
     */
    private static function activitySequence(string $pa, string $yearPrefix): int
    {
        $tail = mb_substr($pa, mb_strlen($yearPrefix));

        if (! ctype_digit($tail)) {
            return 0;
        }

        /* 4 หลัก = รูปแบบใหม่ (โครงการ 2 + กิจกรรม 2) · นอกนั้นถือเป็นรูปแบบเก่า */
        return (int) (mb_strlen($tail) === 4 ? mb_substr($tail, -2) : $tail);
    }
}
