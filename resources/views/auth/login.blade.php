<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>เข้าสู่ระบบ · ระบบฐานข้อมูลครัวเรือน มรย.พัฒนาท้องถิ่น</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <script type="module" src="{{ asset('js/app.js') }}"></script>
    @endif
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--surface-2)">
    <div style="width:min(420px,100%)">

        <div style="text-align:center;margin-bottom:20px">
            <div class="ic-cir brand" style="width:46px;height:46px;margin:0 auto 12px">
                <x-icon name="home" :size="22" :stroke="2.1" />
            </div>
            <h2 style="font-size:18px;font-weight:700;line-height:1.4">ระบบฐานข้อมูลครัวเรือน</h2>
            <p style="font-size:12.5px;color:var(--ink-3);margin-top:3px">
                มรย.พัฒนาท้องถิ่น · ยุทธศาสตร์ที่ 1
            </p>
        </div>

        @if (! $dbReady)
            {{-- ยังไม่มีตาราง users — เครื่องที่เพิ่งติดตั้ง ต้องสร้างตารางก่อนถึงจะล็อกอินได้ --}}
            <div class="card">
                <div class="card-h">
                    <span style="color:var(--warning-ink)"><x-icon name="warn" :size="18" :stroke="2.2" /></span>
                    <div style="flex:1">
                        <h3>ยังไม่ได้สร้างตารางในฐานข้อมูล</h3>
                        <p>ต้องสร้างตารางก่อน จึงจะสร้างบัญชีผู้ใช้และเข้าสู่ระบบได้</p>
                    </div>
                </div>

                <div class="card-b">
                    <p style="font-size:12.5px;color:var(--ink-3);line-height:1.65;margin-bottom:14px">
                        ตรวจว่าตั้งค่า <span class="code">DB_DATABASE</span> ใน <span class="code">.env</span>
                        ตรงกับฐานข้อมูลที่สร้างไว้ และเปิด MySQL อยู่ แล้วกดปุ่มด้านล่าง
                        (เท่ากับคำสั่ง <span class="code">php artisan migrate</span>)
                    </p>

                    <form method="POST" action="{{ route('auth.bootstrap-migrate') }}">
                        @csrf
                        <button class="btn pri" style="width:100%;justify-content:center">
                            <x-icon name="swap" :size="15" :stroke="2.2" /> สร้างตารางในฐานข้อมูล
                        </button>
                    </form>
                </div>
            </div>
        @elseif ($needsSetup)
            {{-- ยังไม่มีผู้ใช้ในระบบเลย — ให้สร้างบัญชีผู้ดูแลคนแรกตรงนี้ --}}
            <div class="card">
                <div class="card-h">
                    <span style="color:var(--brand)"><x-icon name="shield" :size="18" :stroke="2" /></span>
                    <div style="flex:1">
                        <h3>สร้างบัญชีผู้ดูแลคนแรก</h3>
                        <p>ยังไม่มีผู้ใช้ในระบบ — บัญชีนี้จะเป็นคนแรกและเข้าสู่ระบบให้อัตโนมัติ</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('auth.first-user') }}" class="card-b">
                    @csrf

                    <div class="f {{ $errors->has('name') ? 'err' : '' }}">
                        <label>ชื่อ - สกุล <span class="req">*</span></label>
                        <input name="name" value="{{ old('name') }}" placeholder="เช่น สมชาย ใจดี" autofocus>
                        <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('name') }}</span>
                    </div>

                    <div class="f {{ $errors->has('email') ? 'err' : '' }}" style="margin-top:12px">
                        <label>อีเมล <span class="req">*</span></label>
                        <input name="email" type="email" value="{{ old('email') }}" placeholder="you@yru.ac.th">
                        <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('email') }}</span>
                    </div>

                    <div class="f {{ $errors->has('password') ? 'err' : '' }}" style="margin-top:12px">
                        <label>รหัสผ่าน <span class="req">*</span></label>
                        <input name="password" type="password" placeholder="อย่างน้อย 8 ตัวอักษร">
                        <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('password') }}</span>
                    </div>

                    <div class="f" style="margin-top:12px">
                        <label>ยืนยันรหัสผ่าน <span class="req">*</span></label>
                        <input name="password_confirmation" type="password" placeholder="พิมพ์รหัสผ่านอีกครั้ง">
                    </div>

                    <button class="btn pri" style="width:100%;margin-top:16px;justify-content:center">
                        <x-icon name="chk" :size="15" :stroke="2.4" /> สร้างบัญชีและเข้าสู่ระบบ
                    </button>
                </form>
            </div>
        @else
            <div class="card">
                <form method="POST" action="{{ route('login') }}" class="card-b">
                    @csrf

                    <div class="f {{ $errors->has('email') ? 'err' : '' }}">
                        <label>อีเมล</label>
                        <input name="email" type="email" value="{{ old('email') }}"
                               placeholder="you@yru.ac.th" autofocus autocomplete="username">
                        <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('email') }}</span>
                    </div>

                    <div class="f {{ $errors->has('password') ? 'err' : '' }}" style="margin-top:12px">
                        <label>รหัสผ่าน</label>
                        <input name="password" type="password" placeholder="รหัสผ่าน" autocomplete="current-password">
                        <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('password') }}</span>
                    </div>

                    <label style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:12.5px;cursor:pointer">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        จำการเข้าสู่ระบบไว้ในเครื่องนี้
                    </label>

                    <button class="btn pri" style="width:100%;margin-top:16px;justify-content:center">
                        <x-icon name="shield" :size="15" :stroke="2.2" /> เข้าสู่ระบบ
                    </button>
                </form>
            </div>

            <p style="text-align:center;font-size:11.5px;color:var(--ink-3);margin-top:14px;line-height:1.6">
                ลืมรหัสผ่าน หรือยังไม่มีบัญชี — ติดต่อผู้ดูแลระบบเพื่อขอสิทธิ์เข้าใช้งาน
            </p>
        @endif
    </div>
</div>

@include('partials.overlays')
</body>
</html>
