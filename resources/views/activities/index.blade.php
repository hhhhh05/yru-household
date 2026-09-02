@extends('layouts.app')

@php use App\Support\Thai; @endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>โครงการ / กิจกรรม</h2>
            <p>แบ่งเป็น ปีงบประมาณ → โครงการหลัก → กิจกรรม ·
                {{ Thai::fmt($totalCount) }} กิจกรรม · งบรวม {{ Thai::fmt($totalBudget) }} บาท ·
                รหัส PA = LP + ปีงบ + ลำดับโครงการ + ลำดับกิจกรรม</p>
        </div>

        <a class="btn out" href="{{ route('export', 'activities') }}">
            <x-icon name="down" :size="15" :stroke="2.2" /> ส่งออก
        </a>
        <a class="btn pri" href="{{ route('activities.create', qs()) }}">
            <x-icon name="plus" :size="15" :stroke="2.4" /> เพิ่มกิจกรรม
        </a>
    </div>

    {{-- กรองอยู่หรือเปล่า — ใช้ทั้งปุ่มล้างตัวกรองและข้อความใต้การ์ดสรุป
         ต้องประกาศก่อนแถบตัวกรอง เพราะปุ่มล้างอยู่ในแถบนั้น --}}
    @php $filtering = $fy !== '' || $q !== '' || $pg !== '' || $pa !== '' || $unit !== ''; @endphp

    {{-- ------------------------------------------------------- แถบตัวกรอง
         วางไว้บนสุด ตัวเลขในการ์ดสรุปด้านล่างจะขยับตามที่กรองไว้ --}}
    <div class="card" style="margin-bottom:14px">
        @php
            /* ตัวเลือกโครงการหลัก — ถ้าเลือกปีงบไว้ ให้เหลือเฉพาะโครงการของปีนั้น */
            $programChoices = [];

            foreach ($programsByYear as $year => $items) {
                if ($fy !== '' && (string) $year !== $fy) {
                    continue;
                }

                foreach ($items as $item) {
                    $programChoices[$item['id']] = ($fy === '' ? $year.' · ' : '').$item['name'];
                }
            }
        @endphp

        <form method="GET" action="{{ route('activities.index') }}" class="tbar js-auto">
            <div class="fsearch {{ $q ? 'has' : '' }}">
                <x-icon name="search" :size="15" :stroke="2.2" />
                <input name="q" class="js-search" data-autofocus-end value="{{ $q }}"
                       placeholder="ค้นหารหัส PA, ชื่อกิจกรรม หรือชื่อโครงการหลัก…">
                <a class="clr" href="{{ route('activities.index', qs(['q' => ''])) }}">
                    <x-icon name="x" :size="14" :stroke="2.4" />
                </a>
            </div>

            {{-- ปีงบเป็นช่องเลือกเหมือนตัวกรองอื่น (เดิมเป็นปุ่มแท็บแยกอยู่ท้ายแถบ)
                 เปลี่ยนปีงบแล้วโครงการ/กิจกรรมที่ค้างอยู่ ฝั่งเซิร์ฟเวอร์จะล้างให้เองถ้าคนละปี --}}
            <select class="sel" name="fy">
                <option value="">ทุกปีงบประมาณ</option>
                @foreach ($fiscalYears as $year)
                    <option value="{{ $year }}" @selected($fy === (string) $year)>ปีงบ {{ $year }}</option>
                @endforeach
            </select>

            <select class="sel" name="pg">
                <option value="">ทุกโครงการหลัก</option>
                @foreach ($programChoices as $id => $label)
                    <option value="{{ $id }}" @selected($pg === (string) $id)>
                        {{ mb_strlen($label) > 58 ? mb_substr($label, 0, 58).'…' : $label }}
                    </option>
                @endforeach
            </select>

            @php
                /* ตัวเลือกกิจกรรม — แคบลงตามปีงบ / โครงการหลัก / คณะ ที่เลือกไว้แล้ว
                   จะได้ไม่มีตัวเลือกที่เลือกแล้วผลลัพธ์ว่างเปล่า */
                $activityChoices = array_values(array_filter($activities, function ($a) use ($fy, $pg, $unit) {
                    if ($fy !== '' && (string) $a['fy'] !== $fy) {
                        return false;
                    }

                    if ($pg !== '' && (string) ($a['program_id'] ?? '') !== $pg) {
                        return false;
                    }

                    return $unit === '' || trim((string) ($a['unit'] ?? '')) === trim($unit);
                }));
            @endphp

            <select class="sel" name="pa">
                <option value="">ทุกกิจกรรม ({{ count($activityChoices) }})</option>
                @foreach ($activityChoices as $choice)
                    <option value="{{ $choice['pa'] }}" @selected($pa === $choice['pa'])>
                        {{ $choice['pa'] }} · {{ mb_strlen($choice['name']) > 52 ? mb_substr($choice['name'], 0, 52).'…' : $choice['name'] }}
                    </option>
                @endforeach
            </select>

            <select class="sel" name="unit">
                <option value="">ทุกคณะ/หน่วยงาน</option>
                @foreach ($units as $unitOption)
                    <option value="{{ $unitOption }}" @selected($unit === $unitOption)>
                        {{ mb_strlen($unitOption) > 44 ? mb_substr($unitOption, 0, 44).'…' : $unitOption }}
                    </option>
                @endforeach
            </select>

            {{-- ล้างตัวกรองทั้งหมดในคลิกเดียว — โผล่เฉพาะตอนที่กรองอยู่จริง --}}
            @if ($filtering)
                <a class="btn ghost sm" href="{{ route('activities.index') }}">
                    <x-icon name="x" :size="14" :stroke="2.4" /> ล้างตัวกรอง
                </a>
            @endif
        </form>
    </div>

    {{-- ------------------------------------------------------------- การ์ดสรุป
         ตัวเลขทั้งหมดคิดตามตัวกรองที่เลือกอยู่ ไม่ใช่ทั้งระบบ
         ถ้ากรองอยู่จะมีบรรทัดล่างบอกยอดรวมทั้งระบบไว้เทียบ --}}
    <div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:14px">
        <div class="tile">
            <div class="tl">งบประมาณ</div>
            {{-- แสดงจำนวนเงินเต็ม ไม่ย่อเป็น «ล.» — ตัวเลขยาวจึงลดขนาดฟอนต์ลงหน่อยกันตกบรรทัด --}}
            <div class="tv money">{{ Thai::fmt($scopeBudget) }}<small> บาท</small></div>
            <div class="td">
                @if ($scopeCount)
                    เฉลี่ย {{ Thai::fmt(round($scopeBudget / $scopeCount)) }} บาท/กิจกรรม
                @else
                    ไม่มีกิจกรรมในขอบเขตนี้
                @endif
            </div>
            <div class="tile-ic"><x-icon name="cash" :size="16" :stroke="2" /></div>
        </div>

        <div class="tile">
            <div class="tl">โครงการหลัก</div>
            <div class="tv">{{ Thai::fmt($scopeProgramCount) }}</div>
            <div class="td">
                @if ($filtering)
                    จากทั้งหมด {{ Thai::fmt($programCount) }} โครงการ
                @else
                    ใน {{ count($fiscalYears) }} ปีงบประมาณ
                @endif
            </div>
            <div class="tile-ic"><x-icon name="box" :size="16" :stroke="2" /></div>
        </div>

        <div class="tile">
            <div class="tl">กิจกรรม</div>
            <div class="tv">{{ Thai::fmt($scopeCount) }}</div>
            <div class="td">
                @if ($filtering)
                    จากทั้งหมด {{ Thai::fmt($totalCount) }} กิจกรรม
                @elseif ($scopeUnlinkedCount)
                    {{-- กิจกรรมที่ยังไม่ผูกโครงการหลัก ไม่ถูกนับในการ์ดโครงการ จึงต้องบอกให้เห็น --}}
                    <span style="color:var(--critical-ink);font-weight:600">{{ $scopeUnlinkedCount }} กิจกรรม</span>
                    ยังไม่ผูกโครงการหลัก
                @else
                    ผูกโครงการหลักครบทุกกิจกรรม
                @endif
            </div>
            <div class="tile-ic"><x-icon name="link" :size="16" :stroke="2" /></div>
        </div>
    </div>

    {{-- ---------------------------------------------------------- ตาราง --}}
    <div class="card">
        <div class="tw">
            <table>
                <thead>
                <tr>
                    <th style="width:96px">รหัส PA</th>
                    <th>ชื่อกิจกรรม</th>
                    <th class="r" style="width:130px">งบประมาณ (บาท)</th>
                    <th style="width:190px">ครัวเรือนเข้าร่วม</th>
                    <th style="width:78px"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($tree as $year => $yearGroup)
                    {{-- ชั้นที่ 1 · ปีงบประมาณ --}}
                    <tr style="background:var(--surface-2)">
                        <td colspan="5" style="padding:8px 12px">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <b style="font-size:12.5px;font-weight:700;letter-spacing:.03em">ปีงบประมาณ {{ $year }}</b>
                                <span class="pill">{{ count($yearGroup['programs']) }} โครงการหลัก</span>
                                <span class="pill">{{ $yearGroup['count'] }} กิจกรรม</span>
                                <span style="flex:1"></span>
                                <b class="num" style="font-weight:600">{{ Thai::fmt($yearGroup['budget']) }}</b>
                            </div>
                        </td>
                    </tr>

                    @foreach ($yearGroup['programs'] as $programId => $program)
                    {{-- ชั้นที่ 2 · โครงการหลัก --}}
                    <tr>
                        <td colspan="5" style="padding:7px 12px 7px 22px;border-left:3px solid var(--brand);background:var(--surface)">
                            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                                <x-icon name="box" :size="13" :stroke="2.1" />
                                <b style="font-size:12px;font-weight:600;flex:1;min-width:200px">{{ $program['name'] }}</b>
                                <span class="pill">{{ count($program['rows']) }} กิจกรรม</span>
                                <b class="num" style="font-size:12px;font-weight:600">{{ Thai::fmt($program['budget']) }}</b>
                                @if ($programId)
                                    <a class="btn ghost xs"
                                       href="{{ route('activities.index', qs(['fy' => $year, 'pa' => '', 'pg' => $pg === (string) $programId ? '' : $programId])) }}">
                                        {{ $pg === (string) $programId ? 'เลิกกรอง' : 'ดูเฉพาะนี้' }}
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>

                    @foreach ($program['rows'] as $p)
                        @php $n = $counts[$p['pa']] ?? 0; @endphp
                        <tr>
                            <td><span class="code" style="color:var(--brand);font-weight:600">{{ $p['pa'] }}</span></td>
                            <td>
                                <div class="t-name" style="line-height:1.45">{{ $p['name'] }}</div>
                                @if (! empty($p['lecturer']))
                                    <div class="t-sub">
                                        <x-icon name="users" :size="11" :stroke="2.2" /> {{ $p['lecturer'] }}
                                        @if (! empty($p['lecturer_phone']))
                                            · <span class="num">{{ $p['lecturer_phone'] }}</span>
                                        @endif
                                        @if (($p['workload'] ?? null) !== null && $p['workload'] !== '')
                                            · ภาระงาน <span class="num">{{ rtrim(rtrim(number_format((float) $p['workload'], 1), '0'), '.') }}</span> ชม./สัปดาห์
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="r"><span class="num" style="font-weight:600">{{ Thai::fmt($p['budget']) }}</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:9px">
                                    <div class="meter" style="flex:1" data-tip="<b>{{ $p['pa'] }}</b>{{ $n }} ครัวเรือน">
                                        <i style="width:{{ round($n / max(1, $maxCount) * 100, 1) }}%"></i>
                                    </div>
                                    <b class="num" style="width:26px;text-align:right;font-weight:600">{{ $n }}</b>
                                    <a class="btn ghost xs" href="{{ route('enrollments.index', ['pa' => $p['pa']]) }}">จัดการ</a>
                                </div>
                            </td>
                            <td>
                                <div class="rowacts">
                                    <a class="ia" href="{{ route('activities.edit', ['pa' => $p['pa']] + qs()) }}" title="แก้ไข">
                                        <x-icon name="edit" :size="15" :stroke="2" />
                                    </a>
                                    <form method="POST" action="{{ route('activities.destroy', ['pa' => $p['pa']] + qs()) }}" class="f-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="ia d" title="ลบ" data-confirm
                                                data-confirm-title="ยืนยันการลบกิจกรรม"
                                                data-confirm-label="ลบกิจกรรม"
                                                data-confirm-body="กำลังจะลบ {{ $p['pa'] }} {{ e(mb_substr($p['name'], 0, 80)) }}{{ $n ? ' — มี '.$n.' ครัวเรือนในกิจกรรมนี้' : '' }}">
                                            <x-icon name="trash" :size="15" :stroke="2" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach   {{-- กิจกรรมในโครงการหลัก --}}
                    @endforeach   {{-- โครงการหลักในปีงบ --}}
                @endforeach       {{-- ปีงบประมาณ --}}
                </tbody>
            </table>
        </div>

        {{-- ----------------------------------- มุมมองการ์ด (หน้าจอเล็ก) --}}
        <div class="mcards">
            @foreach ($rows as $p)
                @php $n = $counts[$p['pa']] ?? 0; @endphp
                <div class="mcard">
                    <div class="mc-top">
                        <span class="code" style="color:var(--brand);font-weight:600;font-size:12.5px">{{ $p['pa'] }}</span>
                        <span class="pill">ปีงบ {{ $p['fy'] }}</span>
                        <span style="flex:1"></span>
                        <a class="ia" href="{{ route('activities.edit', $p['pa']) }}" aria-label="แก้ไข">
                            <x-icon name="edit" :size="16" :stroke="2" />
                        </a>
                    </div>
                    <div class="mc-name" style="font-weight:500">{{ $p['name'] }}</div>
                    @if (! empty($p['program']))
                        <div class="mc-meta" style="display:flex;gap:5px;align-items:flex-start">
                            <x-icon name="box" :size="11" :stroke="2.1" /> {{ $p['program'] }}
                        </div>
                    @endif
                    <div class="mc-meta"><b class="num">{{ Thai::fmt($p['budget']) }}</b> บาท · {{ $n }} ครัวเรือน</div>
                    <div style="display:flex;align-items:center;gap:9px;margin-top:8px">
                        <div class="meter" style="flex:1"><i style="width:{{ round($n / max(1, $maxCount) * 100, 1) }}%"></i></div>
                        <a class="btn out xs" href="{{ route('enrollments.index', ['pa' => $p['pa']]) }}">จัดการรายชื่อ</a>
                    </div>
                </div>
            @endforeach
        </div>

        @if (! count($rows))
            <div class="empty">
                <div class="ic"><x-icon name="box" :size="22" :stroke="1.9" /></div>
                <b>ไม่พบกิจกรรม</b>
                <p>ลองเปลี่ยนคำค้น ปีงบประมาณ หรือเลือก «ทุกโครงการหลัก»</p>
                @if ($pg !== '' || $fy !== '' || $q !== '')
                    <a class="btn out sm" href="{{ route('activities.index') }}" style="margin-top:10px">ล้างตัวกรองทั้งหมด</a>
                @endif
            </div>
        @endif
    </div>
@endsection
