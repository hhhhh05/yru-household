@php
    use App\Support\Nav;

    $navKey = $navKey ?? 'home';
    $nav = Nav::find($navKey);
@endphp
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $nav['title'] }} · ระบบฐานข้อมูลครัวเรือน มรย.พัฒนาท้องถิ่น</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    {{--
        โหลดไฟล์ CSS/JS
        · ถ้ามี public/build/manifest.json (สั่ง npm run build แล้ว) → ใช้ Vite ตามปกติ
        · ถ้าไม่มี → ถอยไปใช้ไฟล์สำเร็จใน public/css, public/js ซึ่งแนบมาให้แล้ว
          ทำให้หน้าเว็บมีสไตล์แน่นอน แม้ยังไม่ได้รัน npm install / npm run build
    --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <script type="module" src="{{ asset('js/app.js') }}"></script>
    @endif
</head>
<body>
<div class="app" id="app">
    @include('partials.sidebar', ['navKey' => $navKey])

    <div class="main">
        @include('partials.topbar', ['navKey' => $navKey, 'nav' => $nav])

        <div class="wrap" id="view">
            @include('partials.migrate-banner')

            @yield('content')
        </div>
    </div>
</div>

@include('partials.overlays')
</body>
</html>
