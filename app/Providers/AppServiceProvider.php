<?php

namespace App\Providers;

use App\Repositories\ActivityRepository;
use App\Repositories\AreaRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * ลงทะเบียนชั้นข้อมูลเป็น singleton
     * — ทำให้แต่ละคำขออ่านไฟล์ข้อมูลและคำนวณผลตรวจคุณภาพเพียงครั้งเดียว
     */
    public function register(): void
    {
        $this->app->singleton(AreaRepository::class);
        $this->app->singleton(EnrollmentRepository::class);
        $this->app->singleton(HouseholdRepository::class);
        $this->app->singleton(ActivityRepository::class);
        $this->app->singleton(DataQualityAnalyzer::class);
    }

    public function boot(): void
    {
        /* APP_URL ที่มี «/» ปิดท้าย ทำให้ลิงก์ที่ระบบสร้างกลายเป็น https://โดเมน//activities
           ซึ่งโฮสต์ตอบ 404 — อาการคือกดฟิลเตอร์แล้วไม่มีอะไรเกิดขึ้น
           ตัดทับท้ายทิ้งตรงนี้ทีเดียว จะได้ไม่ต้องคอยระวังตอนตั้งค่า .env ของแต่ละเครื่อง */
        $url = rtrim((string) config('app.url'), '/');

        if ($url === '') {
            return;
        }

        config(['app.url' => $url]);

        /* บนเครื่องพัฒนาไม่บังคับ root/scheme — ที่อยู่ที่ใช้เปิดเว็บมักไม่ตรงกับ APP_URL
           (localhost/yru-household, 127.0.0.1, ชื่อ .test) ถ้าบังคับจะทำให้ลิงก์เพี้ยนแทน */
        if (app()->environment('local')) {
            return;
        }

        /* บนโฮสต์จริง บังคับให้ทุกลิงก์อิงโดเมนเดียวกับ APP_URL เสมอ
           กัน https ตกเป็น http เวลารันหลังพร็อกซี/โหลดบาลานเซอร์ ซึ่งทำให้ฟอร์มส่งไม่ถึงปลายทาง */
        URL::forceRootUrl($url);

        if (str_starts_with($url, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
