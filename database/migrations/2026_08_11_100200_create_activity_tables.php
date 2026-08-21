<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * โครงการหลัก (programs) → กิจกรรม (activities) → รายชื่อเข้าร่วม (enrollments)
 *
 * enrollments เป็นตารางเชื่อม household ↔ activity แบบ many-to-many
 * แต่มีข้อมูลของตัวเอง (วันที่เข้าร่วม · สถานะ · หมายเหตุ) จึงทำเป็นตารางเต็มรูป
 * unique(household_id, activity_id) กันการลงทะเบียนซ้ำในกิจกรรมเดียวกัน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');        // ปีงบประมาณ พ.ศ.
            $table->string('name', 255);
            $table->timestamps();

            $table->unique(['fiscal_year', 'name'], 'programs_year_name_unique');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('pa', 20)->unique();                 // LP + ปีงบ 2 หลัก + ลำดับ 3 หลัก
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('name', 500);
            $table->decimal('budget', 14, 2)->default(0);
            $table->unsignedSmallInteger('target_households')->nullable();
            $table->string('unit', 150)->nullable();            // หน่วยงานรับผิดชอบ
            $table->timestamps();
            $table->softDeletes();

            $table->index('fiscal_year');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();   // EN0001 (รหัสจากชีตเดิม)
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->date('joined_at')->nullable();              // วันที่เข้าร่วม — เก็บตามชีตเดิมเป็น พ.ศ. (เช่น 2568-11-04)
            $table->string('status', 30)->default('รอเริ่ม');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['household_id', 'activity_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('programs');
    }
};
