<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ไฟล์โครงการ (เอกสารโครงการฉบับเต็ม) ของโครงการหลัก
 *
 * คนละไฟล์กับ report_* ซึ่งเป็น «เล่มรายงาน» ตอนปิดโครงการ
 * ไฟล์โครงการแนบได้ตั้งแต่ต้น ไม่บังคับ
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('programs', 'doc_file')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->string('doc_file', 255)->nullable()->after('unit');
            $table->string('doc_name', 255)->nullable()->after('doc_file');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('programs', 'doc_file')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['doc_file', 'doc_name']);
        });
    }
};
