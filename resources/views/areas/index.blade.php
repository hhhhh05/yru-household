@extends('layouts.app')

@php use App\Support\Thai; @endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>ข้อมูลพื้นที่เป้าหมาย</h2>
            <p>ชีต «ข้อมูลจังหวัด» · {{ Thai::fmt($total) }} แถว · {{ $provinceCount }} จังหวัด ·
                {{ $tambonCount }} ตำบล</p>
        </div>

        {{-- กดซ้ำได้เสมอ — AreaSeeder ใช้ firstOrCreate จึงเติมเฉพาะที่ยังขาด ไม่ลบของเดิม --}}
        <form method="POST" action="{{ route('setup.seed', 'areas') }}" class="f-inline">
            @csrf
            <button class="btn out" data-tip="<b>เติมข้อมูลพื้นที่ที่ยังขาด</b>ไม่ลบของเดิม กดซ้ำได้">
                <x-icon name="map" :size="15" :stroke="2.2" /> เติมข้อมูลพื้นที่
            </button>
        </form>
    </div>

    @if ($total === 0)
        {{-- ฐานข้อมูลยังไม่มีข้อมูลพื้นที่ — กดปุ่มได้เลย ไม่ต้องใช้ Terminal --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-h">
                <span style="color:var(--brand)"><x-icon name="map" :size="18" :stroke="2" /></span>
                <div style="flex:1">
                    <h3>ยังไม่มีข้อมูลพื้นที่ในฐานข้อมูล</h3>
                    <p>ต้องมีจังหวัด/อำเภอ/ตำบลก่อน จึงจะบันทึกครัวเรือนได้ (ใช้ออกรหัส HC)</p>
                </div>
            </div>
            <div class="card-b" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <form method="POST" action="{{ route('setup.seed', 'areas') }}" class="f-inline">
                    @csrf
                    <button class="btn pri">
                        <x-icon name="map" :size="15" :stroke="2.2" /> ใส่ข้อมูลพื้นที่ (3 จังหวัด · 34 ตำบล)
                    </button>
                </form>

                <form method="POST" action="{{ route('setup.seed', 'demo') }}" class="f-inline">
                    @csrf
                    <button type="button" class="btn out" data-confirm data-confirm-kind="crit"
                            data-confirm-title="ใส่ข้อมูลตัวอย่างทั้งชุด"
                            data-confirm-label="ล้างแล้วใส่ข้อมูลตัวอย่าง"
                            data-confirm-body="จะ <b>ล้างข้อมูลทุกตาราง</b> แล้วใส่ชุดตัวอย่าง 104 ครัวเรือน · 39 กิจกรรม · 104 รายการลงทะเบียน — ถ้ากรอกข้อมูลเองไว้แล้วจะหายทั้งหมด">
                        <x-icon name="down" :size="15" :stroke="2.2" /> ใส่ข้อมูลตัวอย่างทั้งชุด (ล้างข้อมูลเดิม)
                    </button>
                </form>

                <span class="note" style="flex:1;min-width:220px">
                    <x-icon name="info" :size="14" :stroke="2" />
                    <span>เทียบเท่าคำสั่ง <span class="code">php artisan db:seed --class=AreaSeeder</span></span>
                </span>
            </div>
        </div>
    @endif

    @if (count($issues))
        <div class="card" style="margin-bottom:16px">
            <div class="card-h">
                <span style="color:var(--serious-ink)"><x-icon name="warn" :size="18" :stroke="2" /></span>
                <div style="flex:1">
                    <h3>พบ {{ count($issues) }} ประเด็นในชุดข้อมูลพื้นที่</h3>
                    <p>รหัสพื้นที่ถูกใช้สร้างรหัส HC จึงควรแก้ก่อนเพิ่มครัวเรือนใหม่</p>
                </div>
            </div>
            <div>
                @foreach ($issues as $issue)
                    <x-issue :issue="$issue" />
                @endforeach
            </div>
        </div>
    @endif

    <div class="card">
        <div class="tw">
            <table>
                <thead>
                <tr>
                    <th style="width:110px">จังหวัด</th>
                    <th style="width:170px">อำเภอ</th>
                    <th>ตำบล</th>
                    <th class="c" style="width:100px">รหัสพื้นที่</th>
                    <th class="r" style="width:130px">ครัวเรือนในระบบ</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($grouped as $province => $group)
                    @php
                        $provinceHouseholds = 0;
                        foreach ($group as $a) {
                            $provinceHouseholds += $householdsByTambon[$nrm($a['tam'].'('.$a['code'].')')] ?? 0;
                        }
                    @endphp
                    <tr style="background:var(--surface-2)">
                        <td colspan="5" style="padding:8px 12px">
                            <div style="display:flex;align-items:center;gap:10px">
                                <b style="font-size:12.5px;font-weight:700">จังหวัด{{ $province }}</b>
                                <span class="pill">{{ count($group) }} ตำบล</span>
                                <span class="pill">{{ $provinceHouseholds }} ครัวเรือน</span>
                            </div>
                        </td>
                    </tr>

                    @foreach ($group as $a)
                        @php
                            $n = $householdsByTambon[$nrm($a['tam'].'('.$a['code'].')')] ?? 0;
                            $dup = ($dupKeys[$a['prov'].$a['dist'].$a['tam'].$a['code']] ?? 0) > 1;
                        @endphp
                        <tr>
                            <td>{{ $a['prov'] }}</td>
                            <td>
                                อ.{{ $a['dist'] }}
                                @if ($a['dist'] === 'กรงปีนัง')
                                    <span class="bg b-ser" data-tip="ชีตครัวเรือนสะกด «กรงปินัง»">
                                        <x-icon name="warn" :size="11" :stroke="2.4" /> สะกดต่างกัน
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="t-name">ต.{{ $a['tam'] }}</span>
                                @if ($dup)
                                    <span class="bg b-ser"><x-icon name="warn" :size="11" :stroke="2.4" /> ซ้ำ</span>
                                @endif
                            </td>
                            <td class="c">
                                <span class="pill"
                                      @if ($a['code'] === 99) style="background:var(--warning-bg);color:var(--warning-ink)" @endif>{{ $a['code'] }}</span>
                            </td>
                            <td class="r">
                                @if ($n)
                                    <span class="num" style="font-weight:600">{{ Thai::fmt($n) }}</span>
                                @else
                                    <span class="t-empty"></span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
