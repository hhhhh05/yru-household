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

        {{-- ครัวเรือนที่ยังไม่เข้าร่วมกิจกรรมใดเลย = ทะเบียนทั้งหมด − ที่เข้าร่วมแล้ว (นับครัวเรือนไม่ซ้ำ)
             ครัวเรือนเดียวเข้าได้หลายกิจกรรม จึงต้องเทียบกับ «ครัวเรือนไม่ซ้ำ» ไม่ใช่จำนวนรายการลงทะเบียน --}}
        @php $notEnrolled = max(0, $total - $enrolledCount); @endphp

        <div class="tile">
            <div class="tl">ครัวเรือนที่ยังไม่เข้าร่วม</div>
            <div class="tv">{{ Thai::fmt($notEnrolled) }}</div>
            <div class="td">
                {{ $total ? round($notEnrolled / $total * 100) : 0 }}% ของทะเบียน
                {{ Thai::fmt($total) }} ครัวเรือน
            </div>
            <div class="tile-ic"><x-icon name="users" :size="16" :stroke="2" /></div>
            <div class="meter" style="margin-top:11px">
                <i style="width:{{ $total ? round($notEnrolled / $total * 100, 1) : 0 }}%"></i>
            </div>
        </div>

        @php
            /* ช่วงปีงบเอาจากข้อมูลจริง ไม่ฝังเลขไว้ในหน้า ไม่งั้นพอเพิ่มปีงบใหม่ข้อความจะผิด */
            $budgetYears = array_keys($budgetByYear);
            sort($budgetYears);
        @endphp

        <div class="tile">
            <div class="tl">งบประมาณรวม {{ count($budgetYears) }} ปีงบ</div>
            {{-- แสดงจำนวนเงินเต็ม ไม่ย่อเป็น «ล.» --}}
            <div class="tv money">{{ Thai::fmt($budget) }}<small> บาท</small></div>
            <div class="td">
                @if ($budgetYears)
                    {{ $budgetYears[0] }}@if (count($budgetYears) > 1)–{{ end($budgetYears) }}@endif ·
                @endif
                เฉลี่ย {{ Thai::fmt(round($budget / max(1, $activityCount))) }} บาท/กิจกรรม
            </div>
            <div class="tile-ic"><x-icon name="box" :size="16" :stroke="2" /></div>
        </div>

        {{-- โครงการหลัก / กิจกรรม — ป้ายย่อยแยกตามปีงบ ใช้ปีงบของ «โครงการ» เป็นแกน
             (กิจกรรมยึดปีงบตามโครงการแม่อยู่แล้ว จึงไม่มีปีที่ขัดกัน) --}}
        <div class="tile">
            <div class="tl">โครงการ · กิจกรรม</div>
            <div class="tv">{{ Thai::fmt($programCount) }}<small> โครงการ</small>
                <span style="opacity:.4;font-weight:400"> / </span>{{ Thai::fmt($activityCount) }}<small> กิจกรรม</small></div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:9px">
                @php
                    /* รวมปีงบจากทั้งสองฝั่ง — ถ้าปีไหนมีกิจกรรมแต่ยังไม่มีโครงการ (หรือกลับกัน)
                       ก็ยังต้องโผล่ในป้าย ไม่งั้นตัวเลขรวมกับป้ายย่อยจะบวกไม่ตรงกัน */
                    $paYearList = array_unique(array_merge(
                        array_keys($programCountByYear),
                        array_keys($activityCountByYear),
                    ));
                    sort($paYearList);
                @endphp

                @forelse ($paYearList as $year)
                    <span class="bg" data-tip="<b>ปีงบ {{ $year }}</b>{{ $programCountByYear[$year] ?? 0 }} โครงการ · {{ $activityCountByYear[$year] ?? 0 }} กิจกรรม">
                        {{ $year }} <b>{{ $programCountByYear[$year] ?? 0 }}</b>/<b>{{ $activityCountByYear[$year] ?? 0 }}</b>
                    </span>
                @empty
                    <span class="t-empty"></span>
                @endforelse
            </div>
            <div class="tile-ic"><x-icon name="box" :size="16" :stroke="2" /></div>
        </div>

        {{-- พื้นที่ครอบคลุมทั้งทะเบียน — นับแบบไม่ซ้ำตามสายเต็ม จังหวัด›อำเภอ›ตำบล›หมู่บ้าน
             (ชื่อหมู่บ้าน/ตำบลซ้ำกันได้ข้ามอำเภอ ถ้านับแค่ชื่อจะได้น้อยกว่าจริง) --}}
        <div class="tile">
            {{-- ระบุขอบเขตให้ชัดในหัวการ์ด — หน้ารายชื่อเข้าร่วมมีการ์ดชื่อเดียวกัน
                 แต่นับเฉพาะครัวเรือนที่ลงทะเบียนแล้ว ตัวเลขสองหน้าจึงไม่เท่ากันเป็นปกติ --}}
            <div class="tl">พื้นที่ครอบคลุม · ทั้งทะเบียน</div>
            <div class="tv">{{ Thai::fmt($areaCounts['vill']) }}<small> หมู่บ้าน</small></div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:9px">
                <span class="bg">จังหวัด <b>{{ Thai::fmt($areaCounts['prov']) }}</b></span>
                <span class="bg">อำเภอ <b>{{ Thai::fmt($areaCounts['dist']) }}</b></span>
                <span class="bg">ตำบล <b>{{ Thai::fmt($areaCounts['tam']) }}</b></span>
            </div>
            <div class="tile-ic"><x-icon name="map" :size="16" :stroke="2" /></div>
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

        {{-- รายได้เฉลี่ยก่อนเข้าร่วม (BL) — เฉลี่ยจากครัวเรือนที่ลงทะเบียนและมีตัวเลขรายได้ --}}
        <div class="tile">
            <div class="tl">รายได้เฉลี่ยก่อนเข้าร่วม</div>
            <div class="tv money">{{ $income['beforeAvg'] !== null ? Thai::fmt($income['beforeAvg']) : '—' }}<small> บาท/ปี</small></div>
            <div class="td">{{ Thai::fmt($income['beforeCount']) }}/{{ Thai::fmt($income['total']) }} รายการมีข้อมูล</div>
            <div class="meter" style="margin-top:9px">
                <i style="width:{{ $income['total'] ? round($income['beforeCount'] / $income['total'] * 100, 1) : 0 }}%"></i>
            </div>
        </div>

        {{-- รายได้เฉลี่ยหลังเข้าร่วม + ส่วนต่าง
             ส่วนต่างคิดจากรายคนที่มีตัวเลขครบทั้งสองฝั่ง ไม่ใช่เอาสองค่าเฉลี่ยมาลบกัน
             เพราะคนละกลุ่มตัวอย่าง (คนที่ยังไม่จบยังไม่มีรายได้หลังเข้าร่วม) --}}
        <div class="tile">
            <div class="tl">รายได้เฉลี่ยหลังเข้าร่วม</div>
            <div class="tv money">{{ $income['afterAvg'] !== null ? Thai::fmt($income['afterAvg']) : '—' }}<small> บาท/ปี</small></div>
            <div class="td">
                @if ($income['diffAvg'] !== null)
                    <span style="color:{{ $income['diffAvg'] > 0 ? 'var(--good-ink)' : ($income['diffAvg'] < 0 ? 'var(--critical-ink)' : 'inherit') }};font-weight:600">
                        @if ($income['diffAvg'] > 0)
                            +{{ Thai::fmt($income['diffAvg']) }}
                        @elseif ($income['diffAvg'] < 0)
                            −{{ Thai::fmt(abs($income['diffAvg'])) }}
                        @else
                            เท่าเดิม
                        @endif
                    </span>
                    เทียบก่อนเข้าร่วม ({{ Thai::fmt($income['pairCount']) }} ราย)
                @else
                    {{ Thai::fmt($income['afterCount']) }}/{{ Thai::fmt($income['total']) }} รายการมีข้อมูล
                @endif
            </div>
            <div class="meter" style="margin-top:9px">
                <i style="width:{{ $income['total'] ? round($income['afterCount'] / $income['total'] * 100, 1) : 0 }}%"></i>
            </div>
        </div>

        {{-- สถานะการดำเนินงานของรายการลงทะเบียนทั้งระบบ --}}
        @php $doneCount = $statusCounts['สำเร็จ'] ?? 0; @endphp

        <div class="tile">
            <div class="tl">สถานะการดำเนินงาน</div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:9px">
                @forelse ($statusCounts as $status => $n)
                    <span class="bg {{ $statusClass[$status] ?? '' }}">{{ $status }} <b>{{ Thai::fmt($n) }}</b></span>
                @empty
                    <span class="t-empty"></span>
                @endforelse
            </div>
            @if ($income['total'])
                <div class="meter" style="margin-top:11px">
                    <i style="width:{{ round($doneCount / $income['total'] * 100, 1) }}%"></i>
                </div>
                <div class="td">สำเร็จแล้ว {{ round($doneCount / $income['total'] * 100) }}%
                    ({{ Thai::fmt($doneCount) }}/{{ Thai::fmt($income['total']) }} รายการ)</div>
            @endif
        </div>
    </div>

    {{-- ------------------------------------------------ สรุปสำหรับผู้บริหาร
         สี่คำถามที่ถูกถามบ่อยที่สุดในที่ประชุม —
         ครอบคลุมครัวเรือนแค่ไหน · งานเดินถึงไหน · เงินอยู่ปีไหน · อยู่จังหวัดใดมากสุด
         ใช้โดนัทเพราะเป็น «ส่วนของทั้งหมด» และมีไม่กี่ชิ้น
         การ์ดตัวเลขด้านบนยังเป็นตัวจริงเหมือนเดิม ส่วนนี้แค่ทำให้เห็นสัดส่วนเร็วขึ้น --}}
    @php
        /* สีสถานะผ่านตัวตรวจ palette ทั้งโหมดสว่างและมืด (ΔE ตาบอดสีต่ำสุด 11.0)
           และทุกชิ้นมีป้ายชื่อกำกับในตารางข้าง ๆ ไม่ได้ใช้สีอย่างเดียวสื่อความหมาย */
        $statusColor = [
            'สำเร็จ' => '#128a12',
            'กำลังดำเนินการ' => '#2a78d6',
            'รอเริ่ม' => '#c97f05',
            'ออกกลางคัน' => '#c83535',
        ];

        $statusSlices = [];

        foreach ($statusCounts as $status => $n) {
            $statusSlices[] = [
                'label' => $status,
                'n' => $n,
                'color' => $statusColor[$status] ?? '#6b7c73',
            ];
        }

        $dropCount = $statusCounts['ออกกลางคัน'] ?? 0;
        $runningCount = $statusCounts['กำลังดำเนินการ'] ?? 0;

        /* ปีงบเป็นลำดับ ไม่ใช่หมวดหมู่ จึงไล่เฉดเขียวเข้มขึ้นตามปี (ปีล่าสุดเข้มสุด)
           ไม่ใช้สีคนละวงศ์ เพราะจะอ่านเหมือนคนละเรื่องกัน */
        $greenRamp = ['#7ec9a8', '#4bb68b', '#1f9a6d', '#0b8253', '#045c39'];
        $budgetYearsSorted = array_keys($budgetByYear);
        sort($budgetYearsSorted);
        $budgetSlices = [];

        foreach ($budgetYearsSorted as $i => $fy) {
            $step = count($budgetYearsSorted) > 1
                ? (int) round($i / (count($budgetYearsSorted) - 1) * (count($greenRamp) - 1))
                : count($greenRamp) - 2;

            $budgetSlices[] = [
                'label' => 'ปีงบ '.$fy,
                'n' => $budgetByYear[$fy],
                'color' => $greenRamp[$step],
            ];
        }

        /* ปีงบที่ใช้งบสูงสุด — ใช้เขียนบรรทัดสรุปใต้วง */
        $topBudgetYear = null;

        foreach ($budgetByYear as $fy => $b) {
            if ($topBudgetYear === null || $b > $budgetByYear[$topBudgetYear]) {
                $topBudgetYear = $fy;
            }
        }

        /* ครัวเรือนตามจังหวัด — สามสีเดียวกับกราฟแท่งด้านล่าง (สีผูกกับสิ่งที่มันแทน)
           เกินสามจังหวัดยุบเป็น «จังหวัดอื่น ๆ» ไม่สร้างสีใหม่เพิ่มเรื่อย ๆ */
        $provRows = $areaLevels['prov'];
        $provPalette = ['#2a78d6', '#eb6834', '#1baf7a'];
        $provSlices = [];
        $provOther = 0;

        foreach ($provRows as $i => $row) {
            if ($i < count($provPalette)) {
                $provSlices[] = ['label' => $row['name'], 'n' => $row['n'], 'color' => $provPalette[$i]];
            } else {
                $provOther += $row['n'];
            }
        }

        if ($provOther) {
            $provSlices[] = ['label' => 'จังหวัดอื่น ๆ', 'n' => $provOther, 'color' => '#8d9c94'];
        }

        /* จังหวัดที่มีครัวเรือนมากที่สุด — ต้องหาเอง เพราะรายการเรียงตามชื่อจังหวัด ไม่ใช่ตามจำนวน */
        $topProv = null;

        foreach ($provRows as $row) {
            if ($topProv === null || $row['n'] > $topProv['n']) {
                $topProv = $row;
            }
        }
    @endphp

    <div class="sec-h">
        <h3>สรุปสำหรับผู้บริหาร</h3>
        <span>สัดส่วนจากข้อมูลจริงในระบบ ณ ตอนนี้ · ชี้ที่วงเพื่อดูตัวเลขรายชิ้น</span>
    </div>

    <div class="dset">
        <div class="card">
            <div class="card-h">
                <div style="flex:1">
                    <h3>ความครอบคลุมครัวเรือน</h3>
                    <p>ทะเบียนทั้งหมด {{ Thai::fmt($total) }} ครัวเรือน</p>
                </div>
                <a class="btn out sm" href="{{ route('households.index') }}">ดูทะเบียน</a>
            </div>
            <div class="card-b">
                <x-donut title="ความครอบคลุมครัวเรือน" unit="ครัวเรือน"
                         :center="$total ? round($enrolledCount / $total * 100).'%' : '—'"
                         center-label="เข้าร่วมแล้ว"
                         :center-sub="Thai::fmt($enrolledCount).' / '.Thai::fmt($total)"
                         :note="$notEnrolled
                             ? 'ยังไม่เข้าร่วมอีก '.Thai::fmt($notEnrolled).' ครัวเรือน · เป้าหมายทะเบียนปีนี้ '.Thai::fmt($target).' ครัวเรือน'
                             : 'ครัวเรือนในทะเบียนเข้าร่วมกิจกรรมครบทุกหลังแล้ว'"
                         :slices="[
                             ['label' => 'เข้าร่วมแล้ว', 'n' => $enrolledCount, 'color' => '#0b8253'],
                             ['label' => 'ยังไม่เข้าร่วม', 'n' => $notEnrolled, 'color' => '#b3c2ba'],
                         ]" />
            </div>
        </div>

        <div class="card">
            <div class="card-h">
                <div style="flex:1">
                    <h3>สถานะการดำเนินงาน</h3>
                    <p>รายการลงทะเบียนทั้งระบบ {{ Thai::fmt($enrollmentCount) }} รายการ</p>
                </div>
                <a class="btn out sm" href="{{ route('enrollments.index') }}">ดูรายชื่อ</a>
            </div>
            <div class="card-b">
                <x-donut title="สถานะการดำเนินงาน" unit="รายการ"
                         :center="$enrollmentCount ? round($doneCount / $enrollmentCount * 100).'%' : '—'"
                         center-label="สำเร็จแล้ว"
                         :center-sub="Thai::fmt($doneCount).' / '.Thai::fmt($enrollmentCount)"
                         :note="'กำลังดำเนินการ '.Thai::fmt($runningCount).' รายการ'
                             .($dropCount ? ' · ออกกลางคัน '.Thai::fmt($dropCount).' รายการ ควรตามสาเหตุ' : ' · ยังไม่มีผู้ออกกลางคัน')"
                         :slices="$statusSlices" />
            </div>
        </div>

        <div class="card">
            <div class="card-h">
                <div style="flex:1">
                    <h3>สัดส่วนงบประมาณตามปีงบ</h3>
                    <p>{{ Thai::fmt($activityCount) }} กิจกรรม ภายใต้ยุทธศาสตร์ที่ 1</p>
                </div>
                <a class="btn out sm" href="{{ route('activities.index') }}">ดูโครงการ</a>
            </div>
            <div class="card-b">
                <x-donut title="สัดส่วนงบประมาณตามปีงบ" unit="บาท"
                         :center="$topBudgetYear ? round($budgetByYear[$topBudgetYear] / max(1, $budget) * 100).'%' : '—'"
                         :center-label="$topBudgetYear ? 'ปีงบ '.$topBudgetYear : 'ยังไม่มีข้อมูล'"
                         center-sub="สัดส่วนงบสูงสุด"
                         :note="$topBudgetYear
                             ? 'ปีงบ '.$topBudgetYear.' ใช้งบมากที่สุด '.Thai::fmt($budgetByYear[$topBudgetYear]).' บาท'
                                 .' ('.round($budgetByYear[$topBudgetYear] / max(1, $budget) * 100).'% ของทั้งหมด)'
                             : 'ยังไม่มีข้อมูลงบประมาณ'"
                         :slices="$budgetSlices" />
            </div>
        </div>

        <div class="card">
            <div class="card-h">
                <div style="flex:1">
                    <h3>ครัวเรือนตามจังหวัด</h3>
                    <p>ครอบคลุม {{ Thai::fmt($areaCounts['prov']) }} จังหวัด ·
                        {{ Thai::fmt($areaCounts['tam']) }} ตำบล</p>
                </div>
            </div>
            <div class="card-b">
                <x-donut title="ครัวเรือนตามจังหวัด" unit="ครัวเรือน"
                         :center="Thai::fmt($total)" center-label="ครัวเรือน"
                         :center-sub="Thai::fmt($areaCounts['prov']).' จังหวัด'"
                         :note="$topProv
                             ? $topProv['name'].' มากที่สุด '.Thai::fmt($topProv['n']).' ครัวเรือน'
                                 .' ('.round($topProv['n'] / max(1, $total) * 100).'% ของทะเบียน)'
                             : 'ยังไม่มีข้อมูลพื้นที่'"
                         :slices="$provSlices" />
            </div>
        </div>
    </div>

    <div style="margin-bottom:16px">
        {{-- ------------------------------------------- ครัวเรือนตามชั้นพื้นที่
             แยกสามชั้น จังหวัด → อำเภอ → ตำบล (ไม่ลงถึงหมู่บ้าน)
             แต่ละชั้นสเกลแท่งด้วยค่าสูงสุดของชั้นตัวเอง ไม่ใช่ค่าสูงสุดรวม
             ไม่งั้นชั้นล่างที่ตัวเลขน้อยกว่าจะกลายเป็นแท่งจิ๋วอ่านไม่ออก --}}
        <div class="card">
            <div class="card-h">
                <div style="flex:1">
                    <h3>ครัวเรือนตามพื้นที่</h3>
                    <p>จำนวนครัวเรือนในทะเบียน แยกตามจังหวัด · อำเภอ · ตำบล</p>
                </div>
                <a class="btn out sm" href="{{ route('households.index') }}">ดูทะเบียน</a>
            </div>
            <div class="card-b">
                @foreach ([['prov', 'จังหวัด'], ['dist', 'อำเภอ'], ['tam', 'ตำบล']] as [$levelKey, $levelLabel])
                    @php
                        $levelRows = $areaLevels[$levelKey];
                        $levelMax = max(array_column($levelRows, 'n') ?: [1]);
                    @endphp

                    <div style="margin-bottom:{{ $loop->last ? '0' : '18px' }}">
                        {{-- จุดสีหน้าหัวข้อ = สีของแท่งในชั้นนี้ ทำหน้าที่แทนกล่อง legend --}}
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:9px">
                            <span class="vdot lv-{{ $levelKey }}"></span>
                            <b style="font-size:12.5px;font-weight:700;letter-spacing:.03em">{{ $levelLabel }}</b>
                            <span style="font-size:12px;color:var(--ink-3)">{{ Thai::fmt(count($levelRows)) }} แห่ง</span>
                        </div>

                        <div class="vchart lv-{{ $levelKey }}">
                            @foreach ($levelRows as $row)
                                <div class="vb">
                                    <div class="vnum">{{ Thai::fmt($row['n']) }}</div>
                                    <div class="vtrack">
                                        <div class="vfill" style="height:{{ round($row['n'] / $levelMax * 100, 1) }}%"
                                             data-tip="<b>{{ $row['name'] }}{{ $row['sub'] ? ' · '.$row['sub'] : '' }}</b>{{ $row['n'] }} ครัวเรือน · {{ round($row['n'] / max(1, $total) * 100) }}% ของทะเบียน"></div>
                                    </div>
                                    <div class="vlbl" title="{{ $row['name'] }}{{ $row['sub'] ? ' · '.$row['sub'] : '' }}">
                                        {{ $row['name'] }}
                                        @if ($row['sub'])
                                            <span class="vsub">{{ $row['sub'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- การ์ด «รายการที่ควรตรวจสอบก่อนใช้ข้อมูล» ถูกซ่อนตามที่ผู้ใช้ขอ
         ข้อมูลยังคำนวณอยู่และดูได้เต็ม ๆ ที่เมนู «ตรวจสอบคุณภาพข้อมูล» --}}
@endsection
