<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** โครงการหลักตามปีงบประมาณ */
class Program extends Model
{
    public const STATUS_OPEN = 'กำลังดำเนินการ';

    public const STATUS_CLOSED = 'ปิดโครงการ';

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_CLOSED];

    protected $fillable = ['fiscal_year', 'name', 'lead_name', 'unit', 'doc_file', 'doc_name', 'status', 'report_file', 'report_name', 'closed_at'];

    protected $casts = ['fiscal_year' => 'integer'];

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** ที่อยู่ไฟล์เล่มรายงานบนดิสก์ (public/uploads/program-reports) */
    public function reportPath(): ?string
    {
        return $this->report_file
            ? public_path('uploads/program-reports/'.$this->report_file)
            : null;
    }

    /** ที่อยู่ไฟล์โครงการบนดิสก์ (public/uploads/program-docs) */
    public function docPath(): ?string
    {
        return $this->doc_file
            ? public_path('uploads/program-docs/'.$this->doc_file)
            : null;
    }

    /** วันที่ปิดโครงการแบบ พ.ศ. (เหมือนวันที่อื่นในระบบ เก็บเป็น พ.ศ. ในคอลัมน์ DATE) */
    public static function todayBuddhist(): string
    {
        $now = now();

        return ($now->year + 543).'-'.$now->format('m-d');
    }
}
