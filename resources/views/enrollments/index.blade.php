@extends('layouts.app')

@php
    use App\Support\Thai;

    $rows = $page['rows'];
    $pa = $filters['pa'];
    $scopeCount = count($scope);
    $incomeAvg = count($incomes) ? round(array_sum($incomes) / count($incomes)) : null;

    /* รายได้เฉลี่ยหลังเข้าร่วมโครงการ + ส่วนต่างเฉลี่ย
       ส่วนต่างคิดจาก «รายคนที่มีตัวเลขครบทั้งสองฝั่ง» ไม่ใช่เอาค่าเฉลี่ยสองชุดมาลบกัน
       เพราะสองชุดนั้นมาจากคนละกลุ่มตัวอย่าง ผลลัพธ์จะไม่ตรงความจริง */
    $incomeAfterAvg = count($incomesAfter) ? round(array_sum($incomesAfter) / count($incomesAfter)) : null;
    $incomeDiffAvg = count($incomePairs) ? round(array_sum($incomePairs) / count($incomePairs)) : null;
    $doneCount = $statusCounts['สำเร็จ'] ?? 0;
@endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>รายชื่อเข้าร่วมโครงการ</h2>
            <p>ชีต «รายชื่อเข้าร่วมโครงการ» · จับคู่ HC ↔ PA ·
                {{ Thai::fmt($totalEnrollments) }} รายการลงทะเบียนทั้งระบบ</p>
        </div>

        <form method="GET" action="{{ route('export', 'enrollments') }}" class="f-inline">
            @foreach (qs([], ['page']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button class="btn out"><x-icon name="down" :size="15" :stroke="2.2" /> ส่งออก</button>
        </form>

        {{-- ปุ่ม «เพิ่มรายชื่อ» ย้ายไปอยู่เหนือตารางแทน (ใกล้กับรายการที่กำลังดูอยู่)
             จึงไม่มีปุ่มซ้ำในหัวหน้า --}}
    </div>

    {{-- ------------------------------------------------------ ตัวเลือกกิจกรรม --}}
    <div class="card" style="margin-bottom:14px">
        <div class="card-b" style="padding:14px 16px">
            <div class="picker-bar">
                @php
                    $pg = $filters['pg'];

                    /* กิจกรรมที่จะแสดงในช่องที่สอง — แคบลงตามโครงการหลักที่เลือก */
                    $paOptions = $pg
                        ? array_values(array_filter($activities, fn ($a) => (string) ($a['program_id'] ?? '') === $pg))
                        : $activities;

                    $paYears = array_values(array_unique(array_column($paOptions, 'fy')));
                    sort($paYears);

                    /* ช่องกรองพื้นที่ — แต่ละชั้นแคบลงตามชั้นบนที่เลือกไว้แล้ว
                       จังหวัดยังแสดงครบเสมอ จะได้เปลี่ยนกลับได้ ไม่ตันอยู่ในจังหวัดเดียว */
                    $fProv = $filters['prov'];
                    $fDist = $filters['dist'];
                    $fTam = $filters['tam'];

                    $provOptions = array_values(array_unique(array_column($areaOptions, 'prov')));

                    $distOptions = array_values(array_filter(array_unique(array_column(
                        array_filter($areaOptions, fn ($a) => $fProv === '' || $a['prov'] === $fProv),
                        'dist',
                    )), fn ($d) => $d !== ''));

                    $tamOptions = array_values(array_filter(array_unique(array_column(
                        array_filter($areaOptions, fn ($a) => ($fProv === '' || $a['prov'] === $fProv)
                            && ($fDist === '' || $a['dist'] === $fDist)),
                        'tam',
                    )), fn ($t) => $t !== ''));
                @endphp

                {{-- เลือกโครงการหลักก่อน แล้วค่อยเลือกกิจกรรมย่อยในโครงการนั้น
                     ฟอร์มเดียวกันทั้งสองช่อง เพราะต้องส่งค่าไปด้วยกัน --}}
                <form method="GET" action="{{ route('enrollments.index') }}" class="js-auto"
                      style="flex:1;min-width:280px;display:flex;gap:12px;flex-wrap:wrap">
                    <div class="f" style="flex:1;min-width:240px">
                        <label><x-icon name="box" :size="13" :stroke="2.2" /> โครงการหลัก</label>
                        <select class="pick-lg" name="pg">
                            <option value="">▸ ทุกโครงการหลัก</option>
                            @foreach ($programsByYear as $year => $items)
                                <optgroup label="ปีงบประมาณ {{ $year }}">
                                    @foreach ($items as $item)
                                        <option value="{{ $item['id'] }}" @selected($pg === (string) $item['id'])>
                                            {{ mb_strlen($item['name']) > 70 ? mb_substr($item['name'], 0, 70).'…' : $item['name'] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="f" style="flex:1;min-width:260px">
                        <label><x-icon name="link" :size="13" :stroke="2.2" /> กิจกรรม</label>
                        <select id="enPick" class="pick-lg" name="pa">
                            <option value="">
                                @if ($pg)
                                    ▸ ทุกกิจกรรมในโครงการนี้ ({{ count($paOptions) }} กิจกรรม)
                                @else
                                    ▸ ทุกกิจกรรม — แสดงรายชื่อทั้งระบบ ({{ Thai::fmt($totalEnrollments) }} รายการ)
                                @endif
                            </option>
                            @foreach ($paYears as $year)
                                <optgroup label="ปีงบประมาณ {{ $year }}">
                                    @foreach ($paOptions as $p)
                                        @continue($p['fy'] !== $year)
                                        <option value="{{ $p['pa'] }}" @selected($pa === $p['pa'])>
                                            {{ $p['pa'] }} · {{ mb_strlen($p['name']) > 70 ? mb_substr($p['name'], 0, 70).'…' : $p['name'] }}
                                            — {{ $counts[$p['pa']] ?? 0 }} ครัวเรือน
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    {{-- ตัวกรองพื้นที่ อยู่ใต้โครงการ/กิจกรรม ในฟอร์มเดียวกัน
                         flex-basis:100% บังคับให้ขึ้นบรรทัดใหม่เต็มความกว้าง --}}
                    <div style="flex-basis:100%;display:flex;gap:12px;flex-wrap:wrap">
                        <div class="f" style="flex:1;min-width:170px">
                            <label><x-icon name="map" :size="13" :stroke="2.2" /> จังหวัด</label>
                            <select name="prov">
                                <option value="">ทุกจังหวัด</option>
                                @foreach ($provOptions as $provOption)
                                    <option value="{{ $provOption }}" @selected($fProv === $provOption)>จ.{{ $provOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="f" style="flex:1;min-width:170px">
                            <label><x-icon name="map" :size="13" :stroke="2.2" /> อำเภอ</label>
                            <select name="dist">
                                <option value="">ทุกอำเภอ</option>
                                @foreach ($distOptions as $distOption)
                                    <option value="{{ $distOption }}" @selected($fDist === $distOption)>อ.{{ $distOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="f" style="flex:1;min-width:170px">
                            <label><x-icon name="map" :size="13" :stroke="2.2" /> ตำบล</label>
                            <select name="tam">
                                <option value="">ทุกตำบล</option>
                                @foreach ($tamOptions as $tamOption)
                                    <option value="{{ $tamOption }}" @selected($fTam === $tamOption)>ต.{{ Thai::tamName($tamOption) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ฟอร์มนี้ส่งเฉพาะช่องของตัวเอง ตัวกรองอื่นจึงต้องพกไปด้วย
                         ไม่งั้นเปลี่ยนโครงการหรือพื้นที่ทีไร คำค้น/สถานะ/การเรียงจะหายทุกครั้ง --}}
                    <input type="hidden" name="q" value="{{ $filters['q'] }}">
                    <input type="hidden" name="st" value="{{ $filters['st'] }}">
                    <input type="hidden" name="vill" value="{{ $filters['vill'] }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
                    <input type="hidden" name="per" value="{{ $page['per'] }}">
                </form>

                {{-- ไม่แสดงป้ายสรุปข้างช่องกรองแล้ว — ทั้งป้าย PA/ปีงบ/งบ ตอนเลือกกิจกรรม
                     และป้ายจำนวนกิจกรรมตอนยังไม่เลือก ตัวเลขเหล่านี้ดูได้จากการ์ดสรุปด้านล่าง --}}
            </div>

            @if ($activity)
                <div class="pick-name">{{ $activity['name'] }}
                    <div class="pick-prog">{{ $activity['program'] }}</div>
                </div>
            @endif
        </div>
    </div>


    {{-- ------------------------------------------------------------- การ์ดสรุป --}}
    <div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:14px">
        <div class="tile">
            <div class="tl">รายการลงทะเบียน{{ $narrowed ? ' (กรองอยู่)' : '' }}</div>
            <div class="tv">{{ Thai::fmt($scopeCount) }}</div>
            <div class="td">
                {{ $pa ? 'ครัวเรือนไม่ซ้ำ '.$scopeUniqueHouseholds.' ราย' : 'ใน '.$scopeActivityCount.' กิจกรรม' }}
                @if ($narrowed)
                    · จากทั้งหมด {{ Thai::fmt($totalEnrollments) }}
                @endif
            </div>
            <div class="tile-ic"><x-icon name="link" :size="16" :stroke="2" /></div>
        </div>

        {{-- พื้นที่ครอบคลุม — นับแบบไม่ซ้ำจากครัวเรือนที่อยู่ในขอบเขตที่กำลังดู
             ตัวเลขนับตาม «สายเต็ม» จังหวัด›อำเภอ›ตำบล›หมู่บ้าน เพราะชื่อซ้ำกันได้ข้ามอำเภอ --}}
        <div class="tile">
            {{-- นับเฉพาะครัวเรือนที่ลงทะเบียนและอยู่ในตัวกรองที่เลือก
                 หน้าภาพรวมนับทั้งทะเบียน ตัวเลขสองหน้าจึงไม่เท่ากันเป็นปกติ --}}
            <div class="tl">พื้นที่ครอบคลุม · {{ $narrowed || $pa ? 'ที่กรองอยู่' : 'ที่เข้าร่วมแล้ว' }}</div>
            <div class="tv">{{ Thai::fmt($areaCounts['vill']) }}<small> หมู่บ้าน</small></div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:9px">
                <span class="bg">จังหวัด <b>{{ Thai::fmt($areaCounts['prov']) }}</b></span>
                <span class="bg">อำเภอ <b>{{ Thai::fmt($areaCounts['dist']) }}</b></span>
                <span class="bg">ตำบล <b>{{ Thai::fmt($areaCounts['tam']) }}</b></span>
            </div>
            <div class="tile-ic"><x-icon name="map" :size="16" :stroke="2" /></div>
        </div>

        {{-- งบเฉลี่ย/ครัวเรือน คิดได้เฉพาะตอนดูทั้งกิจกรรม
             ถ้ากรองพื้นที่/สถานะ/คำค้นเพิ่ม จำนวนคนจะไม่ครบ หารออกมาแล้วเกินจริง
             กรณีนั้นจึงสลับไปแสดงจำนวนครัวเรือนไม่ซ้ำแทน --}}
        @php $showBudgetPerHousehold = $pa && ! $narrowed; @endphp

        <div class="tile">
            <div class="tl">{{ $showBudgetPerHousehold ? 'งบเฉลี่ย/ครัวเรือน' : 'ครัวเรือนไม่ซ้ำ' }}</div>
            <div class="tv">
                @if ($showBudgetPerHousehold)
                    {{ $scopeCount ? Thai::fmt(round($activity['budget'] / $scopeCount)) : '—' }}
                @else
                    {{ Thai::fmt($scopeUniqueHouseholds) }}
                @endif
            </div>
            <div class="td">
                @if ($showBudgetPerHousehold)
                    บาท จากงบ {{ Thai::fmt($activity['budget']) }}
                @elseif ($narrowed)
                    ในผลลัพธ์ที่กรองอยู่ · ทั้งทะเบียน {{ Thai::fmt($totalHouseholds) }} ครัวเรือน
                @else
                    จากทะเบียน {{ Thai::fmt($totalHouseholds) }} ครัวเรือน
                @endif
            </div>
            <div class="tile-ic"><x-icon name="cash" :size="16" :stroke="2" /></div>
        </div>

        <div class="tile">
            <div class="tl">รายได้ BL เฉลี่ย</div>
            <div class="tv">{{ $incomeAvg !== null ? Thai::fmt($incomeAvg) : '—' }}<small> บาท/ปี</small></div>
            <div class="td">{{ count($incomes) }}/{{ $scopeCount }} มีข้อมูลรายได้</div>
            <div class="meter" style="margin-top:9px">
                <i style="width:{{ $scopeCount ? round(count($incomes) / $scopeCount * 100, 1) : 0 }}%"></i>
            </div>
        </div>

        <div class="tile">
            <div class="tl">รายได้เฉลี่ยหลังเข้าร่วม</div>
            <div class="tv">{{ $incomeAfterAvg !== null ? Thai::fmt($incomeAfterAvg) : '—' }}<small> บาท/ปี</small></div>
            <div class="td">
                @if ($incomeDiffAvg !== null)
                    <span style="color:{{ $incomeDiffAvg > 0 ? 'var(--good-ink)' : ($incomeDiffAvg < 0 ? 'var(--critical-ink)' : 'inherit') }};font-weight:600">
                        @if ($incomeDiffAvg > 0)
                            +{{ Thai::fmt($incomeDiffAvg) }}
                        @elseif ($incomeDiffAvg < 0)
                            −{{ Thai::fmt(abs($incomeDiffAvg)) }}
                        @else
                            เท่าเดิม
                        @endif
                    </span>
                    เทียบรายได้ BL ({{ count($incomePairs) }} ราย)
                @else
                    {{ count($incomesAfter) }}/{{ $scopeCount }} มีข้อมูลรายได้หลังจบ
                @endif
            </div>
            <div class="meter" style="margin-top:9px">
                <i style="width:{{ $scopeCount ? round(count($incomesAfter) / $scopeCount * 100, 1) : 0 }}%"></i>
            </div>
        </div>

        <div class="tile">
            <div class="tl">สถานะการดำเนินงาน</div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:9px">
                @forelse ($statusCounts as $status => $n)
                    <span class="bg {{ $statusClass[$status] ?? '' }}">{{ $status }} <b>{{ $n }}</b></span>
                @empty
                    <span class="t-empty"></span>
                @endforelse
            </div>
            @if ($scopeCount)
                <div class="meter" style="margin-top:11px">
                    <i style="width:{{ round($doneCount / $scopeCount * 100, 1) }}%"></i>
                </div>
                <div class="td">สำเร็จแล้ว {{ round($doneCount / $scopeCount * 100) }}%</div>
            @endif
        </div>
    </div>

    {{-- ---------------------------------------------------------- ตารางรายชื่อ --}}
    <div class="card">
        <div class="card-h">
            <div style="flex:1">
                <h3>รายชื่อครัวเรือน{{ $activity ? '' : ' (ทุกกิจกรรม)' }}</h3>
                <p>{{ Thai::fmt($page['total']) }} รายการ
                    @if ($page['total'] !== $scopeCount)
                        (กรองจาก {{ Thai::fmt($scopeCount) }})
                    @endif
                    {{ $activity ? ' ในกิจกรรมนี้' : '' }}</p>
            </div>
            @if ($pa)
                <a class="btn pri sm" href="{{ route('enrollments.create', qs()) }}">
                    <x-icon name="plus" :size="13" :stroke="2.4" /> เพิ่มรายชื่อ
                </a>
            @endif
        </div>

        <form method="GET" action="{{ route('enrollments.index') }}" class="tbar js-auto">
            <input type="hidden" name="pg" value="{{ $filters['pg'] }}">
            <input type="hidden" name="pa" value="{{ $pa }}">
            {{-- ตัวกรองพื้นที่อยู่คนละฟอร์ม (ในการ์ดด้านบน) จึงต้องพกค่ามาด้วย --}}
            <input type="hidden" name="prov" value="{{ $filters['prov'] }}">
            <input type="hidden" name="dist" value="{{ $filters['dist'] }}">
            <input type="hidden" name="tam" value="{{ $filters['tam'] }}">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
            <input type="hidden" name="per" value="{{ $page['per'] }}">

            <div class="fsearch {{ $filters['q'] ? 'has' : '' }}">
                <x-icon name="search" :size="15" :stroke="2.2" />
                <input name="q" class="js-search" data-autofocus-end value="{{ $filters['q'] }}"
                       placeholder="ค้นหา HC, ชื่อ-สกุล, บ้านเลขที่, รหัสกิจกรรม…">
                <a class="clr" href="{{ route('enrollments.index', qs(['q' => '', 'page' => 1])) }}">
                    <x-icon name="x" :size="14" :stroke="2.4" />
                </a>
            </div>

            <select class="sel" name="st">
                <option value="">ทุกสถานะ</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected($filters['st'] === $s)>{{ $s }}</option>
                @endforeach
            </select>

            <select class="sel" name="vill">
                <option value="">ทุกหมู่บ้าน</option>
                @foreach ($villages as $v)
                    <option value="{{ $v }}" @selected($filters['vill'] === $v)>{{ $v }}</option>
                @endforeach
            </select>

            @if ($filters['q'] || $filters['st'] || $filters['vill'] || $filters['prov'] || $filters['dist'] || $filters['tam'])
                <a class="btn ghost sm" href="{{ route('enrollments.index', ['pa' => $pa]) }}">ล้างตัวกรอง</a>
            @endif

            <span style="flex:1"></span>
            <span class="note" style="font-size:12px">
                <x-icon name="info" :size="13" :stroke="2.1" />
                <span>คลิกหัวตารางเพื่อเรียงลำดับ{{ $pa ? '' : ' · เลื่อนตารางแนวนอนเพื่อดูคอลัมน์อื่น' }}</span>
            </span>
        </form>

        {{-- ------------------------------------------ แถบจัดการแบบกลุ่ม --}}
        <div class="bulk" data-bulk-bar hidden>
            <b>เลือก <span data-sel-count>0</span> รายการ</b>

            <button class="btn sm" data-modal-open="m-en-status">
                <x-icon name="swap" :size="13" :stroke="2.2" /> เปลี่ยนสถานะ
            </button>
            <button class="btn sm" data-modal-open="m-en-move">
                <x-icon name="link" :size="13" :stroke="2.2" /> ย้ายไปกิจกรรมอื่น
            </button>

            <form method="POST" action="{{ route('enrollments.bulk', 'remove') }}" class="f-inline">
                @csrf
                <input type="hidden" name="ids" data-fill-selection value="">
                <button type="button" class="btn dang sm" data-confirm
                        data-confirm-title="นำออกจากกิจกรรม"
                        data-confirm-label="นำออกจากกิจกรรม"
                        data-confirm-body="ข้อมูลครัวเรือนในทะเบียนจะยังอยู่ครบ ลบเฉพาะการลงทะเบียนกิจกรรมเท่านั้น">
                    <x-icon name="x" :size="13" :stroke="2.4" /> นำออกจากกิจกรรม
                </button>
            </form>

            <button class="btn ghost sm" style="margin-left:auto" data-sel-clear>ยกเลิกการเลือก</button>
        </div>

        <div class="tw">
            <table>
                <thead>
                <tr>
                    <th style="width:36px" class="c">
                        <input type="checkbox" id="ckEnAll" aria-label="เลือกทั้งหน้า">
                    </th>
                    <x-th field="hc" label="รหัส HC" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="width:112px" />
                    <x-th field="name" label="ชื่อ - สกุล" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="min-width:186px" />
                    <x-th field="vill" label="หมู่บ้าน" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="min-width:120px" />
                    <x-th field="moo" label="หมู่" align="c" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="width:60px" />
                    <x-th field="tam" label="ตำบล" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="min-width:104px" />
                    <x-th field="dist" label="อำเภอ" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="min-width:104px" />
                    <th style="min-width:96px">จังหวัด</th>
                    @if ($pa)
                        <th style="min-width:118px">ติดต่อ</th>
                    @else
                        <x-th field="pa" label="กิจกรรม" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="width:236px" />
                    @endif
                    <x-th field="joined" label="วันที่เข้าร่วม" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="width:118px" />
                    <x-th field="status" label="สถานะ" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="width:170px" />
                    <x-th field="income" label="รายได้ก่อนเข้าร่วม" align="r" route-name="enrollments.index" :sort="$filters['sort']" :dir="$filters['dir']" width="width:126px" />
                    <th class="r" style="width:126px">รายได้หลังเข้าร่วม</th>
                    <th style="min-width:170px">หมายเหตุ</th>
                    <th style="width:44px"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $e)
                    <tr>
                        <td class="c">
                            <input type="checkbox" data-cken="{{ $e['id'] }}" aria-label="เลือก {{ $e['hc'] }}">
                        </td>
                        <td><span class="code">{{ $e['hc'] }}</span></td>
                        <td>
                            <div class="t-name" style="white-space:nowrap">{{ $e['h']['name'] }}</div>
                            <div class="t-sub">บ้านเลขที่ {{ $e['h']['house'] }}</div>
                        </td>
                        <td style="white-space:nowrap">{{ $e['h']['vill'] }}</td>
                        <td class="c">
                            @if (trim((string) $e['h']['moo']) !== '')
                                <span class="pill">{{ $e['h']['moo'] }}</span>
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td style="white-space:nowrap">
                            @if ($e['h']['tam'])
                                ต.{{ Thai::tamName($e['h']['tam']) }}
                            @else
                                <span class="bg b-crit">ไม่ระบุ</span>
                            @endif
                        </td>
                        <td style="white-space:nowrap">
                            @if (trim((string) $e['h']['dist']) !== '')
                                อ.{{ $e['h']['dist'] }}
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td style="white-space:nowrap">
                            @if ($e['h']['prov'])
                                จ.{{ $e['h']['prov'] }}
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>

                        @if ($pa)
                            <td>
                                @if ($e['h']['phone'])
                                    <span class="num">{{ $e['h']['phone'] }}</span>
                                @else
                                    <span class="t-empty"></span>
                                @endif
                            </td>
                        @else
                            <td>
                                <span class="code" style="color:var(--brand);font-weight:600;font-size:12px">{{ $e['pa'] }}</span>
                                <div class="t-sub" style="max-width:236px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                     data-tip="<b>{{ $e['pa'] }}</b>{{ e($e['p']['name'] ?? '') }}">{{ $e['p']['name'] ?? '' }}</div>
                            </td>
                        @endif

                        <td><span class="num">{{ Thai::date($e['joined']) }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('enrollments.status', ['id' => $e['id']] + qs()) }}" class="f-inline">
                                @csrf
                                @method('PATCH')
                                <select class="sel" name="status" data-status
                                        data-en-id="{{ $e['id'] }}"
                                        data-en-hc="{{ $e['hc'] }}"
                                        data-en-name="{{ $e['h']['name'] }}"
                                        data-en-income="{{ $e['income_after'] ?? '' }}"
                                        data-en-income-before="{{ $e['income_before'] ?? '' }}"
                                        data-en-note="{{ $e['note'] }}"
                                        style="height:30px;font-size:12.5px;max-width:148px" aria-label="สถานะ">
                                    @foreach ($statuses as $s)
                                        <option @selected($s === $e['status'])>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </form>

                        </td>
                        @php
                            /* รายได้ก่อนเข้าร่วมที่จดไว้กับรายการนี้ — ถ้ายังไม่มี (แถวเก่า)
                               ถอยไปใช้รายได้ปัจจุบันของครัวเรือน แล้วติดป้ายบอกว่าเป็นค่าอ้างอิง */
                            $before = $e['income_before'] ?? null;
                            $beforeIsSnapshot = $before !== null;
                            $before ??= $e['h']['income'];
                        @endphp

                        <td class="r">
                            @if ($before !== null)
                                <span class="num" style="font-weight:600">{{ Thai::fmt($before) }}</span>
                                @unless ($beforeIsSnapshot)
                                    <div class="t-sub" data-tip="ยังไม่ได้บันทึกรายได้ก่อนเข้าร่วมของรายการนี้<br>ตัวเลขที่เห็นมาจากทะเบียนครัวเรือนปัจจุบัน">อ้างอิงทะเบียน</div>
                                @endunless
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td class="r">
                            @php
                                $after = $e['income_after'] ?? null;
                                /* ส่วนต่างคำนวณได้เมื่อมีทั้งรายได้ตั้งต้นและรายได้หลังจบ */
                                $diff = ($after !== null && $before !== null) ? $after - $before : null;
                            @endphp
                            @if ($after !== null)
                                <span class="num" style="font-weight:600">{{ Thai::fmt($after) }}</span>
                                @if ($diff !== null && $diff !== 0)
                                    <div class="t-sub {{ $diff > 0 ? '' : '' }}"
                                         style="color:{{ $diff > 0 ? 'var(--good-ink)' : 'var(--critical-ink)' }}">
                                        {{ $diff > 0 ? '+' : '−' }}{{ Thai::fmt(abs($diff)) }}
                                    </div>
                                @endif
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td>
                            @if (trim($e['note']) !== '')
                                <div style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12.3px"
                                     data-tip="<b>หมายเหตุ</b>{{ e($e['note']) }}">{{ $e['note'] }}</div>
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td>
                            <div class="rowacts">
                                {{-- ครัวเรือนที่ไม่มีรหัส HC สร้างลิงก์ไม่ได้ — ถ้าฝืนสร้างจะพังทั้งหน้า --}}
                                @if (trim((string) $e['hc']) !== '')
                                    <a class="ia" href="{{ route('households.show', $e['hc']) }}" title="ดูรายละเอียดครัวเรือน">
                                        <x-icon name="eye" :size="15" :stroke="2" />
                                    </a>
                                @endif
                                {{-- แก้ไขตัวเลขและหมายเหตุของรายการนี้ โดยไม่ต้องเปลี่ยนสถานะ --}}
                                <button type="button" class="ia" data-en-edit
                                        data-en-id="{{ $e['id'] }}"
                                        data-en-hc="{{ $e['hc'] }}"
                                        data-en-name="{{ $e['h']['name'] }}"
                                        data-en-pa="{{ $e['pa'] }}"
                                        data-en-status="{{ $e['status'] }}"
                                        data-en-joined="{{ $e['joined'] }}"
                                        data-en-before="{{ $e['income_before'] ?? '' }}"
                                        data-en-after="{{ $e['income_after'] ?? '' }}"
                                        data-en-note="{{ $e['note'] }}" title="แก้ไขรายได้และหมายเหตุ">
                                    <x-icon name="edit" :size="15" :stroke="2" />
                                </button>

                                <form method="POST" action="{{ route('enrollments.destroy', ['id' => $e['id']] + qs()) }}" class="f-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="ia d" title="นำออกจากกิจกรรม" data-confirm
                                            data-confirm-title="นำออกจากกิจกรรม"
                                            data-confirm-label="นำออก"
                                            data-confirm-body="นำ <b>{{ e($e['h']['name']) }}</b> ออกจากกิจกรรม {{ $e['pa'] }}">
                                        <x-icon name="x" :size="15" :stroke="2.2" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15">
                            <div class="empty">
                                <div class="ic"><x-icon name="users" :size="22" :stroke="1.9" /></div>
                                @if ($filters['q'] || $filters['st'] || $filters['vill'])
                                    <b>ไม่พบรายชื่อที่ตรงกับเงื่อนไข</b>
                                    <p>ลองลดตัวกรองลง หรือค้นหาด้วยคำอื่น</p>
                                    <a class="btn out sm" style="margin-top:14px"
                                       href="{{ route('enrollments.index', ['pa' => $pa]) }}">ล้างตัวกรอง</a>
                                @else
                                    <b>ยังไม่มีครัวเรือนในกิจกรรมนี้</b>
                                    <p>กด «เพิ่มรายชื่อ» เพื่อเลือกครัวเรือนจากทะเบียนเข้าร่วมกิจกรรม</p>
                                    @if ($pa)
                                        <a class="btn pri sm" style="margin-top:14px" href="{{ route('enrollments.create', qs()) }}">
                                            <x-icon name="plus" :size="13" :stroke="2.4" /> เพิ่มรายชื่อ
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- ----------------------------------- มุมมองการ์ด (หน้าจอเล็ก) --}}
        <div class="mcards">
            @foreach ($rows as $e)
                <div class="mcard">
                    <div class="mc-top">
                        <input type="checkbox" data-cken="{{ $e['id'] }}" aria-label="เลือก {{ $e['hc'] }}">
                        <span class="code" style="font-size:12.5px">{{ $e['hc'] }}</span>
                        <span class="bg {{ $statusClass[$e['status']] ?? '' }}">{{ $e['status'] }}</span>
                        <span style="flex:1"></span>
                        @if (trim((string) $e['hc']) !== '')
                            <a class="ia" href="{{ route('households.show', $e['hc']) }}" aria-label="ดูรายละเอียด">
                                <x-icon name="eye" :size="16" :stroke="2" />
                            </a>
                        @endif
                    </div>
                    <div class="mc-name">{{ $e['h']['name'] }}</div>
                    <div class="mc-meta">บ้านเลขที่ {{ $e['h']['house'] }} · {{ $e['h']['vill'] }}
                        ม.{{ $e['h']['moo'] }} · เข้าร่วม {{ Thai::date($e['joined']) }}</div>
                    <div class="mc-tags">
                        @if (! $pa)
                            <span class="bg b-brand"><span class="code">{{ $e['pa'] }}</span></span>
                        @endif
                        @if ($e['h']['tam'])
                            <span class="bg">ต.{{ Thai::tamName($e['h']['tam']) }}</span>
                        @endif
                        @if ($e['h']['prov'])
                            <span class="bg">จ.{{ $e['h']['prov'] }}</span>
                        @endif
                        @if (($e['income_after'] ?? null) !== null)
                            <span class="bg b-good">หลังเข้าร่วม
                                <span class="num">{{ Thai::fmt($e['income_after']) }}</span></span>
                        @endif
                        @if ($e['h']['phone'])
                            <span class="bg"><x-icon name="phone" :size="11" :stroke="2.2" />
                                <span class="num">{{ $e['h']['phone'] }}</span></span>
                        @endif
                        @if ($e['h']['income'] !== null)
                            <span class="bg b-good"><span class="num">{{ Thai::fmt($e['h']['income']) }}</span> บาท/ปี</span>
                        @else
                            <span class="bg out">ไม่มีข้อมูลรายได้</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <x-pager :page="$page" route-name="enrollments.index" :total-all="$scopeCount" />
    </div>

    <div class="note" style="margin-top:14px">
        <x-icon name="info" :size="14" :stroke="2" />
        <span>ชีต «รายชื่อเข้าร่วมโครงการ» อ่านผ่านลิงก์แชร์ไม่ได้ ระบบจึงจำลองโครงสร้างและตัวอย่างการจับคู่ไว้ให้ครบทุกฟิลด์
            (HC · PA · วันที่เข้าร่วม · สถานะ · หมายเหตุ) — เมื่อเปิดสิทธิ์อ่านชีตแล้วจะนำเข้าทับได้ทันทีที่หน้า
            <b>นำเข้า/ส่งออกข้อมูล</b></span>
    </div>

    {{-- ------------------ หน้าต่างกรอกข้อมูลเพิ่ม ตอนเลือกสถานะ สำเร็จ / ออกกลางคัน
         JS เป็นคนเปิดและเติมค่าให้ (ดู initEnrollStatus ใน app.js) --}}
    <div class="modal sm js-modal" id="m-en-detail" role="dialog" aria-modal="true">
        <form method="POST" id="enDetailForm" action="">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" id="enDetailStatus" value="">

            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="chk" :size="18" :stroke="2.2" /></div>
                <div style="flex:1">
                    <h3 id="enDetailTitle">บันทึกผลการเข้าร่วม</h3>
                    <p id="enDetailWho"></p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="ปิด">
                    <x-icon name="x" :size="16" :stroke="2.2" />
                </button>
            </div>

            <div class="md-b">
                <div class="fgrid">
                    <div class="f full" id="enDetailIncomeBeforeBox">
                        <label>รายได้ก่อนเข้าร่วม <span class="req">*</span> <span class="tag-sug">บาท/ปี</span></label>
                        <input name="income_before" id="enDetailIncomeBefore" type="number" min="0" step="1000"
                               placeholder="เช่น 120000">
                        <span class="hint">
                            ค่าที่จดไว้ตอนเข้าร่วม — แก้ได้ถ้าตัวเลขตอนนั้นคลาดเคลื่อน
                            การแก้ที่นี่ไม่กระทบรายได้ในทะเบียนครัวเรือน
                        </span>
                    </div>

                    <div class="f full" id="enDetailIncomeBox">
                        <label>รายได้หลังเข้าร่วม <span class="tag-sug">บาท/ปี</span></label>
                        <input name="income_after" id="enDetailIncome" type="number" min="0" step="1000"
                               placeholder="เช่น 145000">
                        <span class="hint" id="enDetailIncomeHint">
                            เว้นว่างได้ถ้ายังเก็บตัวเลขไม่ได้ — กลับมากรอกภายหลังได้
                        </span>
                    </div>

                    <div class="f full">
                        <label>หมายเหตุ <span class="req" id="enDetailNoteReq" hidden>*</span></label>
                        <textarea name="note" id="enDetailNote" rows="3"
                                  placeholder="บันทึกผลที่เกิดขึ้น หรือเหตุผล"></textarea>
                        <span class="hint" id="enDetailNoteHint"></span>
                    </div>
                </div>
            </div>

            <div class="md-f">
                <span style="flex:1"></span>
                <button type="button" class="btn out" data-modal-close>ยกเลิก</button>
                <button class="btn pri"><x-icon name="chk" :size="14" :stroke="2.4" /> บันทึก</button>
            </div>
        </form>
    </div>

    {{-- --------------------------------------- หน้าต่างจัดการแบบกลุ่ม --}}
    <div class="modal sm js-modal" id="m-en-status" role="dialog" aria-modal="true">
        <form method="POST" action="{{ route('enrollments.bulk', 'status') }}">
            @csrf
            <input type="hidden" name="ids" data-fill-selection value="">
            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="swap" :size="19" :stroke="2.1" /></div>
                <div style="flex:1">
                    <h3>เปลี่ยนสถานะ <span data-selection-count>0</span> รายการ</h3>
                    <p>ปรับสถานะการดำเนินงานพร้อมกันทั้งชุด</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close><x-icon name="x" :size="16" :stroke="2.2" /></button>
            </div>
            <div class="md-b">
                <div class="f full">
                    <label>สถานะใหม่</label>
                    <select name="status">
                        @foreach ($statuses as $s)
                            <option>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="md-f">
                <span style="flex:1"></span>
                <button type="button" class="btn out" data-modal-close>ยกเลิก</button>
                <button class="btn pri"><x-icon name="chk" :size="14" :stroke="2.4" /> เปลี่ยนสถานะ</button>
            </div>
        </form>
    </div>

    <div class="modal sm js-modal" id="m-en-move" role="dialog" aria-modal="true">
        <form method="POST" action="{{ route('enrollments.bulk', 'move') }}">
            @csrf
            <input type="hidden" name="ids" data-fill-selection value="">
            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="link" :size="19" :stroke="2.1" /></div>
                <div style="flex:1">
                    <h3>ย้าย <span data-selection-count>0</span> รายการไปกิจกรรมอื่น</h3>
                    <p>ใช้เมื่อลงทะเบียนผิดกิจกรรม หรือขยายผลไปกิจกรรมต่อเนื่อง</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close><x-icon name="x" :size="16" :stroke="2.2" /></button>
            </div>
            <div class="md-b">
                <div class="f full">
                    <label>กิจกรรมปลายทาง</label>
                    <select name="pa">
                        @foreach ($fiscalYears as $year)
                            <optgroup label="ปีงบ {{ $year }}">
                                @foreach ($activities as $p)
                                    @continue($p['fy'] !== $year)
                                    <option value="{{ $p['pa'] }}">{{ $p['pa'] }} · {{ mb_substr($p['name'], 0, 70) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="f full" style="margin-top:14px">
                    <label>วิธีย้าย</label>
                    <select name="mode">
                        <option value="move">ย้าย — นำออกจากกิจกรรมเดิม</option>
                        <option value="copy">คัดลอก — คงไว้ในกิจกรรมเดิมด้วย</option>
                    </select>
                </div>
            </div>
            <div class="md-f">
                <span style="flex:1"></span>
                <button type="button" class="btn out" data-modal-close>ยกเลิก</button>
                <button class="btn pri"><x-icon name="chk" :size="14" :stroke="2.4" /> ยืนยัน</button>
            </div>
        </form>
    </div>
    {{-- ------------------------------------------------ แก้ไขรายการเข้าร่วม
         ใช้ปลายทางเดียวกับการเปลี่ยนสถานะ แต่ส่งสถานะเดิมกลับไปโดยไม่แตะต้อง
         จึงไม่ต้องเพิ่มเส้นทางใหม่ และกฎตรวจฝั่งเซิร์ฟเวอร์ยังเป็นชุดเดียวกัน --}}
    <div class="modal sm js-modal" id="m-en-edit" role="dialog" aria-modal="true">
        <form method="POST" id="enEditForm" action="">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" id="enEditStatus" value="">

            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="edit" :size="18" :stroke="2.2" /></div>
                <div style="flex:1">
                    <h3>แก้ไขรายการเข้าร่วม</h3>
                    <p id="enEditWho"></p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="ปิด">
                    <x-icon name="x" :size="16" :stroke="2.2" />
                </button>
            </div>

            <div class="md-b">
                <div class="fgrid">
                    <div class="f full">
                        {{-- วันที่เก็บเป็น พ.ศ. ทั้งระบบ (เช่น 2569-08-11) ช่องนี้จึงรับ-ส่งปี พ.ศ. ตรง ๆ
                             ไม่แปลงเป็น ค.ศ. เพื่อให้ตรงกับที่แสดงในตารางและกับข้อมูลเดิมจากชีต --}}
                        <label>วันที่เข้าร่วม <span class="tag-sug">พ.ศ.</span></label>
                        <input name="joined_at" id="enEditJoined" type="date">
                        <span class="hint" id="enEditJoinedHint">ปีเป็น พ.ศ. เช่น 2569</span>
                    </div>

                    <div class="f full">
                        <label>รายได้ก่อนเข้าร่วม <span class="req">*</span> <span class="tag-sug">บาท/ปี</span></label>
                        <input name="income_before" id="enEditBefore" type="number" min="0" step="1000" required
                               placeholder="เช่น 120000">
                        <span class="hint">ใส่ 0 ได้ถ้าไม่มีรายได้ · แก้ที่นี่ไม่กระทบรายได้ในทะเบียนครัวเรือน</span>
                    </div>

                    <div class="f full">
                        <label>รายได้หลังเข้าร่วม <span class="tag-sug">บาท/ปี</span></label>
                        <input name="income_after" id="enEditAfter" type="number" min="0" step="1000"
                               placeholder="เช่น 145000">
                        <span class="hint" id="enEditDiff">เว้นว่างได้ถ้ายังเก็บตัวเลขไม่ได้</span>
                    </div>

                    <div class="f full">
                        <label>หมายเหตุ <span class="req" id="enEditNoteReq" hidden>*</span></label>
                        <textarea name="note" id="enEditNote" rows="3"
                                  placeholder="บันทึกผลที่เกิดขึ้น หรือเหตุผล"></textarea>
                        <span class="hint" id="enEditNoteHint"></span>
                    </div>
                </div>
            </div>

            <div class="md-f">
                <span style="flex:1"></span>
                <button type="button" class="btn out" data-modal-close>ยกเลิก</button>
                <button class="btn pri"><x-icon name="chk" :size="14" :stroke="2.4" /> บันทึก</button>
            </div>
        </form>
    </div>

    {{-- แม่แบบ URL สำหรับส่งฟอร์ม — JS แทนที่ __ID__ ด้วยรหัสรายการที่กำลังแก้ --}}
    <script type="application/json" id="en-status-url">@json(route('enrollments.status', ['id' => '__ID__'] + qs()))</script>
@endsection
