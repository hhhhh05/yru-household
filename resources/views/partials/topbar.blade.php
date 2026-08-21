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

    <form class="gsearch" method="GET" action="{{ route('households.index') }}">
        <x-icon name="search" :size="15" :stroke="2.2" />
        <input id="gq" name="q" type="search" value="{{ request('q') }}"
               placeholder="ค้นหา HC, ชื่อ, บ้านเลขที่, โครงการ…" aria-label="ค้นหาทั้งระบบ">
        <kbd>/</kbd>
    </form>

    <button class="icon-btn" id="theme" title="สลับโหมดสว่าง/มืด" aria-label="สลับโหมดสว่าง/มืด">
        <x-icon name="sun" :stroke="2" />
    </button>

    @auth
        {{-- ผู้ใช้ที่ล็อกอินอยู่ + ปุ่มออกจากระบบ --}}
        <div style="display:flex;align-items:center;gap:9px;padding-left:11px;margin-left:3px;border-left:1px solid var(--line)">
            <span style="font-size:12.5px;font-weight:500;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                  data-tip="<b>{{ e(auth()->user()->name) }}</b>{{ e(auth()->user()->email) }}">
                {{ auth()->user()->name }}
            </span>

            <form method="POST" action="{{ route('logout') }}" class="f-inline">
                @csrf
                <button class="icon-btn" title="ออกจากระบบ" aria-label="ออกจากระบบ">
                    <x-icon name="swap" :size="17" :stroke="2" />
                </button>
            </form>
        </div>
    @endauth
</header>
