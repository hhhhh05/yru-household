<?php

namespace App\Support;

use App\Repositories\ActivityRepository;
use App\Repositories\AreaRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;

/**
 * นิยามหน้าทั้งหมดของระบบ (แทนอาร์เรย์ ROUTES ในไฟล์ HTML ต้นฉบับ)
 * ใช้ร่วมกันทั้งเมนูด้านซ้าย · breadcrumb · ชื่อหน้า · ปุ่ม "เพิ่ม" มุมขวา
 */
class Nav
{
    /** เมนูที่แสดงในแถบด้านซ้าย (ตามต้นฉบับ แสดงเพียง 4 หัวข้อหลัก) */
    public const SIDEBAR = ['home', 'hh', 'pj', 'en'];

    /** @return array<string, array<string, mixed>> */
    public static function items(): array
    {
        return [
            'home' => [
                'icon' => 'home',
                'title' => 'ภาพรวมระบบ',
                'desc' => 'สรุปสถานะฐานข้อมูลและงบประมาณ',
                'group' => '',
                'route' => 'dashboard',
            ],
            'hh' => [
                'icon' => 'users',
                'title' => 'ทะเบียนครัวเรือน',
                'desc' => 'เพิ่ม แก้ไข ค้นหา และจัดกลุ่มครัวเรือนเป้าหมาย',
                'group' => 'ข้อมูลหลัก',
                'route' => 'households.index',
                'count' => 'households',
            ],
            'pj' => [
                'icon' => 'box',
                'title' => 'โครงการ / กิจกรรม',
                'desc' => 'กิจกรรมภายใต้ยุทธศาสตร์ที่ 1 และงบประมาณรายปี',
                'group' => '',
                'route' => 'activities.index',
                'count' => 'activities',
            ],
            'en' => [
                'icon' => 'link',
                'title' => 'รายชื่อเข้าร่วมโครงการ',
                'nav' => 'รายชื่อเข้าร่วม',
                'desc' => 'จับคู่ครัวเรือนกับกิจกรรม และติดตามสถานะ',
                'group' => '',
                'route' => 'enrollments.index',
                'count' => 'enrollments',
            ],
            'ar' => [
                'icon' => 'map',
                'title' => 'ข้อมูลพื้นที่',
                'desc' => 'จังหวัด / อำเภอ / ตำบล และรหัสพื้นที่เป้าหมาย',
                'group' => '',
                'route' => 'areas.index',
                'count' => 'areas',
            ],
            'st' => [
                'icon' => 'users',
                'title' => 'เจ้าหน้าที่รับผิดชอบ',
                'nav' => 'เจ้าหน้าที่',
                'desc' => 'รายชื่อกลางของหน่วยงาน ใช้อ้างอิงในเอกสารและการติดต่อ',
                'group' => '',
                'route' => 'staff.index',
                'count' => 'staff',
            ],
            'un' => [
                'icon' => 'box',
                'title' => 'คณะ / หน่วยงาน',
                'nav' => 'คณะ / หน่วยงาน',
                'desc' => 'รายชื่อคณะและหน่วยงาน ใช้เป็นตัวเลือกในหน้าเจ้าหน้าที่',
                'group' => '',
                'route' => 'units.index',
                'count' => 'units',
            ],
            'dq' => [
                'icon' => 'shield',
                'title' => 'ตรวจสอบคุณภาพข้อมูล',
                'desc' => 'รายการที่ควรแก้ไขก่อนนำข้อมูลไปใช้',
                'group' => 'เครื่องมือ',
                'route' => 'quality.index',
                'count' => 'issues',
                'warn' => true,
            ],
            'io' => [
                'icon' => 'swap',
                'title' => 'นำเข้า / ส่งออกข้อมูล',
                'desc' => 'ซิงก์กับ Google Sheet และส่งออกเป็นไฟล์',
                'group' => '',
                'route' => 'io.index',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function find(string $key): array
    {
        return self::items()[$key] ?? self::items()['home'];
    }

    /** ตัวเลขท้ายเมนู */
    public static function count(?string $kind): ?int
    {
        return match ($kind) {
            'households' => count(app(HouseholdRepository::class)->all()),
            'activities' => count(app(ActivityRepository::class)->all()),
            'enrollments' => count(app(EnrollmentRepository::class)->all()),
            'areas' => count(app(AreaRepository::class)->all()),
            /* ตารางอาจยังไม่ถูกสร้าง (ยังไม่ได้รัน migration) — เมนูต้องไม่พัง */
            'staff' => \Illuminate\Support\Facades\Schema::hasTable('staff')
                ? \App\Models\Staff::count()
                : null,
            'units' => \Illuminate\Support\Facades\Schema::hasTable('units')
                ? \App\Models\Unit::count()
                : null,
            'issues' => count(app(DataQualityAnalyzer::class)->issues()),
            default => null,
        };
    }
}
