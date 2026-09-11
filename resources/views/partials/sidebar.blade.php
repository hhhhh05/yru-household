@php use App\Support\Nav; @endphp

{{-- แถบเมนูด้านซ้าย — แสดงเพียง 4 หัวข้อหลักตามต้นฉบับ --}}
<aside class="sb">
    <div class="sb-brand">
        <div class="sb-logo">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round">{!! \App\Support\Icon::path('leaf') !!}</svg>
        </div>
        <div class="sb-txt">
            <b>ฐานข้อมูลครัวเรือน</b>
            <span>มรย.พัฒนาท้องถิ่น · ยุทธศาสตร์ที่ 1</span>
        </div>
    </div>

    <nav class="sb-nav" id="nav">
        @php $group = ''; @endphp
        @foreach (Nav::SIDEBAR as $key)
            @php $item = Nav::find($key); $count = Nav::count($item['count'] ?? null); @endphp

            @if (($item['group'] ?? '') && $item['group'] !== $group)
                @php $group = $item['group']; @endphp
                <div class="sb-group">{{ $group }}</div>
            @endif

            <a class="nav-i {{ $navKey === $key ? 'on' : '' }}" href="{{ route($item['route']) }}"
               title="{{ $item['title'] }}">
                <x-icon :name="$item['icon']" />
                <span class="lbl">{{ $item['nav'] ?? $item['title'] }}</span>
                @if ($count !== null)
                    <span class="cnt {{ ($item['warn'] ?? false) && $count ? 'warn' : '' }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach

        <div class="sb-group">เครื่องมือ</div>
        @foreach (['ar', 'st', 'un', 'dq', 'io'] as $key)
            @php $item = Nav::find($key); $count = Nav::count($item['count'] ?? null); @endphp
            <a class="nav-i {{ $navKey === $key ? 'on' : '' }}" href="{{ route($item['route']) }}"
               title="{{ $item['title'] }}">
                <x-icon :name="$item['icon']" />
                <span class="lbl">{{ $item['nav'] ?? $item['title'] }}</span>
                @if ($count !== null)
                    <span class="cnt {{ ($item['warn'] ?? false) && $count ? 'warn' : '' }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="sb-foot">
        <div class="sb-user" role="button" tabindex="0">
            <div class="av">ยศ</div>
            <div class="sb-txt" style="min-width:0">
                <b>ยุทธศาสตร์ที่ 1</b>
                <span>ผู้ดูแลข้อมูล · YRU</span>
            </div>
        </div>
    </div>
</aside>
