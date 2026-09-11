<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เจ้าหน้าที่รับผิดชอบ — รายชื่อกลางของหน่วยงาน
 *
 * เป็นรายชื่ออิสระ ไม่ผูกกับตำบลหรือกิจกรรมใด ๆ
 * ถ้าภายหลังต้องการผูกกับพื้นที่ ค่อยเพิ่มคอลัมน์ทีหลังได้โดยไม่ต้องย้ายข้อมูล
 *
 * ใช้ soft delete เพราะรายชื่อคนมักถูกอ้างถึงในเอกสารที่พิมพ์ไปแล้ว
 * ลบพลาดแล้วกู้คืนได้ ไม่หายถาวร
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff')) {
            return;
        }

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 180);
            $table->string('position', 120)->nullable();     // ตำแหน่ง
            $table->string('unit', 180)->nullable();         // คณะ/หน่วยงาน
            $table->string('phone', 30)->nullable();
            $table->string('email', 180)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
