<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ตรวจว่ามี migration ที่ยังไม่ได้รันค้างอยู่ไหม
 *
 * ใช้แสดงแถบเตือน «อัปเดตฐานข้อมูล» บนทุกหน้า เพราะผู้ใช้งานระบบนี้
 * ทำงานผ่านหน้าเว็บอย่างเดียว ไม่ได้เปิด Terminal
 */
class Migrations
{
    /**
     * รายชื่อ migration ที่ยังไม่ได้รัน
     *
     * คืนอาร์เรย์ว่างเมื่อยังต่อฐานข้อมูลไม่ได้ เพื่อไม่ให้ทั้งเว็บล่ม
     * เพราะเมธอดนี้ถูกเรียกจาก layout ของทุกหน้า
     *
     * @return array<int, string>
     */
    public static function pending(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        try {
            if (! Schema::hasTable('migrations')) {
                return $cache = [];
            }

            $ran = DB::table('migrations')->pluck('migration')->all();

            $files = array_map(
                fn ($file) => $file->getFilenameWithoutExtension(),
                File::files(database_path('migrations'))
            );

            sort($files);

            return $cache = array_values(array_diff($files, $ran));
        } catch (Throwable) {
            return $cache = [];
        }
    }

    public static function hasPending(): bool
    {
        return self::pending() !== [];
    }
}
