<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ระบบล็อกอินอย่างง่าย — อีเมล + รหัสผ่าน
 *
 * ใช้ Auth ของ Laravel ตรง ๆ ไม่ได้ลงแพ็กเกจเพิ่ม
 * ระบบนี้เป็นระบบภายในหน่วยงาน จึงไม่มีหน้าสมัครสมาชิกเปิดให้คนทั่วไป
 * บัญชีแรกสร้างผ่านปุ่มในหน้าล็อกอิน ซึ่งกดได้เฉพาะตอนยังไม่มีผู้ใช้เลย
 */
class AuthController extends Controller
{
    /** จำนวนครั้งที่ยอมให้ลองรหัสผิดต่อ 1 นาที */
    private const MAX_ATTEMPTS = 5;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'dbReady' => $this->usersTableExists(),
            'needsSetup' => $this->needsFirstUser(),
        ]);
    }

    public function login(Request $request)
    {
        $v = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'กรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'password.required' => 'กรอกรหัสผ่าน',
        ]);

        /* กันเดารหัสผ่านรัว ๆ — นับแยกตามอีเมล + IP */
        $key = Str::lower($v['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => 'ลองผิดหลายครั้งเกินไป — รออีก '.$seconds.' วินาทีแล้วลองใหม่',
            ]);
        }

        if (! Auth::attempt($v, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            /* ไม่บอกว่าอีเมลผิดหรือรหัสผิด เพื่อไม่ให้เดาได้ว่าอีเมลไหนมีอยู่จริง */
            throw ValidationException::withMessages([
                'email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
            ]);
        }

        RateLimiter::clear($key);

        /* เปลี่ยน session id หลังล็อกอินสำเร็จ — กัน session fixation */
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('toasts', [[
            'title' => 'ยินดีต้อนรับ',
            'msg' => Auth::user()->name,
            'kind' => 'ok',
        ]]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('toasts', [[
            'title' => 'ออกจากระบบแล้ว',
            'msg' => '',
            'kind' => 'ok',
        ]]);
    }

    /**
     * สร้างบัญชีผู้ดูแลคนแรก
     *
     * ทำได้เฉพาะตอนที่ยังไม่มีผู้ใช้ในระบบเลย — พอมีคนแรกแล้วเส้นทางนี้จะปฏิเสธทันที
     * ไม่งั้นใครก็สร้างบัญชีเข้าระบบเองได้
     */
    public function createFirstUser(Request $request)
    {
        if (! $this->needsFirstUser()) {
            return redirect()->route('login')->with('toasts', [[
                'title' => 'สร้างบัญชีแรกไม่ได้',
                'msg' => 'ระบบมีผู้ใช้อยู่แล้ว — ให้ผู้ดูแลเป็นคนเพิ่มบัญชีให้',
                'kind' => 'err',
            ]]);
        }

        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'กรอกชื่อ-สกุล',
            'email.required' => 'กรอกอีเมล',
            'email.unique' => 'อีเมลนี้มีในระบบแล้ว',
            'password.required' => 'กรอกรหัสผ่าน',
            'password.min' => 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร',
            'password.confirmed' => 'ยืนยันรหัสผ่านไม่ตรงกัน',
        ]);

        /* ส่งรหัสผ่านแบบข้อความธรรมดาเข้าไปโดยตั้งใจ
           เพราะ App\Models\User มี cast 'password' => 'hashed' อยู่แล้ว
           ถ้า Hash::make() ซ้ำอีกชั้นจะเสี่ยงเข้ารหัสสองรอบจนล็อกอินไม่ได้ */
        $user = new User;
        $user->name = trim($v['name']);
        $user->email = Str::lower(trim($v['email']));
        $user->password = $v['password'];
        $user->save();

        /* ด่านตรวจ — ถ้าแฮชผิดรูป ให้รู้ทันทีตอนสร้างบัญชี ดีกว่าไปงงตอนล็อกอินไม่ได้ */
        if (! Hash::check($v['password'], $user->password)) {
            $user->delete();

            throw ValidationException::withMessages([
                'password' => 'ตั้งรหัสผ่านไม่สำเร็จ (การเข้ารหัสไม่ถูกต้อง) — แจ้งผู้ดูแลระบบ',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('toasts', [[
            'title' => 'สร้างบัญชีผู้ดูแลแล้ว',
            'msg' => $user->email.' — เข้าสู่ระบบให้อัตโนมัติ',
            'kind' => 'ok',
        ]]);
    }

    /**
     * รัน migration ตอนที่ระบบยังไม่มีผู้ใช้เลย
     *
     * จำเป็นต้องเปิดให้คนที่ยังไม่ล็อกอินใช้ได้ ไม่งั้นจะวนไม่จบ:
     * เครื่องที่ติดตั้งใหม่ยังไม่มีตาราง users → ล็อกอินไม่ได้
     * แต่ปุ่มอัปเดตฐานข้อมูลปกติอยู่หลังการล็อกอิน → กดไม่ได้เช่นกัน
     *
     * พอมีผู้ใช้คนแรกแล้ว เส้นทางนี้จะปฏิเสธทันที
     */
    public function bootstrapMigrate(Request $request)
    {
        if ($this->usersTableExists() && ! $this->needsFirstUser()) {
            return redirect()->route('login')->with('toasts', [[
                'title' => 'ทำไม่ได้',
                'msg' => 'ระบบมีผู้ใช้อยู่แล้ว — ให้ล็อกอินก่อนแล้วใช้ปุ่มอัปเดตฐานข้อมูลตามปกติ',
                'kind' => 'err',
            ]]);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            return back()->with('toasts', [[
                'title' => 'อัปเดตฐานข้อมูลไม่สำเร็จ',
                'msg' => $e->getMessage(),
                'kind' => 'err',
            ]]);
        }

        return redirect()->route('login')->with('toasts', [[
            'title' => 'สร้างตารางเรียบร้อย',
            'msg' => trim(Artisan::output()) ?: 'ไม่มี migration ค้างอยู่',
            'kind' => 'ok',
        ]]);
    }

    /** ตาราง users มีอยู่จริงไหม (ยังไม่ได้รัน migration ก็จะยังไม่มี) */
    private function usersTableExists(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (\Throwable) {
            /* ต่อฐานข้อมูลไม่ได้ — ให้หน้าล็อกอินยังเปิดได้ ไม่ต้องพังทั้งหน้า */
            return false;
        }
    }

    /** ยังไม่มีผู้ใช้สักคนในระบบหรือเปล่า */
    private function needsFirstUser(): bool
    {
        try {
            return $this->usersTableExists() && User::count() === 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
