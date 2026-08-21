<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Household;
use App\Models\Province;
use App\Models\Tambon;
use Database\Seeders\AreaSeeder;
use Database\Seeders\YruDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * ตั้งค่าเริ่มต้นผ่านหน้าเว็บ — ไม่ต้องพิมพ์คำสั่งใน Terminal
 *
 *  areas → ใส่ข้อมูลพื้นที่ (จังหวัด/อำเภอ/ตำบล) อย่างเดียว ไม่ลบอะไร เรียกซ้ำได้
 *  demo  → ใส่ชุดข้อมูลตัวอย่างทั้งหมด (ล้างทุกตารางก่อน)
 */
class SetupController extends Controller
{
    /** รัน migration ที่ยังไม่ได้รัน (ใช้ตอนเพิ่มคอลัมน์ใหม่) */
    public function migrate(Request $request)
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return back()->with('toasts', [[
                'title' => 'อัปเดตฐานข้อมูลไม่สำเร็จ',
                'msg' => $e->getMessage(),
                'kind' => 'err',
            ]]);
        }

        return back()->with('toasts', [[
            'title' => 'อัปเดตโครงสร้างฐานข้อมูลแล้ว',
            'msg' => trim(Artisan::output()) ?: 'ไม่มี migration ค้างอยู่',
            'kind' => 'ok',
        ]]);
    }

    public function seed(Request $request, string $type)
    {
        $seeder = $type === 'demo' ? YruDataSeeder::class : AreaSeeder::class;

        try {
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
        } catch (Throwable $e) {
            return back()->with('toasts', [[
                'title' => 'ใส่ข้อมูลไม่สำเร็จ',
                'msg' => $e->getMessage(),
                'kind' => 'err',
            ]]);
        }

        $msg = $type === 'demo'
            ? Household::count().' ครัวเรือน · '.Tambon::count().' ตำบล พร้อมใช้งาน'
            : Province::count().' จังหวัด · '.District::count().' อำเภอ · '.Tambon::count().' ตำบล';

        return back()->with('toasts', [[
            'title' => $type === 'demo' ? 'ใส่ข้อมูลตัวอย่างเรียบร้อย' : 'ใส่ข้อมูลพื้นที่เรียบร้อย',
            'msg' => $msg,
            'kind' => 'ok',
        ]]);
    }
}
