<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผู้รับผิดชอบของโครงการหลัก
 *
 * lead_name — อาจารย์หัวหน้าโครงการ (คนละระดับกับ «อาจารย์ที่รับผิดชอบ» ของกิจกรรมย่อย)
 * unit      — คณะ/หน่วยงานที่รับผิดชอบโครงการ เก็บเป็นชื่อ ไม่ผูก foreign key
 *             เหมือนที่กิจกรรมทำอยู่ ข้อมูลเก่าที่สะกดต่างกันจึงยังบันทึกได้
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('programs', 'lead_name')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->string('lead_name', 180)->nullable()->after('name');
            $table->string('unit', 180)->nullable()->after('lead_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('programs', 'lead_name')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['lead_name', 'unit']);
        });
    }
};
