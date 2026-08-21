<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่มข้อมูลอาจารย์ผู้รับผิดชอบกิจกรรม + คำอธิบายโครงการ
 *
 * เก็บเลขประจำตัวประชาชนเป็นตัวเลข 13 หลัก (ไม่มีขีด) เพื่อค้นหา/ตรวจซ้ำได้
 * ถ้าต้องการเข้ารหัสข้อมูลนี้ในฐานข้อมูล ดูหมายเหตุใน app/Models/Activity.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('lecturer_name', 150)->nullable()->after('unit');
            $table->string('lecturer_phone', 20)->nullable()->after('lecturer_name');
            $table->string('lecturer_id_card', 13)->nullable()->after('lecturer_phone');
            $table->text('description')->nullable()->after('lecturer_id_card');

            $table->index('lecturer_name');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['lecturer_name']);
            $table->dropColumn(['lecturer_name', 'lecturer_phone', 'lecturer_id_card', 'description']);
        });
    }
};
