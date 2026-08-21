<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\District;
use App\Models\Enrollment;
use App\Models\Household;
use App\Models\Program;
use App\Models\Province;
use App\Models\Tambon;
use App\Models\Village;
use App\Support\Thai;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * นำข้อมูลจริงจากไฟล์ใน app/Data เข้าฐานข้อมูล
 *
 * สั่งได้ด้วย: php artisan db:seed
 * (หรือ php artisan migrate:fresh --seed เพื่อสร้างตารางใหม่แล้วใส่ข้อมูล)
 *
 * หมายเหตุการแปลงข้อมูล:
 *  · ชื่ออำเภอ «กรงปีนัง» ในชีตข้อมูลจังหวัด ปรับให้ตรงกับชีตครัวเรือนเป็น «กรงปินัง»
 *  · อำเภอของแต่ละครัวเรือนอ้างจากตำบลที่เลือก จึงไม่มีปัญหา «อำเภอไม่ตรงกับตำบล» อีก
 *  · ชื่อหมู่บ้านเก็บตามที่บันทึกไว้จริง (รวมที่สะกดต่างกัน) เพื่อให้หน้าตรวจคุณภาพข้อมูลยังใช้ได้
 */
class YruDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->truncateAll();

        $areas = require app_path('Data/areas.php');
        $households = require app_path('Data/households.php');
        $activities = require app_path('Data/activities.php');
        $enrollments = require app_path('Data/enrollments.php');
        $programs = require app_path('Data/programs.php');

        [$tambonByKey, $tambonByName] = $this->seedAreas($areas);
        $this->seedHouseholds($households, $tambonByKey, $tambonByName);
        $this->seedActivities($activities, $programs);
        $this->seedEnrollments($enrollments);

        $this->command?->info(sprintf(
            'เสร็จแล้ว: %d จังหวัด · %d อำเภอ · %d ตำบล · %d หมู่บ้าน · %d ครัวเรือน · %d กิจกรรม · %d รายการลงทะเบียน',
            Province::count(), District::count(), Tambon::count(), Village::count(),
            Household::count(), Activity::count(), Enrollment::count()
        ));
    }

    /** ล้างข้อมูลเดิมก่อนใส่ใหม่ (ปิดการตรวจ foreign key ชั่วคราว) */
    private function truncateAll(): void
    {
        $tables = ['enrollments', 'households', 'activities', 'programs', 'villages', 'tambons', 'districts', 'provinces'];
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * จังหวัด → อำเภอ → ตำบล
     *
     * @return array{0: array<string,int>, 1: array<string,int>}
     *         [0] คีย์ "ชื่อตำบล(รหัส)" → tambon_id   [1] คีย์ "ชื่อตำบล" → tambon_id
     */
    private function seedAreas(array $areas): array
    {
        $byKey = [];
        $byName = [];

        foreach ($areas as $row) {
            $provinceName = trim($row['prov']);
            /* ปรับการสะกดอำเภอให้เป็นรูปเดียวกับที่ชีตครัวเรือนใช้ */
            $districtName = str_replace('กรงปีนัง', 'กรงปินัง', trim($row['dist']));
            $tambonName = Thai::dedup($row['tam']);

            $province = Province::firstOrCreate(['name' => $provinceName]);
            $district = District::firstOrCreate([
                'province_id' => $province->id,
                'name' => $districtName,
            ]);
            $tambon = Tambon::firstOrCreate(
                ['district_id' => $district->id, 'name' => $tambonName],
                ['area_code' => $row['code']],
            );

            $byKey[$tambonName.'('.$row['code'].')'] = $tambon->id;
            $byName[$tambonName] ??= $tambon->id;
        }

        return [$byKey, $byName];
    }

    /** ครัวเรือน + หมู่บ้าน */
    private function seedHouseholds(array $households, array $tambonByKey, array $tambonByName): void
    {
        $villageCache = [];

        foreach ($households as $row) {
            /* หาตำบลจากข้อความ "ปุโรง(10)" — ถ้าไม่มีก็ปล่อยว่างไว้ให้ผู้ใช้มาแก้ */
            $tambonId = null;

            if ($row['tam']) {
                $clean = Thai::dedup(Thai::tamName($row['tam']));
                $code = Thai::tamCode($row['tam']);
                $tambonId = $tambonByKey[$clean.'('.$code.')'] ?? $tambonByName[$clean] ?? null;
            }

            /* หมู่บ้าน — เก็บตามที่บันทึกไว้จริง ไม่แก้การสะกดให้
               ยังจดลงตาราง villages ด้วย เพราะใช้เป็นคลังคำของตัวช่วยกรองตอนพิมพ์ */
            $villageName = trim((string) $row['vill']);

            if ($villageName !== '') {
                $cacheKey = $tambonId.'|'.$villageName.'|'.$row['moo'];

                $villageCache[$cacheKey] ??= Village::firstOrCreate([
                    'tambon_id' => $tambonId,
                    'name' => $villageName,
                    'moo' => $row['moo'],
                ])->id;
            }

            /* แยกเพื่อจัดรูปแบบให้เป็นมาตรฐานเดียวกัน แล้วประกอบกลับเก็บคอลัมน์เดียว
               ต้อง dedup ก่อนแยก ไม่งั้น «นาางสาว» จะหาคำนำหน้าไม่เจอ แล้วโดนเติม
               «นาย» ไปข้างหน้าจนกลายเป็น «นายนาางสาว...» */
            [$prefix, $first, $last] = Thai::splitName(Thai::dedup($row['name']));

            Household::create([
                'hc' => $row['hc'],
                'full_name' => trim(($prefix ?: 'นาย').$first.' '.$last),
                'house_no' => $row['house'],
                /* ชื่อหมู่บ้านเก็บบนแถวครัวเรือน ส่วนตาราง villages เป็นคลังคำแนะนำ */
                'village_name' => $villageName ?: null,
                'moo' => $row['moo'] ?: null,
                'tambon_id' => $tambonId,
                'phone' => $row['phone'] ?: null,
                'income_bl' => $row['income'],
                'source' => 'สำรวจภาคสนาม (อว.ส่วนหน้า)',
                'status' => 'อยู่ในเป้าหมาย',
            ]);
        }
    }

    /** โครงการหลัก + กิจกรรม */
    private function seedActivities(array $activities, array $programs): void
    {
        $programIds = [];

        foreach ($programs as $fiscalYear => $name) {
            $programIds[$fiscalYear] = Program::firstOrCreate([
                'fiscal_year' => $fiscalYear,
                'name' => $name,
            ])->id;
        }

        foreach ($activities as $row) {
            Activity::create([
                'pa' => $row['pa'],
                'program_id' => $programIds[$row['fy']] ?? null,
                'fiscal_year' => $row['fy'],
                'name' => $row['name'],
                'budget' => $row['budget'],
            ]);
        }
    }

    /** รายชื่อเข้าร่วมโครงการ */
    private function seedEnrollments(array $enrollments): void
    {
        $householdIds = Household::pluck('id', 'hc');
        $activityIds = Activity::pluck('id', 'pa');

        foreach ($enrollments as $row) {
            $householdId = $householdIds[$row['hc']] ?? null;
            $activityId = $activityIds[$row['pa']] ?? null;

            if (! $householdId || ! $activityId) {
                continue;
            }

            Enrollment::firstOrCreate(
                ['household_id' => $householdId, 'activity_id' => $activityId],
                [
                    'code' => $row['id'],
                    'joined_at' => $row['joined'],
                    'status' => $row['status'],
                    'note' => $row['note'] ?: null,
                ],
            );
        }
    }
}
