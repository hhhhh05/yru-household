<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ภาระงานของอาจารย์ผู้รับผิดชอบ หน่วยเป็น «ชั่วโมงต่อสัปดาห์»
 *
 * ใช้ decimal(4,1) เพราะภาระงานมักลงท้ายด้วยครึ่งชั่วโมง เช่น 7.5
 * ถ้าเก็บเป็นจำนวนเต็มจะปัดทิ้งโดยไม่มีใครรู้
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('activities', 'workload_per_week')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('workload_per_week', 4, 1)->nullable()->after('lecturer_id_card');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activities', 'workload_per_week')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('workload_per_week');
        });
    }
};
