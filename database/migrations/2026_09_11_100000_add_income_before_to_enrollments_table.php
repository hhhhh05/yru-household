<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * รายได้ก่อนเข้าร่วมกิจกรรม — บันทึกไว้กับ «การเข้าร่วมครั้งนั้น»
 *
 * ทำไมไม่อ่านจาก households.income_bl ตรง ๆ เหมือนเดิม
 *   1. ครัวเรือนหนึ่งเข้าได้หลายกิจกรรม รายได้ตั้งต้นของแต่ละครั้งไม่เท่ากัน
 *      จบกิจกรรมแรกแล้วรายได้ขยับ กิจกรรมที่สองต้องเทียบกับตัวเลขใหม่
 *   2. ถ้าแก้ income_bl ของครัวเรือนภายหลัง ส่วนต่างของกิจกรรมที่ปิดไปแล้ว
 *      จะเปลี่ยนตามย้อนหลัง ทั้งที่รายงานส่งไปแล้ว
 * จึงเก็บเป็นค่า ณ วันที่เข้าร่วม (snapshot) แยกจากทะเบียนครัวเรือน
 *
 * แถวเดิมเติมย้อนหลังจาก households.income_bl ที่มีอยู่ ณ ตอนนี้
 * เป็นค่าที่ดีที่สุดเท่าที่มี — ระบบเดิมก็ใช้ตัวเลขนี้อยู่แล้ว ตัวเลขจึงไม่กระโดด
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('enrollments', 'income_before')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->decimal('income_before', 12, 2)->nullable()->after('status');
            });
        }

        /* เติมย้อนหลังเฉพาะแถวที่ยังว่าง — รันซ้ำได้ ไม่ทับค่าที่คนกรอกไว้แล้ว */
        DB::table('enrollments')
            ->join('households', 'households.id', '=', 'enrollments.household_id')
            ->whereNull('enrollments.income_before')
            ->whereNotNull('households.income_bl')
            ->update(['enrollments.income_before' => DB::raw('households.income_bl')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('enrollments', 'income_before')) {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('income_before');
        });
    }
};
