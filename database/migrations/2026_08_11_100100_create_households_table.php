<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ทะเบียนครัวเรือน — ตารางหลักของระบบ
 *
 * hc = รหัสครัวเรือน PY + ปีงบ 2 หลัก + รหัสพื้นที่ 2 หลัก + ลำดับ 5 หลัก
 *      เป็นคีย์ธุรกิจ (business key) ที่หน่วยงานใช้อ้างอิงกันจริง จึงตั้ง unique ไว้
 *      แต่ยังใช้ id เป็น primary key เพื่อให้แก้รหัส HC ภายหลังได้โดยไม่กระทบตารางลูก
 *
 * ชื่อแยกเป็น 3 ส่วน (คำนำหน้า / ชื่อ / นามสกุล) เพื่อค้นหาและตรวจคำนำหน้าได้ถูกต้อง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('hc', 20)->unique();

            /* ---- ชื่อ ---- */
            $table->string('prefix', 20)->default('นาย');   // นาย/นาง/นางสาว/เด็กชาย/เด็กหญิง
            $table->string('first_name', 80);
            $table->string('last_name', 80);

            /* ---- ที่อยู่ ---- */
            $table->string('house_no', 30);
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tambon_id')->nullable()->constrained()->nullOnDelete();

            /* ---- ติดต่อ / เศรษฐฐานะ ---- */
            $table->string('phone', 20)->nullable();
            $table->decimal('income_bl', 12, 2)->nullable();   // รายได้ baseline บาท/ปี

            /* ---- พิกัดที่ตั้ง ---- */
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            /* ---- ข้อมูลประกอบ ---- */
            $table->string('source', 100)->nullable();          // แหล่งอ้างอิงข้อมูล
            $table->string('status', 30)->default('อยู่ในเป้าหมาย');
            $table->text('note')->nullable();

            /* ---- ผู้บันทึก (ต่อยอดเมื่อมีระบบล็อกอิน) ---- */
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();      // ลบแล้วยังกู้คืนได้ — ข้อมูลราชการไม่ควรหายถาวร

            $table->index(['last_name', 'first_name']);
            $table->index(['tambon_id', 'village_id']);
            /* ใช้ตรวจครัวเรือนซ้ำ: ชื่อ-สกุล + บ้านเลขที่ ไม่ควรซ้ำกัน */
            $table->index(['first_name', 'last_name', 'house_no'], 'households_dup_check_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
