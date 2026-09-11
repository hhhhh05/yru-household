<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** รายชื่อเข้าร่วมโครงการ — เชื่อมครัวเรือนกับกิจกรรม */
class Enrollment extends Model
{
    /** สถานะการดำเนินงานที่ใช้ได้ */
    public const STATUSES = ['กำลังดำเนินการ', 'สำเร็จ', 'รอเริ่ม', 'ออกกลางคัน'];

    protected $fillable = [
        'code', 'household_id', 'activity_id', 'joined_at', 'status',
        'income_before', 'income_after', 'note',
    ];

    /**
     * จดรายได้ตั้งต้นของครัวเรือนไว้กับรายการลงทะเบียน ตอนสร้างใหม่
     *
     * ทำที่นี่ (ไม่ใช่ในคอนโทรลเลอร์) เพราะมีที่สร้างรายการอยู่หลายจุด
     * — เพิ่มทีละคน · เพิ่มทีละกลุ่ม · คัดลอกไปกิจกรรมอื่น · เพิ่มพร้อมตอนบันทึกครัวเรือน
     * ถ้าไปใส่ทีละจุดจะหลุดสักจุดแน่นอน และจุดที่หลุดจะเงียบ ไม่มีใครรู้
     */
    protected static function booted(): void
    {
        static::creating(function (self $enrollment) {
            if ($enrollment->income_before !== null || ! self::hasIncomeBeforeColumn()) {
                return;
            }

            $enrollment->income_before = Household::find($enrollment->household_id)?->income_bl;
        });
    }

    /**
     * มีคอลัมน์ income_before แล้วหรือยัง (ยังไม่รัน migration ก็ต้องไม่พัง)
     * ถามฐานข้อมูลครั้งเดียวต่อ 1 request แล้วจำไว้ ไม่งั้นเพิ่มทีละ 50 คนจะยิงคำถามซ้ำ 50 รอบ
     */
    private static function hasIncomeBeforeColumn(): bool
    {
        static $has = null;

        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('enrollments', 'income_before');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * ปรับชื่อกิจกรรมให้เทียบกันได้ (ตัดช่องว่างและตัวพิมพ์เล็กใหญ่)
     * ใช้ตรวจกฎ «ปีงบเดียวกันห้ามซ้ำชื่อกิจกรรม»
     */
    public static function normalizeName(?string $name): string
    {
        return (string) preg_replace('/\s+/u', '', mb_strtolower(trim((string) $name)));
    }

    /**
     * หารายการที่ชนกฎกับกิจกรรมปลายทาง
     * — ครัวเรือนนี้อยู่ในกิจกรรม «ชื่อเดียวกัน» และ «ปีงบเดียวกัน» อยู่แล้วหรือไม่
     *   (ครอบคลุมกรณีกิจกรรมเดิมซ้ำ ๆ ด้วย เพราะชื่อและปีงบก็ตรงกันเอง)
     *
     * คืน null ถ้าเพิ่มได้
     */
    public static function conflictWith(int $householdId, Activity $activity): ?self
    {
        $target = self::normalizeName($activity->name);

        return static::with('activity')
            ->where('household_id', $householdId)
            ->whereHas('activity', fn ($q) => $q->where('fiscal_year', $activity->fiscal_year))
            ->get()
            ->first(fn (self $e) => self::normalizeName($e->activity->name ?? '') === $target);
    }

    /**
     * ครัวเรือนที่เพิ่มเข้ากิจกรรมนี้ไม่ได้ (ติดกฎชื่อกิจกรรม + ปีงบ)
     *
     * @return array<int, string>   [household_id => รหัส PA ที่ชนกฎ]
     */
    public static function blockedHouseholdIds(Activity $activity): array
    {
        $target = self::normalizeName($activity->name);
        $out = [];

        $rows = static::with('activity')
            ->whereHas('activity', fn ($q) => $q->where('fiscal_year', $activity->fiscal_year))
            ->get();

        foreach ($rows as $enrollment) {
            if (self::normalizeName($enrollment->activity->name ?? '') === $target) {
                $out[$enrollment->household_id] = $enrollment->activity->pa;
            }
        }

        return $out;
    }

    /** วันที่เข้าร่วมในรูป YYYY-MM-DD ตามที่ชีตเดิมใช้ (พ.ศ.) */
    public function joinedDate(): string
    {
        return (string) ($this->getRawOriginal('joined_at') ?? $this->joined_at ?? '');
    }

    /**
     * รหัสรายการถัดไป เช่น EN0105
     * นับจากเลขสูงสุดที่มีอยู่ (ไม่ใช้ count เพราะถ้าเคยลบรายการไปแล้วจะออกรหัสซ้ำ)
     */
    public static function nextCode(): string
    {
        $max = 0;

        foreach (static::whereNotNull('code')->pluck('code') as $code) {
            $max = max($max, (int) preg_replace('/\D/', '', (string) $code));
        }

        return 'EN'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** วันที่วันนี้ในรูปพุทธศักราช เพื่อให้ตรงรูปแบบเดียวกับข้อมูลจากชีตเดิม */
    public static function todayBuddhist(): string
    {
        $now = now();

        return ($now->year + 543).'-'.$now->format('m-d');
    }
}
