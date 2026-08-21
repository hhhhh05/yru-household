@extends('layouts.app')

@php use App\Support\Thai; @endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>ภาพรวมระบบ</h2>
            <p>ฐานข้อมูลครัวเรือน 2570 · ยุทธศาสตร์ที่ 1 การพัฒนาท้องถิ่น · มหาวิทยาลัยราชภัฏยะลา</p>
        </div>
        <div class="seg">
            <a class="on" href="{{ route('dashboard') }}">ปีงบ 2569</a>
            <a href="{{ route('activities.index') }}">ทุกปีงบ</a>
        </div>
    </div>

    {{-- ---------------------------------------------------------- การ์ดสรุป --}}
    <div class="tiles">
        <div class="tile">
            <div class="tl">ครัวเรือนในทะเบียน</div>
            <div class="tv">{{ Thai::fmt($total) }}</div>
            <div class="td">
                <span class="up"><x-icon name="up" :size="12" :stroke="2.4" /> +12</span>
                จากเดือนก่อน · เป้าหมาย {{ Thai::fmt($target) }}
            </div>
            <div class="tile-ic"><x-icon name="users" :size="16" :stroke="2" /></div>
            <div class="spark">
                @foreach ([6, 7, 9, 8, 11, 13, 12, 15, 17, 16, 19, 22] as $i => $v)
                    <i style="height:{{ round($v / 22 * 100) }}%{{ $i === 11 ? ';background:var(--brand)' : '' }}"></i>
                @endforeach
            </div>
        </div>

        <div class="tile">
            <div class="tl">บันทึกรายได้แล้ว</div>
            <div class="tv">{{ $incomeCount }}<small> / {{ $total }}</small></div>
            <div class="td">
                มัธยฐาน {{ Thai::fmt($medianIncome) }} บาท/ปี · ขาด {{ $total - $incomeCount }} ครัวเรือน
            </div>
            <div class="tile-ic"><x-icon name="cash" :size="16" :stroke="2" /></div>
            <div class="meter" style="margin-top:11px">
                <i style="width:{{ round($incomeCount / max(1, $total) * 100, 1) }}%"></i>
            </div>
        </div>

        <div class="tile">
            <div class="tl">ครัวเรือนเข้าร่วมกิจกรรม</div>
            <div class="tv">{{ Thai::fmt($enrolledCount) }}</div>
            <div class="td">
                {{ Thai::fmt($enrollmentCount) }} รายการลงทะเบียน · {{ Thai::fmt($activityCount) }} กิจกรรม
            </div>
            <div class="tile-ic"><x-icon name="link" :size="16" :stroke="2" /></div>
            <div class="meter" style="margin-top:11px">
                <i style="width:{{ round($enrolledCount / max(1, $total) * 100, 1) }}%"></i>
            </div>
        </div>

        <div class="tile">
            <div class="tl">งบประมาณรวม 3 ปีงบ</div>
            <div class="tv">{{ Thai::compact($budget) }}<small> บาท</small></div>
            <div class="td">
                2567–2569 · เฉลี่ย {{ Thai::fmt(round($budget / max(1, $activityCount))) }} บาท/กิจกรรม
            </div>
            <div class="tile-ic"><x-icon name="box" :size="16" :stroke="2" /></div>
        </div>
    </div>

    <div class="grid2" style="margin-bottom:16px">
        {{-- --------------------------------------------- ครัวเรือนตามหมู่บ้าน --}}
        <div class="card">
            <div class="card-h">
                <div style="flex:1">
                    <h3>ครัวเรือนตามหมู่บ้าน</h3>
                    <p>จำนวนครัวเรือนในทะเบียน แยกตามหมู่บ้าน/ชุมชน</p>
                </div>
                <a class="btn out sm" href="{{ route('households.index') }}">ดูทะเบียน</a>
            </div>
            <div class="card-b">
                @foreach ($villages as $v)
                    @php $varied = count($v['variants']) > 1; @endphp
                    <div class="bar-row">
                        <div class="bl" title="{{ $v['name'] }}">
                            {{ $v['name'] }} <span style="color:var(--ink-3)">ม.{{ $v['moo'] }}</span>
                            @if ($varied)
                                <span style="color:var(--warning-ink)"
                                      data-tip="พบการสะกด {{ count($v['variants']) }} แบบ: {{ implode(' / ', $v['variants']) }}">
                                    <x-icon name="warn" :size="11" :stroke="2.4" />
                                </span>
                            @endif
                        </div>
                        <div class="bar-tr">
                            <div class="bar-fl" style="width:{{ round($v['n'] / max(1, $maxVillage) * 100, 1) }}%"
                                 data-tip="<b>{{ $v['name'] }} ม.{{ $v['moo'] }}</b>{{ $v['n'] }} ครัวเรือน · {{ round($v['n'] / max(1, $total) * 100) }}% ของทะเบียน"></div>
                        </div>
                        <div class="bv">{{ Thai::fmt($v['n']) }}</div>
                    </div>
                @endforeach

                <div class="note" style="margin-top:12px">
                    <x-icon name="info" :size="14" :stroke="2" />
                    <span>ทุกครัวเรือนอยู่ในตำบลปุโรง (รหัส 10) ยกเว้น 1 ครัวเรือนในตำบลลำใหม่ (รหัส 5)
                        — ไอคอนสีเหลืองหมายถึงพบชื่อหมู่บ้านสะกดต่างกันในทะเบียน</span>
                </div>
            </div>
        </div>

        {{-- ----------------------------------------- งบประมาณตามปีงบประมาณ --}}
        <div class="card">
            <div class="card-h">
                <div style="flex:1">
                    <h3>งบประมาณตามปีงบประมาณ</h3>
                    <p>รวม {{ Thai::fmt($activityCount) }} กิจกรรม ภายใต้ยุทธศาสตร์ที่ 1</p>
                </div>
                <a class="btn out sm" href="{{ route('activities.index') }}">จัดการโครงการ</a>
            </div>
            <div class="card-b">
                @foreach ($budgetByYear as $fy => $b)
                    @php $n = count(array_filter($activities, fn ($p) => $p['fy'] == $fy)); @endphp
                    <div style="margin-bottom:15px">
                        <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:6px">
                            <b style="font-size:13.5px;font-weight:600">ปีงบ {{ $fy }}</b>
                            <span style="font-size:12px;color:var(--ink-3)">{{ $n }} กิจกรรม</span>
                            <span style="margin-left:auto;font-weight:600;font-variant-numeric:tabular-nums">
                                {{ Thai::fmt($b) }}
                                <span style="font-weight:400;color:var(--ink-3);font-size:12px">บาท</span>
                            </span>
                        </div>
                        <div class="meter" data-tip="<b>ปีงบ {{ $fy }}</b>{{ Thai::fmt($b) }} บาท · {{ $n }} กิจกรรม">
                            <i style="width:{{ round($b / max(1, $maxYearBudget) * 100, 1) }}%"></i>
                        </div>
                    </div>
                @endforeach

                <hr class="sep">
                <div style="display:flex;align-items:baseline">
                    <span style="font-size:13px;color:var(--ink-2)">รวมทั้งสิ้น</span>
                    <b style="margin-left:auto;font-size:17px;font-weight:600;font-variant-numeric:tabular-nums">{{ Thai::fmt($budget) }}</b>
                    <span style="font-size:12px;color:var(--ink-3);margin-left:5px">บาท</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------ ประเด็นที่ควรตรวจสอบ --}}
    <div class="card">
        <div class="card-h">
            <span style="color:var(--critical-ink)"><x-icon name="shield" :size="18" :stroke="2" /></span>
            <div style="flex:1">
                <h3>รายการที่ควรตรวจสอบก่อนใช้ข้อมูล</h3>
                <p>ตรวจพบ {{ count($issues) }} ประเด็นจากข้อมูลจริงในชีต —
                    {{ $criticalCount }} รายการวิกฤต, {{ $seriousCount }} รายการควรแก้เร็ว</p>
            </div>
            <a class="btn pri sm" href="{{ route('quality.index') }}">เปิดหน้าตรวจสอบ</a>
        </div>

        <div>
            @foreach (array_slice($issues, 0, 5) as $issue)
                <x-issue :issue="$issue" />
            @endforeach
        </div>

        <div class="card-f">
            <span style="font-size:12.5px;color:var(--ink-3)">แสดง 5 จาก {{ count($issues) }} รายการ</span>
            <a class="btn ghost sm" style="margin-left:auto" href="{{ route('quality.index') }}">
                ดูทั้งหมด <x-icon name="link" :size="13" :stroke="2.2" />
            </a>
        </div>
    </div>
@endsection
