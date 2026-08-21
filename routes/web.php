<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
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
| ทุกหน้าต้องล็อกอินก่อน ยกเว้นหน้าล็อกอินเอง
| ถ้าเพิ่มเส้นทางใหม่ ให้วางไว้ในบล็อก middleware('auth') ด้านล่าง
*/

/* --------------------------------------------------------- เข้าสู่ระบบ ---- */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    /* สร้างบัญชีแรก — คอนโทรลเลอร์ปฏิเสธเองถ้ามีผู้ใช้อยู่แล้ว */
    Route::post('/setup/first-user', [AuthController::class, 'createFirstUser'])->name('auth.first-user');

    /* สร้างตารางตอนติดตั้งใหม่ (ยังไม่มีตาราง users จึงล็อกอินไม่ได้)
       คอนโทรลเลอร์ปฏิเสธเองเมื่อมีผู้ใช้แล้ว */
    Route::post('/setup/bootstrap-migrate', [AuthController::class, 'bootstrapMigrate'])
        ->name('auth.bootstrap-migrate');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/* ============================================================================
   ตั้งแต่บรรทัดนี้ลงไป ต้องล็อกอินก่อนทั้งหมด
   ============================================================================ */
Route::middleware('auth')->group(function () {

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

});
