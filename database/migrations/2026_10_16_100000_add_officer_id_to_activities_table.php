<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เก็บ «เจ้าหน้าที่ผู้รับผิดชอบ» ของกิจกรรมเป็นรหัสอ้างอิงตาราง staff แทนการเก็บชื่อ
 *
 * ข้อดีของการเก็บ id: แก้ชื่อในทะเบียนเจ้าหน้าที่ที่เดียว กิจกรรมทุกรายการเปลี่ยนตามทันที
 * และไม่มีปัญหาชื่อสะกดต่างกันเล็กน้อยกลายเป็นคนละคน
 *
 * คอลัมน์ officer (ข้อความ) ยังเก็บไว้ ไม่ลบทิ้ง — เป็นค่าสำรองของแถวเก่า
 * ที่จับคู่กับรายชื่อในตาราง staff ไม่ได้ จะได้ไม่มีข้อมูลหายระหว่างเปลี่ยนผ่าน
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activities', 'officer_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->unsignedBigInteger('officer_id')->nullable()->after('officer');
                $table->index('officer_id');
            });
        }

        /* เติมย้อนหลังจากชื่อที่เคยบันทึกไว้ — เทียบแบบตัดช่องว่างทั้งหมดออก
           กันกรณีเว้นวรรคไม่เท่ากัน ทำซ้ำได้ เพราะเติมเฉพาะแถวที่ยังว่าง */
        if (! Schema::hasTable('staff') || ! Schema::hasColumn('activities', 'officer')) {
            return;
        }

        DB::statement(
            'UPDATE activities a
             JOIN staff s
               ON REPLACE(s.full_name, \' \', \'\') = REPLACE(a.officer, \' \', \'\')
              AND s.deleted_at IS NULL
             SET a.officer_id = s.id
             WHERE a.officer_id IS NULL
               AND a.officer IS NOT NULL
               AND a.officer <> \'\''
        );
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activities', 'officer_id')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['officer_id']);
            $table->dropColumn('officer_id');
        });
    }
};
