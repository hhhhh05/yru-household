<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เจ้าหน้าที่ผู้รับผิดชอบของกิจกรรม
 *
 * เก็บเป็น «ชื่อ» ไม่ใช่รหัสอ้างอิงตาราง staff
 * เพราะกิจกรรมเก่าที่บันทึกไว้ก่อนมีรายชื่อกลาง ยังต้องเก็บชื่อที่พิมพ์มาเองได้
 * และเอกสารที่พิมพ์ไปแล้วอ้างชื่อ ไม่ใช่รหัส — ลบคนออกจากรายชื่อกลาง
 * ก็ไม่ควรทำให้ผู้รับผิดชอบของกิจกรรมเก่าหายไปด้วย
 *
 * แยกจาก lecturer_name ที่มีอยู่เดิม — คนละบทบาทกัน
 * (อาจารย์ผู้รับผิดชอบทางวิชาการ ≠ เจ้าหน้าที่ผู้ดูแลกิจกรรม)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('activities', 'officer')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->string('officer', 180)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activities', 'officer')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('officer');
        });
    }
};
