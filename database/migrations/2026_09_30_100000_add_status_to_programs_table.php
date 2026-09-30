<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สถานะโครงการหลัก + เล่มรายงานตอนปิดโครงการ
 *
 * status   — «กำลังดำเนินการ» หรือ «ปิดโครงการ»
 * report_* — ไฟล์ PDF เล่มรายงาน ที่ต้องแนบตอนปิดโครงการ
 *            เก็บชื่อไฟล์จริงบนดิสก์ (report_file) แยกจากชื่อที่ผู้ใช้เห็น (report_name)
 *            เพราะชื่อไฟล์ภาษาไทยที่ผู้ใช้ตั้ง ใช้เป็นชื่อไฟล์บนดิสก์ตรง ๆ ไม่ปลอดภัย
 * closed_at — วันที่ปิดโครงการ (พ.ศ. เหมือนวันที่อื่นในระบบ)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('programs', 'status')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->string('status', 30)->default('กำลังดำเนินการ')->after('name');
            $table->string('report_file', 255)->nullable()->after('status');
            $table->string('report_name', 255)->nullable()->after('report_file');
            $table->date('closed_at')->nullable()->after('report_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('programs', 'status')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['status', 'report_file', 'report_name', 'closed_at']);
        });
    }
};
