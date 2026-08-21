@extends('layouts.app')

@php
    use App\Support\Thai;

    $rows = $page['rows'];
    $quality = app(\App\Repositories\DataQualityAnalyzer::class);
    $enrollments = app(\App\Repositories\EnrollmentRepository::class);

    /* ป้ายตัวกรองที่กำลังใช้งาน */
    $chips = [];
    if ($filters['q']) $chips['q'] = 'ค้นหา: “'.$filters['q'].'”';
    if ($filters['prov']) $chips['prov'] = 'จังหวัด'.$filters['prov'];
    if ($filters['dist']) $chips['dist'] = 'อำเภอ'.$filters['dist'];
    if ($filters['tam']) $chips['tam'] = 'ตำบล'.Thai::tamName($filters['tam']);
    if ($filters['vill']) $chips['vill'] = 'หมู่บ้าน'.$filters['vill'];
    if ($filters['inc']) $chips['inc'] = [
        'y' => 'มีข้อมูลรายได้', 'n' => 'ไม่มีข้อมูลรายได้',
        'p' => 'เข้าร่วมกิจกรรมแล้ว', 'np' => 'ยังไม่เข้าร่วมกิจกรรม',
    ][$filters['inc']] ?? '';
    if (! empty($preselect)) $chips['hcs'] = 'เฉพาะ '.count($preselect).' รายการที่เลือกไว้';
@endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>ทะเบียนครัวเรือน</h2>
            <p>ชีต «ครัวเรือน» · {{ Thai::fmt($totalAll) }} ครัวเรือน ·
                รหัส HC = PY + ปีงบ 2 หลัก + รหัสพื้นที่ 2 หลัก + ลำดับ 5 หลัก</p>
        </div>

        <form method="GET" action="{{ route('export', 'households') }}" class="f-inline">
            @foreach (qs([], ['page', 'hcs']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button class="btn out"><x-icon name="down" :size="15" :stroke="2.2" /> ส่งออก CSV</button>
        </form>

        <a class="btn pri" href="{{ route('households.create', qs()) }}">
            <x-icon name="plus" :size="15" :stroke="2.4" /> เพิ่มครัวเรือน
        </a>
    </div>

    <div class="card">
        {{-- ------------------------------------------------------- ตัวกรอง --}}
        <form method="GET" action="{{ route('households.index') }}" class="tbar js-auto">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
            <input type="hidden" name="per" value="{{ $page['per'] }}">

            <div class="fsearch {{ $filters['q'] ? 'has' : '' }}">
                <x-icon name="search" :size="15" :stroke="2.2" />
                <input name="q" class="js-search" data-autofocus-end value="{{ $filters['q'] }}"
                       placeholder="ค้นหา HC, ชื่อ-สกุล, บ้านเลขที่, เบอร์โทร…">
                <a class="clr" href="{{ route('households.index', qs(['q' => '', 'page' => 1])) }}">
                    <x-icon name="x" :size="14" :stroke="2.4" />
                </a>
            </div>

            <select class="sel" name="prov">
                <option value="">ทุกจังหวัด</option>
                @foreach ($provinces as $p)
                    <option value="{{ $p }}" @selected($filters['prov'] === $p)>{{ $p }}</option>
                @endforeach
            </select>

            <select class="sel" name="dist">
                <option value="">ทุกอำเภอ</option>
                @foreach ($districts as $d)
                    <option value="{{ $d }}" @selected($filters['dist'] === $d)>อ.{{ $d }}</option>
                @endforeach
            </select>

            <select class="sel" name="tam">
                <option value="">ทุกตำบล</option>
                @foreach ($tambons as $t)
                    <option value="{{ $t }}" @selected($filters['tam'] === $t)>ต.{{ Thai::tamName($t) }}</option>
                @endforeach
            </select>

            <select class="sel" name="vill">
                <option value="">ทุกหมู่บ้าน</option>
                @foreach ($villages as $v)
                    <option value="{{ $v }}" @selected($filters['vill'] === $v)>{{ $v }}</option>
                @endforeach
            </select>

            <select class="sel" name="inc">
                <option value="">สถานะข้อมูล: ทั้งหมด</option>
                <option value="y" @selected($filters['inc'] === 'y')>มีข้อมูลรายได้</option>
                <option value="n" @selected($filters['inc'] === 'n')>ไม่มีข้อมูลรายได้</option>
                <option value="p" @selected($filters['inc'] === 'p')>เข้าร่วมกิจกรรมแล้ว</option>
                <option value="np" @selected($filters['inc'] === 'np')>ยังไม่เข้าร่วมกิจกรรม</option>
            </select>

            @if ($chips)
                <a class="btn ghost sm" href="{{ route('households.index') }}">ล้างตัวกรอง</a>
            @endif
        </form>

        @if ($chips)
            <div class="chips">
                <span style="font-size:12px;color:var(--ink-3)">ตัวกรอง</span>
                @foreach ($chips as $key => $label)
                    <span class="chip">{{ $label }}
                        <a href="{{ route('households.index', qs([$key => '', 'page' => 1])) }}" aria-label="ลบตัวกรอง">
                            <x-icon name="x" :size="12" :stroke="2.6" />
                        </a>
                    </span>
                @endforeach
            </div>
        @endif

        {{-- ------------------------------------------- แถบจัดการแบบกลุ่ม --}}
        <div class="bulk" data-bulk-bar hidden>
            <b>เลือก <span data-sel-count>0</span> รายการ</b>

            <button class="btn sm" data-modal-open="m-enroll">
                <x-icon name="link" :size="13" :stroke="2.2" /> เพิ่มเข้ากิจกรรม
            </button>
            <button class="btn sm" data-modal-open="m-area">
                <x-icon name="map" :size="13" :stroke="2.2" /> แก้พื้นที่
            </button>

            <form method="GET" action="{{ route('export', 'households') }}" class="f-inline">
                <input type="hidden" name="hcs" data-fill-selection value="">
                <button class="btn sm"><x-icon name="down" :size="13" :stroke="2.2" /> ส่งออกที่เลือก</button>
            </form>

            <form method="POST" action="{{ route('households.bulk', 'delete') }}" class="f-inline">
                @csrf
                <input type="hidden" name="hcs" data-fill-selection value="">
                <button type="button" class="btn dang sm" data-confirm
                        data-confirm-title="ยืนยันการลบหลายรายการ"
                        data-confirm-label="ลบรายการที่เลือก"
                        data-confirm-body="กำลังจะลบครัวเรือนที่เลือก พร้อมรายการลงทะเบียนกิจกรรมที่เกี่ยวข้อง">
                    <x-icon name="trash" :size="13" :stroke="2.2" /> ลบ
                </button>
            </form>

            <button class="btn ghost sm" style="margin-left:auto" data-sel-clear>ยกเลิกการเลือก</button>
        </div>

        {{-- ---------------------------------------------------------- ตาราง --}}
        <div class="tw">
            <table>
                <thead>
                <tr>
                    <th style="width:36px" class="c">
                        <input type="checkbox" id="ckAll" aria-label="เลือกทั้งหน้า">
                    </th>
                    <x-th field="hc" label="รหัส HC" route-name="households.index" :sort="$filters['sort']" :dir="$filters['dir']" />
                    <x-th field="name" label="ชื่อ - สกุล" route-name="households.index" :sort="$filters['sort']" :dir="$filters['dir']" />
                    <x-th field="vill" label="หมู่บ้าน / หมู่" route-name="households.index" :sort="$filters['sort']" :dir="$filters['dir']" />
                    <th>ตำบล · อำเภอ</th>
                    <x-th field="prov" label="จังหวัด" route-name="households.index" :sort="$filters['sort']" :dir="$filters['dir']" />
                    <x-th field="phone" label="ติดต่อ" route-name="households.index" :sort="$filters['sort']" :dir="$filters['dir']" />
                    <x-th field="income" label="รายได้ BL" align="r" route-name="households.index" :sort="$filters['sort']" :dir="$filters['dir']" />
                    <x-th field="pj" label="กิจกรรม" align="c" route-name="households.index" :sort="$filters['sort']" :dir="$filters['dir']" />
                    <th style="width:104px"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $h)
                    @php
                        $en = $enrollments->forHousehold($h['hc']);
                        $flagged = $quality->isFlagged($h['hc']);
                        $checked = in_array($h['hc'], $preselect ?? [], true);
                    @endphp
                    <tr class="{{ $checked ? 'sel-row' : '' }}">
                        <td class="c">
                            <input type="checkbox" data-ck="{{ $h['hc'] }}" @checked($checked)
                                   aria-label="เลือก {{ $h['hc'] }}">
                        </td>
                        <td>
                            <span class="code">{{ $h['hc'] }}</span>
                            @if ($flagged)
                                <span data-tip="พบประเด็นคุณภาพข้อมูล — ดูหน้าตรวจสอบ"
                                      style="color:var(--critical-ink);vertical-align:-2px">
                                    <x-icon name="warn" :size="13" :stroke="2.3" />
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="t-name">{{ $h['name'] }}</div>
                            <div class="t-sub">บ้านเลขที่ {{ $h['house'] }}</div>
                        </td>
                        <td>{{ $h['vill'] }} <span class="pill">ม.{{ $h['moo'] }}</span></td>
                        <td>
                            @if ($h['tam'])
                                ต.{{ Thai::tamName($h['tam']) }} <span class="pill">{{ Thai::tamCode($h['tam']) }}</span>
                            @else
                                <span class="bg b-crit"><x-icon name="warn" :size="11" :stroke="2.4" /> ไม่ระบุ</span>
                            @endif
                            <div class="t-sub">อ.{{ $h['dist'] }}</div>
                        </td>
                        <td>
                            @if ($h['prov'])
                                จ.{{ $h['prov'] }}
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td>
                            @if ($h['phone'])
                                <span class="num">{{ $h['phone'] }}</span>
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td class="r">
                            @if ($h['income'] !== null)
                                <span class="num" style="font-weight:600">{{ Thai::fmt($h['income']) }}</span>
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td class="c">
                            @if ($en)
                                <span class="bg b-brand"
                                      data-tip="{{ collect($en)->map(fn ($x) => $x['pa'].' · '.$x['status'])->implode('<br>') }}">{{ count($en) }}</span>
                            @else
                                <span class="t-empty"></span>
                            @endif
                        </td>
                        <td>
                            <div class="rowacts">
                                <a class="ia" href="{{ route('households.show', $h['hc']) }}" title="ดูรายละเอียด">
                                    <x-icon name="eye" :size="15" :stroke="2" />
                                </a>
                                <a class="ia" href="{{ route('households.edit', ['hc' => $h['hc']] + qs()) }}" title="แก้ไข">
                                    <x-icon name="edit" :size="15" :stroke="2" />
                                </a>
                                @if (count($en))
                                    {{-- เข้าร่วมโครงการอยู่ → ลบไม่ได้ (ฝั่งเซิร์ฟเวอร์ก็ปฏิเสธเช่นกัน) --}}
                                    <span class="ia" style="opacity:.32;cursor:not-allowed"
                                          data-tip="<b>ลบไม่ได้</b>อยู่ใน {{ count($en) }} กิจกรรม — ถอนออกจากกิจกรรมก่อน">
                                        <x-icon name="trash" :size="15" :stroke="2" />
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('households.destroy', ['hc' => $h['hc']] + qs()) }}" class="f-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="ia d" title="ลบ" data-confirm
                                                data-confirm-title="ยืนยันการลบครัวเรือน"
                                                data-confirm-label="ลบครัวเรือน"
                                                data-confirm-body="กำลังจะลบ <b>{{ e($h['name']) }}</b> ({{ $h['hc'] }})">
                                            <x-icon name="trash" :size="15" :stroke="2" />
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="empty">
                                <div class="ic"><x-icon name="search" :size="22" :stroke="1.9" /></div>
                                <b>ไม่พบครัวเรือนที่ตรงกับเงื่อนไข</b>
                                <p>ลองลดตัวกรองลง หรือค้นหาด้วยคำอื่น</p>
                                <a class="btn out sm" style="margin-top:14px" href="{{ route('households.index') }}">
                                    ล้างตัวกรองทั้งหมด
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- ----------------------------------- มุมมองการ์ด (หน้าจอเล็ก) --}}
        <div class="mcards">
            @forelse ($rows as $h)
                @php
                    $en = $enrollments->forHousehold($h['hc']);
                    $flagged = $quality->isFlagged($h['hc']);
                @endphp
                <div class="mcard">
                    <div class="mc-top">
                        <input type="checkbox" data-ck="{{ $h['hc'] }}" aria-label="เลือก {{ $h['hc'] }}">
                        <span class="code" style="font-size:12.5px">{{ $h['hc'] }}</span>
                        @if ($flagged)
                            <span style="color:var(--critical-ink)"><x-icon name="warn" :size="13" :stroke="2.3" /></span>
                        @endif
                        @if ($en)
                            <span class="bg b-brand"><x-icon name="link" :size="11" :stroke="2.3" /> {{ count($en) }}</span>
                        @endif
                        <span style="flex:1"></span>
                        <a class="ia" href="{{ route('households.show', $h['hc']) }}" aria-label="ดูรายละเอียด">
                            <x-icon name="eye" :size="16" :stroke="2" />
                        </a>
                        <a class="ia" href="{{ route('households.edit', $h['hc']) }}" aria-label="แก้ไข">
                            <x-icon name="edit" :size="16" :stroke="2" />
                        </a>
                    </div>
                    <div class="mc-name">{{ $h['name'] }}</div>
                    <div class="mc-meta">บ้านเลขที่ {{ $h['house'] }} · {{ $h['vill'] }} ม.{{ $h['moo'] }}</div>
                    <div class="mc-tags">
                        @if ($h['tam'])
                            <span class="bg">ต.{{ Thai::tamName($h['tam']) }} · รหัส {{ Thai::tamCode($h['tam']) }}</span>
                        @else
                            <span class="bg b-crit"><x-icon name="warn" :size="11" :stroke="2.4" /> ไม่ระบุตำบล</span>
                        @endif
                        <span class="bg">อ.{{ $h['dist'] }}</span>
                        @if ($h['prov'])
                            <span class="bg">จ.{{ $h['prov'] }}</span>
                        @endif
                        @if ($h['phone'])
                            <span class="bg"><x-icon name="phone" :size="11" :stroke="2.2" />
                                <span class="num">{{ $h['phone'] }}</span></span>
                        @endif
                        @if ($h['income'] !== null)
                            <span class="bg b-good"><span class="num">{{ Thai::fmt($h['income']) }}</span> บาท/ปี</span>
                        @else
                            <span class="bg out">ไม่มีข้อมูลรายได้</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty">
                    <div class="ic"><x-icon name="search" :size="22" :stroke="1.9" /></div>
                    <b>ไม่พบครัวเรือนที่ตรงกับเงื่อนไข</b>
                    <p>ลองลดตัวกรองลง หรือค้นหาด้วยคำอื่น</p>
                </div>
            @endforelse
        </div>

        <x-pager :page="$page" route-name="households.index" :total-all="$totalAll" />
    </div>

    {{-- ------------------------------------------ หน้าต่างจัดการแบบกลุ่ม --}}
    <div class="modal sm js-modal" id="m-enroll" role="dialog" aria-modal="true">
        <form method="POST" action="{{ route('households.bulk', 'enroll') }}">
            @csrf
            <input type="hidden" name="hcs" data-fill-selection value="">
            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="link" :size="19" :stroke="2.1" /></div>
                <div style="flex:1">
                    <h3>เพิ่ม <span data-selection-count>0</span> ครัวเรือนเข้ากิจกรรม</h3>
                    <p>เลือกกิจกรรมปลายทาง</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close><x-icon name="x" :size="16" :stroke="2.2" /></button>
            </div>
            <div class="md-b">
                <div class="f full">
                    <label>กิจกรรม</label>
                    <select name="pa">
                        @foreach ($activities as $p)
                            <option value="{{ $p['pa'] }}">{{ $p['pa'] }} · {{ mb_substr($p['name'], 0, 70) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="f full" style="margin-top:14px">
                    <label>สถานะเริ่มต้น</label>
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
                <button class="btn pri"><x-icon name="plus" :size="14" :stroke="2.4" /> เพิ่มทั้งหมด</button>
            </div>
        </form>
    </div>

    <div class="modal sm js-modal" id="m-area" role="dialog" aria-modal="true">
        <form method="POST" action="{{ route('households.bulk', 'area') }}">
            @csrf
            <input type="hidden" name="hcs" data-fill-selection value="">
            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="map" :size="19" :stroke="2.1" /></div>
                <div style="flex:1">
                    <h3>แก้พื้นที่ <span data-selection-count>0</span> ครัวเรือน</h3>
                    <p>ใช้แก้ปัญหาอำเภอ/ตำบลไม่สอดคล้องกันเป็นชุด</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close><x-icon name="x" :size="16" :stroke="2.2" /></button>
            </div>
            <div class="md-b">
                <div class="fgrid">
                    <div class="f">
                        <label>อำเภอ</label>
                        <select name="dist">
                            <option value="">— ไม่เปลี่ยน —</option>
                            @foreach ($districts as $d)
                                <option>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f">
                        <label>ตำบล</label>
                        <select name="tam">
                            <option value="">— ไม่เปลี่ยน —</option>
                            @foreach ($tambons as $t)
                                <option value="{{ $t }}">ต.{{ Thai::tamName($t) }} ({{ Thai::tamCode($t) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="inline-w info" style="margin-top:14px">
                    <x-icon name="info" :size="15" :stroke="2.2" />
                    <div>ระบบตรวจพบว่าบางครัวเรือนในหมู่บ้านลูโบ๊ะกาโลบันทึกอำเภอเป็น «เมืองยะลา»
                        แต่ตำบลปุโรง(10) อยู่ในอำเภอ «กรงปินัง» — แก้เป็นชุดได้ที่นี่</div>
                </div>
            </div>
            <div class="md-f">
                <span style="flex:1"></span>
                <button type="button" class="btn out" data-modal-close>ยกเลิก</button>
                <button class="btn pri"><x-icon name="chk" :size="14" :stroke="2.4" /> บันทึกทั้งหมด</button>
            </div>
        </form>
    </div>
@endsection
