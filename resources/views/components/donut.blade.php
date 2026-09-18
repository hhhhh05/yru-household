@props([
    'title' => '',
    'slices' => [],
    'center' => '',
    'centerLabel' => '',
    'centerSub' => '',
    'unit' => 'รายการ',
    'note' => '',
])

@php
    use App\Support\Thai;

    /**
     * แผนภูมิวงกลมแบบโดนัท — ใช้สรุปสัดส่วนให้ผู้บริหารอ่านเร็ว
     *
     * $slices = [['label' => ..., 'n' => ..., 'color' => '#rrggbb'], ...]
     *
     * วาดด้วย SVG เส้นเดียวต่อชิ้น (เส้นรอบวง = 100 พอดี) ค่าเปอร์เซ็นต์จึงใส่ลง
     * stroke-dasharray ได้ตรง ๆ ไม่ต้องคำนวณมุม
     *
     * เว้นช่องว่างระหว่างชิ้น 1.2 หน่วย (~2px) เพื่อให้เห็นขอบแยกกันแม้สองสีใกล้กัน
     * และทุกชิ้นมีป้ายชื่อ + ตัวเลขในตารางข้าง ๆ เสมอ — ไม่ใช้สีอย่างเดียวบอกความหมาย
     */
    $slices = array_values(array_filter($slices, fn ($s) => ($s['n'] ?? 0) > 0));
    $sum = array_sum(array_column($slices, 'n'));
    $gap = count($slices) > 1 ? 1.2 : 0;
    $cursor = 0;
@endphp

<div class="donut">
    <div class="ring">
        <svg viewBox="0 0 42 42" role="img" aria-label="{{ $title }}">
            {{-- วงพื้นหลัง — กันไม่ให้วงแหว่งตอนยังไม่มีข้อมูล --}}
            <circle class="dbg" cx="21" cy="21" r="15.915" fill="none" stroke-width="5"></circle>

            @foreach ($slices as $slice)
                @php
                    $pct = $sum ? $slice['n'] / $sum * 100 : 0;
                    $len = max(0.6, $pct - $gap);          // ชิ้นเล็กมากก็ยังต้องมองเห็น
                    $offset = 25 - $cursor;                 // 25 = เริ่มที่ตำแหน่ง 12 นาฬิกา
                    $cursor += $pct;
                @endphp
                <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="5" stroke-linecap="butt"
                        stroke="{{ $slice['color'] }}"
                        stroke-dasharray="{{ round($len, 2) }} {{ round(100 - $len, 2) }}"
                        stroke-dashoffset="{{ round($offset, 2) }}"
                        data-tip="<b>{{ e($slice['label']) }}</b>{{ Thai::fmt($slice['n']) }} {{ $unit }} · {{ round($pct) }}%"></circle>
            @endforeach
        </svg>

        <div class="ctr">
            <b>{{ $center }}</b>
            <span>{{ $centerLabel }}</span>
            @if ($centerSub)
                <span class="sub">{{ $centerSub }}</span>
            @endif
        </div>
    </div>

    <div class="dside">
        <div class="dleg">
            @forelse ($slices as $slice)
                <div class="r">
                    <i style="background:{{ $slice['color'] }}"></i>
                    <span class="nm" title="{{ $slice['label'] }}">{{ $slice['label'] }}</span>
                    <b>{{ Thai::fmt($slice['n']) }}</b>
                    <em>{{ $sum ? round($slice['n'] / $sum * 100) : 0 }}%</em>
                </div>
            @empty
                <div class="r"><span class="nm t-empty">ยังไม่มีข้อมูล</span></div>
            @endforelse
        </div>

        @if ($note)
            {{-- บรรทัด «แล้วไง» — สิ่งที่ผู้บริหารควรอ่านออกจากวงนี้ ไม่ใช่แค่ตัวเลขซ้ำ --}}
            <div class="dnote">{{ $note }}</div>
        @endif
    </div>
</div>
