<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| เส้นทางของระบบฐานข้อมูลครัวเรือน มรย.พัฒนาท้องถิ่น (ยุทธศาสตร์ที่ 1)
|--------------------------------------------------------------------------
| เวอร์ชันนี้เป็น UI เท่านั้น — ยังไม่เชื่อมฐานข้อมูล
| ข้อมูลอ่านจาก app/Data/*.php ผ่านคลาสใน app/Repositories
*/

/* ---------------------------------------------------------- ภาพรวมระบบ ---- */
Route::get('/', DashboardController::class)->name('dashboard');

/* ------------------------------------------------------ ทะเบียนครัวเรือน ---- */
Route::prefix('households')->name('households.')->group(function () {
    Route::get('/', [HouseholdController::class, 'index'])->name('index');
    Route::get('/create', [HouseholdController::class, 'create'])->name('create');
    Route::post('/', [HouseholdController::class, 'store'])->name('store');

    Route::post('/bulk/{action}', [HouseholdController::class, 'bulk'])
        ->whereIn('action', ['enroll', 'area', 'delete', 'merge', 'fix-district'])
        ->name('bulk');

    Route::get('/{hc}', [HouseholdController::class, 'show'])->name('show');
    Route::get('/{hc}/edit', [HouseholdController::class, 'edit'])->name('edit');
    Route::put('/{hc}', [HouseholdController::class, 'update'])->name('update');
    Route::delete('/{hc}', [HouseholdController::class, 'destroy'])->name('destroy');
});

/* ---------------------------------------------------- โครงการ / กิจกรรม ---- */
Route::prefix('activities')->name('activities.')->group(function () {
    Route::get('/', [ActivityController::class, 'index'])->name('index');
    Route::get('/create', [ActivityController::class, 'create'])->name('create');
    Route::post('/', [ActivityController::class, 'store'])->name('store');
    Route::get('/{pa}/edit', [ActivityController::class, 'edit'])->name('edit');
    Route::put('/{pa}', [ActivityController::class, 'update'])->name('update');
    Route::delete('/{pa}', [ActivityController::class, 'destroy'])->name('destroy');
});

/* ------------------------------------------------ รายชื่อเข้าร่วมโครงการ ---- */
Route::prefix('enrollments')->name('enrollments.')->group(function () {
    Route::get('/', [EnrollmentController::class, 'index'])->name('index');
    Route::get('/create', [EnrollmentController::class, 'create'])->name('create');
    Route::post('/', [EnrollmentController::class, 'store'])->name('store');

    Route::post('/bulk/{action}', [EnrollmentController::class, 'bulk'])
        ->whereIn('action', ['status', 'move', 'remove'])
        ->name('bulk');

    Route::patch('/{id}/status', [EnrollmentController::class, 'updateStatus'])->name('status');
    Route::delete('/{id}', [EnrollmentController::class, 'destroy'])->name('destroy');
});

/* ------------------------------------------------------------ เครื่องมือ ---- */
Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
Route::get('/quality', [DataQualityController::class, 'index'])->name('quality.index');
Route::get('/import-export', [ImportExportController::class, 'index'])->name('io.index');

/* ตั้งค่าเริ่มต้นผ่านหน้าเว็บ (ไม่ต้องใช้ Terminal) */
Route::post('/setup/migrate', [SetupController::class, 'migrate'])->name('setup.migrate');

Route::post('/setup/seed/{type}', [SetupController::class, 'seed'])
    ->whereIn('type', ['areas', 'demo'])
    ->name('setup.seed');

Route::get('/export/{type}', ExportController::class)
    ->whereIn('type', ['households', 'activities', 'enrollments', 'areas', 'quality'])
    ->name('export');
