<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** เจ้าหน้าที่รับผิดชอบ — รายชื่อกลาง */
class Staff extends Model
{
    use SoftDeletes;

    /* ชื่อตารางระบุเอง — Laravel จะเดาเป็น "staves" ตามกฎพหูพจน์อังกฤษ */
    protected $table = 'staff';

    protected $fillable = [
        'full_name', 'position', 'unit',
        'program_id', 'activity_id', 'contract_file', 'contract_name', 'salary',
        'phone', 'email',
    ];

    protected $casts = ['salary' => 'decimal:2'];

    /** โครงการหลักที่รับผิดชอบ */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** กิจกรรมที่รับผิดชอบ — เว้นว่างได้ ถ้าดูแลทั้งโครงการ */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * ความครบถ้วนของข้อมูลเจ้าหน้าที่คนนี้ (เป็นเปอร์เซ็นต์)
     *
     * ไม่นับ «กิจกรรม» เพราะตั้งใจให้เว้นว่างได้ (ดูแลทั้งโครงการ)
     * ถ้านับด้วย คนที่กรอกครบจริงจะไม่มีทางได้ 100%
     *
     * @param  bool  $jobReady  ฐานข้อมูลมีคอลัมน์งาน/สัญญา/เงินเดือนแล้วหรือยัง
     * @return array{filled:int, total:int, pct:int, missing:array<int, string>}
     */
    public function completeness(bool $jobReady = true): array
    {
        $has = fn ($v) => $v !== null && trim((string) $v) !== '';

        $checks = [
            'ชื่อ - สกุล' => $has($this->full_name),
            'ตำแหน่ง' => $has($this->position),
            'คณะ / หน่วยงาน' => $has($this->unit),
            'เบอร์โทร' => $has($this->phone),
            'อีเมล' => $has($this->email),
        ];

        if ($jobReady) {
            $checks['โครงการที่รับผิดชอบ'] = $has($this->program_id);
            $checks['เงินเดือนที่จ้าง'] = $this->salary !== null;
            $checks['สัญญาจ้าง'] = $has($this->contract_file);
        }

        $filled = count(array_filter($checks));
        $total = count($checks);

        return [
            'filled' => $filled,
            'total' => $total,
            'pct' => (int) round($filled / max(1, $total) * 100),
            'missing' => array_keys(array_filter($checks, fn ($ok) => ! $ok)),
        ];
    }

    /** ที่อยู่ไฟล์สัญญาจ้างบนดิสก์ (public/uploads/staff-contracts) */
    public function contractPath(): ?string
    {
        return $this->contract_file
            ? public_path('uploads/staff-contracts/'.$this->contract_file)
            : null;
    }
}
