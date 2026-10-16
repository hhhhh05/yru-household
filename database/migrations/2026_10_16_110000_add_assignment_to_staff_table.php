<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * งานที่เจ้าหน้าที่รับผิดชอบ + สัญญาจ้าง
 *
 * program_id / activity_id — เก็บเป็นรหัสอ้างอิง ไม่ใช่ชื่อ
 *   แก้ชื่อโครงการหรือกิจกรรมที่ต้นทางที่เดียว รายชื่อเจ้าหน้าที่เปลี่ยนตามเอง
 *   กิจกรรมเว้นว่างได้ (รับผิดชอบทั้งโครงการ ไม่เจาะจงกิจกรรมเดียว)
 *
 * contract_* — ไฟล์สัญญาจ้าง เก็บชื่อไฟล์บนดิสก์แยกจากชื่อที่ผู้ใช้เห็น
 * salary     — เงินเดือนที่จ้าง (บาท/เดือน) เก็บเป็นทศนิยม 2 ตำแหน่งเหมือนงบประมาณ
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('staff', 'program_id')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedBigInteger('program_id')->nullable()->after('unit');
            $table->unsignedBigInteger('activity_id')->nullable()->after('program_id');
            $table->string('contract_file', 255)->nullable()->after('activity_id');
            $table->string('contract_name', 255)->nullable()->after('contract_file');
            $table->decimal('salary', 12, 2)->nullable()->after('contract_name');

            $table->index('program_id');
            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('staff', 'program_id')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex(['program_id']);
            $table->dropIndex(['activity_id']);
            $table->dropColumn(['program_id', 'activity_id', 'contract_file', 'contract_name', 'salary']);
        });
    }
};
