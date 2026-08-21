<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use App\Models\Tambon;
use App\Support\Thai;
use Illuminate\Database\Seeder;

/**
 * ใส่เฉพาะข้อมูลพื้นที่ (จังหวัด → อำเภอ → ตำบล) จาก app/Data/areas.php
 *
 * ใช้เมื่อต้องการเริ่มทะเบียนครัวเรือนจากศูนย์ แต่ยังต้องมีตัวเลือกพื้นที่ในฟอร์ม
 * (ไม่มีข้อมูลพื้นที่ = ช่อง "ตำบล" ในฟอร์มจะว่าง และบันทึกครัวเรือนไม่ได้)
 *
 *   php artisan db:seed --class=AreaSeeder
 *
 * ไม่ลบข้อมูลเดิม — เรียกซ้ำได้ ปลอดภัย (ใช้ firstOrCreate)
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = require app_path('Data/areas.php');

        foreach ($areas as $row) {
            $province = Province::firstOrCreate(['name' => trim($row['prov'])]);

            /* ปรับการสะกดอำเภอให้เป็นรูปเดียวกับที่ชีตครัวเรือนใช้ */
            $district = District::firstOrCreate([
                'province_id' => $province->id,
                'name' => str_replace('กรงปีนัง', 'กรงปินัง', trim($row['dist'])),
            ]);

            Tambon::firstOrCreate(
                ['district_id' => $district->id, 'name' => Thai::dedup($row['tam'])],
                ['area_code' => $row['code']],
            );
        }

        $this->command?->info(sprintf(
            'ข้อมูลพื้นที่พร้อมใช้: %d จังหวัด · %d อำเภอ · %d ตำบล',
            Province::count(), District::count(), Tambon::count()
        ));
    }
}
