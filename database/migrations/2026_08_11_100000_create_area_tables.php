<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ชุดข้อมูลพื้นที่ — แยกเป็น 4 ชั้น จังหวัด → อำเภอ → ตำบล → หมู่บ้าน
 *
 * เหตุผลที่ต้องแยกตาราง: ในชีตเดิมเก็บชื่ออำเภอ/ตำบลเป็นข้อความในแถวเดียวกับครัวเรือน
 * ทำให้เกิดปัญหา «อำเภอไม่ตรงกับตำบล» 18 ครัวเรือน และชื่อสะกดต่างกันระหว่างชีต
 * เมื่อครัวเรือนอ้างอิง tambon_id ปัญหานี้จะเกิดขึ้นไม่ได้อีกในเชิงโครงสร้าง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();          // ยะลา · ปัตตานี · นราธิวาส
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);                    // ชื่ออำเภอ (ไม่ต้องมีคำว่า "อ.")
            $table->timestamps();

            $table->unique(['province_id', 'name']);
        });

        Schema::create('tambons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);                    // ชื่อตำบล
            $table->unsignedSmallInteger('area_code')->nullable();  // รหัสพื้นที่เป้าหมาย 1–99
            $table->timestamps();

            $table->unique(['district_id', 'name']);
            $table->index('area_code');
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            /* nullable เพราะข้อมูลเดิมมี 3 ครัวเรือนที่ยังไม่ระบุตำบล */
            $table->foreignId('tambon_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);                    // ชื่อหมู่บ้าน / ชุมชน
            $table->unsignedTinyInteger('moo')->nullable(); // หมู่ที่
            $table->timestamps();

            $table->unique(['tambon_id', 'name', 'moo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
        Schema::dropIfExists('tambons');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
