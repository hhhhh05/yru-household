<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * คณะ / หน่วยงาน — รายชื่อกลาง ใช้เป็นตัวเลือกในหน้าเจ้าหน้าที่
 *
 * เก็บชื่อเป็นข้อความตรง ๆ ไม่ผูก foreign key กับตารางอื่น
 * เพราะข้อมูลกิจกรรมเดิมเก็บชื่อคณะเป็นข้อความอยู่แล้ว
 * ถ้าบังคับผูกกัน ข้อมูลเก่าที่สะกดไม่ตรงจะบันทึกไม่ได้ทั้งแถว
 *
 * ใช้ soft delete — ลบชื่อคณะออกจากรายการตัวเลือก
 * ไม่ควรทำให้ชื่อที่เจ้าหน้าที่บันทึกไว้แล้วหายไปด้วย
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('units')) {
            return;
        }

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('short_name', 60)->nullable();   // ชื่อย่อ เช่น วจก.
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
