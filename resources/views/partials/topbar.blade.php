@php
    use App\Support\Nav;

    [$btnLabel, $btnRoute] = Nav::primaryButton($navKey);
@endphp

<header class="top">
    <button class="icon-btn" id="burger" title="ย่อ/ขยายเมนู" aria-label="ย่อ/ขยายเมนู">
        <x-icon name="menu" :size="18" :stroke="2" />
    </button>

    <div style="min-width:0">
        <div class="crumb" id="crumb">
            <span>ยุทธศาสตร์ที่ 1 · มรย.พัฒนาท้องถิ่น</span><span>›</span><span>{{ $nav['title'] }}</span>
        </div>
        <h1 id="ptitle">{{ $nav['title'] }}</h1>
    </div>

    <div class="top-sp"></div>

    <button class="icon-btn" id="theme" title="สลับโหมดสว่าง/มืด" aria-label="สลับโหมดสว่าง/มืด">
        <x-icon name="sun" :stroke="2" />
    </button>
</header>
