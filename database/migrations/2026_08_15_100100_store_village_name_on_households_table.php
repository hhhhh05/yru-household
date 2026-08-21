<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เก็บ «หมู่บ้าน/ชุมชน» เป็นชื่อบนตารางครัวเรือน แทนการอ้าง village_id
 *
 * ตาราง villages ไม่ถูกลบ แต่เปลี่ยนหน้าที่เป็นคลังคำสำหรับตัวช่วยกรองตอนพิมพ์
 *
 * เขียนแบบ «ทำต่อจากจุดที่ค้างได้» — ทุกขั้นตอนตรวจสถานะก่อนทำ
 * จึงรันซ้ำได้ปลอดภัยแม้รอบก่อนจะล้มกลางทาง
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ---- 1. คอลัมน์ใหม่ ---- */
        if (! Schema::hasColumn('households', 'village_name')) {
            Schema::table('households', function (Blueprint $table) {
                $table->string('village_name', 150)->nullable()->after('house_no');
            });
        }

        if (! Schema::hasColumn('households', 'moo')) {
            Schema::table('households', function (Blueprint $table) {
                $table->unsignedTinyInteger('moo')->nullable()->after('village_name');
            });
        }

        /* ---- 2. ย้ายชื่อหมู่บ้าน + หมู่ที่ จากตาราง villages ---- */
        if (Schema::hasColumn('households', 'village_id')) {
            foreach (DB::table('villages')->select('id', 'name', 'moo')->get() as $village) {
                DB::table('households')
                    ->where('village_id', $village->id)
                    ->whereNull('village_name')
                    ->update(['village_name' => $village->name, 'moo' => $village->moo]);
            }

            /* ด่านตรวจ — แถวที่เคยมี village_id ต้องได้ชื่อครบทุกแถวก่อนลบของเก่า */
            $lost = DB::table('households')
                ->whereNotNull('village_id')
                ->where(function ($q) {
                    $q->whereNull('village_name')->orWhere('village_name', '');
                })
                ->count();

            if ($lost > 0) {
                throw new RuntimeException(
                    "ย้ายชื่อหมู่บ้านไม่ครบ: {$lost} แถว — village_id ยังอยู่ครบ ตรวจข้อมูลแล้วรัน migrate ใหม่ได้"
                );
            }
        }

        /* ---- 3. ดัชนีของคอลัมน์ใหม่ ---- */
        if (! $this->hasIndex('households_village_name_index')) {
            Schema::table('households', function (Blueprint $table) {
                $table->index('village_name');
            });
        }

        /* ---- 4. ดัชนีทดแทนของ tambon_id — ต้องสร้าง «ก่อน» ลบดัชนีคู่ ----
           ดัชนีคู่ (tambon_id, village_id) ถูกใช้รองรับ foreign key ของ tambon_id ด้วย
           เพราะ tambon_id เป็นคอลัมน์แรก ถ้าลบทิ้งโดยไม่มีดัชนีอื่นรองรับ
           MySQL จะปฏิเสธด้วย error 1553 */
        if (! $this->hasIndex('households_tambon_id_index')) {
            Schema::table('households', function (Blueprint $table) {
                $table->index('tambon_id');
            });
        }

        /* ---- 5. ตัด foreign key ของ village_id แล้วค่อยลบดัชนีและคอลัมน์ ----
           ลำดับสำคัญ: ตัด constraint ก่อนเสมอ ไม่งั้นดัชนีที่ constraint ใช้อยู่จะลบไม่ได้ */
        if (Schema::hasColumn('households', 'village_id')) {
            foreach ($this->foreignKeysOn('households', 'village_id') as $constraint) {
                DB::statement("ALTER TABLE `households` DROP FOREIGN KEY `{$constraint}`");
            }

            foreach (['households_tambon_id_village_id_index', 'households_village_id_foreign'] as $index) {
                if ($this->hasIndex($index)) {
                    DB::statement("ALTER TABLE `households` DROP INDEX `{$index}`");
                }
            }

            Schema::table('households', function (Blueprint $table) {
                $table->dropColumn('village_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('households', 'village_name')) {
            return;
        }

        Schema::table('households', function (Blueprint $table) {
            $table->foreignId('village_id')->nullable()->after('house_no')->constrained()->nullOnDelete();
        });

        /* ผูกกลับเข้าตาราง villages โดยเทียบ ตำบล + ชื่อ + หมู่ที่ */
        foreach (DB::table('households')->whereNotNull('village_name')->get() as $row) {
            $villageId = DB::table('villages')
                ->where('tambon_id', $row->tambon_id)
                ->where('name', $row->village_name)
                ->where('moo', $row->moo)
                ->value('id');

            $villageId ??= DB::table('villages')->insertGetId([
                'tambon_id' => $row->tambon_id,
                'name' => $row->village_name,
                'moo' => $row->moo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('households')->where('id', $row->id)->update(['village_id' => $villageId]);
        }

        if ($this->hasIndex('households_tambon_id_index')) {
            Schema::table('households', function (Blueprint $table) {
                $table->dropIndex(['tambon_id']);
            });
        }

        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex(['village_name']);
            $table->dropColumn(['village_name', 'moo']);
            $table->index(['tambon_id', 'village_id']);
        });
    }

    /** ตารางนี้มีดัชนีชื่อนี้อยู่ไหม (SQLite ไม่มี SHOW INDEX จึงถามคนละทาง) */
    private function hasIndex(string $name): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$name]
            ) !== null;
        }

        return DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            ['households', $name]
        ) !== null;
    }

    /**
     * ชื่อ foreign key ทั้งหมดที่ผูกอยู่กับคอลัมน์นี้
     *
     * @return array<int, string>
     */
    private function foreignKeysOn(string $table, string $column): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return [];        // SQLite ลบ FK แยกไม่ได้ และไม่บล็อกการลบดัชนีอยู่แล้ว
        }

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        return array_map(fn ($row) => $row->name, $rows);
    }
};
