<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * รายได้หลังเข้าร่วมกิจกรรม — บันทึกตอนเปลี่ยนสถานะเป็น «สำเร็จ»
 *
 * เก็บแยกจาก households.income_bl (รายได้ตั้งต้นของครัวเรือน) เพราะ
 * ครัวเรือนหนึ่งเข้าร่วมได้หลายกิจกรรม รายได้หลังจบจึงเป็นของ «การเข้าร่วมครั้งนั้น»
 * ไม่ใช่ของครัวเรือน ถ้าเก็บรวมกันจะทับกันเองเมื่อจบกิจกรรมที่สอง
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('enrollments', 'income_after')) {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->decimal('income_after', 12, 2)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('enrollments', 'income_after')) {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('income_after');
        });
    }
};
