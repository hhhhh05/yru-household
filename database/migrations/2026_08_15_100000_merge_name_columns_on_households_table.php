<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * รวม prefix + first_name + last_name เป็นคอลัมน์เดียว full_name
 *
 * ลำดับสำคัญ: สร้างคอลัมน์ใหม่ → ย้ายข้อมูล → ตรวจว่าไม่มีแถวว่าง → ค่อยลบคอลัมน์เก่า
 * ถ้าย้ายไม่ครบจะหยุดกลางทางโดยยังไม่ลบอะไร เพื่อไม่ให้ข้อมูลชื่อหายถาวร
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('households', 'full_name')) {
            return;
        }

        Schema::table('households', function (Blueprint $table) {
            $table->string('full_name', 200)->nullable()->after('hc');
        });

        /* ย้ายข้อมูล: «นางสาว» + «ซูรียะห์» + ' ' + «มูซอ» → «นางสาวซูรียะห์ มูซอ» */
        $concat = DB::getDriverName() === 'sqlite'
            ? "TRIM(COALESCE(prefix,'') || COALESCE(first_name,'') || ' ' || COALESCE(last_name,''))"
            : "TRIM(CONCAT(COALESCE(prefix,''), COALESCE(first_name,''), ' ', COALESCE(last_name,'')))";

        DB::table('households')->update(['full_name' => DB::raw($concat)]);

        /* จัดรูปแบบชื่อให้เป็นมาตรฐานเดียวกันหลังต่อคอลัมน์
           จำเป็นเพราะข้อมูลเดิมมีแถวที่คำนำหน้าอ่านไม่ออก (เช่น «นาางสาว» สระซ้ำ)
           แล้วถูกใส่ค่าตั้งต้น «นาย» ไว้ในคอลัมน์ prefix พอต่อกลับจะได้
           «นายนาางสาวลาตีฟะ วาโด» — คำนำหน้าซ้อนกันสองชั้น */
        foreach (DB::table('households')->select('id', 'full_name')->get() as $row) {
            $clean = $this->normalizeName((string) $row->full_name);

            if ($clean !== $row->full_name) {
                DB::table('households')->where('id', $row->id)->update(['full_name' => $clean]);
            }
        }

        /* ด่านตรวจ — ถ้ายังมีแถวที่ชื่อว่าง แปลว่าย้ายไม่ครบ อย่าเพิ่งลบคอลัมน์เก่า */
        $empty = DB::table('households')
            ->where(function ($q) {
                $q->whereNull('full_name')->orWhere('full_name', '');
            })
            ->count();

        if ($empty > 0) {
            throw new RuntimeException(
                "ย้ายชื่อไม่ครบ: มี {$empty} แถวที่ full_name ว่าง — ".
                'คอลัมน์ prefix/first_name/last_name ยังอยู่ครบ ตรวจข้อมูลแล้วรัน migrate ใหม่ได้'
            );
        }

        /* ดัชนีเดิมอ้างคอลัมน์ที่กำลังจะลบ ต้องถอดออกก่อน */
        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex(['last_name', 'first_name']);
            $table->dropIndex('households_dup_check_index');
        });

        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'first_name', 'last_name']);
        });

        Schema::table('households', function (Blueprint $table) {
            $table->string('full_name', 200)->nullable(false)->change();
            $table->index('full_name');
            $table->index(['full_name', 'house_no'], 'households_dup_check_index');
        });
    }

    /**
     * ซ่อมสระซ้ำ แล้วลอกคำนำหน้าที่ซ้อนกันออกให้เหลือชั้นเดียว
     * «นายนาางสาวลาตีฟะ วาโด» → «นางสาวลาตีฟะ วาโด»
     */
    private function normalizeName(string $raw): string
    {
        $name = \App\Support\Thai::dedup(trim($raw));

        /* ตัดคำนำหน้าชั้นนอกทิ้ง ตราบใดที่ข้างในยังขึ้นต้นด้วยคำนำหน้าอีกตัว
           แต่ละรอบสตริงสั้นลงเสมอ จึงไม่วนไม่รู้จบ */
        while (true) {
            [$prefix] = \App\Support\Thai::splitName($name);

            if ($prefix === '') {
                break;
            }

            $rest = ltrim(mb_substr($name, mb_strlen($prefix)));

            if (! \App\Support\Thai::hasValidPrefix($rest)) {
                break;
            }

            $name = $rest;
        }

        [$prefix, $first, $last] = \App\Support\Thai::splitName($name);

        return trim($prefix.$first.' '.$last);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('households', 'full_name')) {
            return;
        }

        Schema::table('households', function (Blueprint $table) {
            $table->string('prefix', 20)->default('นาย')->after('hc');
            $table->string('first_name', 80)->nullable()->after('prefix');
            $table->string('last_name', 80)->nullable()->after('first_name');
        });

        /* แยกกลับด้วยตัวแยกชุดเดียวกับที่แอปใช้ ผลจึงตรงกับตอนกรอกฟอร์ม */
        foreach (DB::table('households')->select('id', 'full_name')->get() as $row) {
            [$prefix, $first, $last] = \App\Support\Thai::splitName((string) $row->full_name);

            DB::table('households')->where('id', $row->id)->update([
                'prefix' => $prefix ?: 'นาย',
                'first_name' => $first,
                'last_name' => $last,
            ]);
        }

        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex('households_dup_check_index');
            $table->dropIndex(['full_name']);
            $table->dropColumn('full_name');
        });

        Schema::table('households', function (Blueprint $table) {
            $table->index(['last_name', 'first_name']);
            $table->index(['first_name', 'last_name', 'house_no'], 'households_dup_check_index');
        });
    }
};
