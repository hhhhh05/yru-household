@extends('layouts.app')

@php
    use App\Repositories\DataQualityAnalyzer;

    $current = collect($categories)->firstWhere(0, $cat);
    $rules = [
        ['ชื่อ-สกุล + บ้านเลขที่ ต้องไม่ซ้ำกัน', 'critical'],
        ['ต้องระบุตำบลเพื่อออกรหัส HC ได้', 'critical'],
        ['อำเภอต้องสอดคล้องกับตำบลในชุดข้อมูลพื้นที่', 'serious'],
        ['ชื่ออำเภอ/ตำบลต้องสะกดตรงกันทุกชีต', 'serious'],
        ['คำนำหน้าชื่อต้องเป็น นาย/นาง/นางสาว/เด็กชาย/เด็กหญิง', 'warning'],
        ['ไม่มีสระหรือวรรณยุกต์ซ้ำติดกัน', 'warning'],
        ['ชื่อหมู่บ้านต้องตรงกับชุดข้อมูลอ้างอิง', 'warning'],
        ['ควรบันทึกรายได้ BL และเบอร์ติดต่อ', 'warning'],
    ];
@endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>ตรวจสอบคุณภาพข้อมูล</h2>
            <p>วิเคราะห์จากข้อมูลจริงในชีตทั้ง 4 · พบ {{ count($issues) }} ประเด็น ·
                อัปเดตอัตโนมัติทุกครั้งที่แก้ข้อมูล</p>
        </div>
        <a class="btn out" href="{{ route('export', 'quality') }}">
            <x-icon name="down" :size="15" :stroke="2.2" /> ส่งออกรายงาน
        </a>
    </div>

    <div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(178px,1fr))">
        <div class="tile">
            <div class="tl"><span class="dot" style="background:var(--critical)"></span>วิกฤต — ต้องแก้ก่อนใช้</div>
            <div class="tv">{{ $critical }}</div>
            <div class="td">ข้อมูลซ้ำหรือขาดฟิลด์บังคับ</div>
        </div>
        <div class="tile">
            <div class="tl"><span class="dot" style="background:var(--serious)"></span>ควรแก้เร็ว</div>
            <div class="tv">{{ $serious }}</div>
            <div class="td">พื้นที่ไม่สอดคล้องกัน</div>
        </div>
        <div class="tile">
            <div class="tl"><span class="dot" style="background:var(--warning)"></span>เฝ้าระวัง</div>
            <div class="tv">{{ $warning }}</div>
            <div class="td">สะกดผิด / ข้อมูลยังไม่ครบ</div>
        </div>
        <div class="tile">
            <div class="tl">ความสมบูรณ์ของข้อมูล</div>
            <div class="tv">{{ round($completeness * 100) }}<small>%</small></div>
            <div class="meter {{ $completeness < 0.6 ? 'w' : '' }}" style="margin-top:9px">
                <i style="width:{{ round($completeness * 100, 1) }}%"></i>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
        @foreach ($categories as [$key, $label, $icon])
            <a class="btn {{ $cat === $key ? 'pri' : 'out' }} sm" href="{{ route('quality.index', ['cat' => $key]) }}">
                <x-icon :name="$icon" :size="13" :stroke="2.1" /> {{ $label }}
                <span class="pill"
                      @if ($cat === $key) style="background:rgba(255,255,255,.22);color:inherit" @endif>{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-h">
            <div style="flex:1">
                <h3>{{ $current[1] ?? 'ทุกประเด็น' }}</h3>
                <p>{{ count($list) }} รายการ · เรียงตามความรุนแรง</p>
            </div>
            <a class="btn out sm" href="{{ route('quality.index', ['cat' => $cat]) }}">
                <x-icon name="swap" :size="13" :stroke="2.2" /> ตรวจสอบใหม่
            </a>
        </div>

        <div>
            @forelse ($list as $issue)
                <x-issue :issue="$issue" />
            @empty
                <div class="empty">
                    <div class="ic" style="color:var(--good-ink)"><x-icon name="chk" :size="22" :stroke="2.4" /></div>
                    <b>ไม่พบประเด็นในหมวดนี้</b>
                    <p>ข้อมูลในหมวดนี้ผ่านการตรวจสอบทั้งหมด</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-h">
            <div style="flex:1">
                <h3>กฎการตรวจสอบที่ระบบใช้</h3>
                <p>ปรับแต่งได้ตามระเบียบของหน่วยงาน</p>
            </div>
        </div>
        <div class="card-b" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
            @foreach ($rules as [$text, $sev])
                <div style="display:flex;gap:9px;align-items:flex-start;padding:10px 12px;border:1px solid var(--line);border-radius:var(--r-m)">
                    <span class="dot" style="background:var(--{{ $sev }});margin-top:6px"></span>
                    <div style="flex:1">
                        <div style="font-size:12.8px;line-height:1.45">{{ $text }}</div>
                        <div style="font-size:11px;color:var(--ink-3);margin-top:2px">{{ DataQualityAnalyzer::SEVERITY[$sev][1] }}</div>
                    </div>
                    <span class="bg b-good"><x-icon name="chk" :size="11" :stroke="2.6" /> เปิด</span>
                </div>
            @endforeach
        </div>
    </div>
@endsection
