<?php

namespace App\Providers;

use App\Repositories\ActivityRepository;
use App\Repositories\AreaRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;
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
        //
    }
}
