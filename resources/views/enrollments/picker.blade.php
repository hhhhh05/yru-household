@php
    use App\Support\Thai;

    $enrollments = app(\App\Repositories\EnrollmentRepository::class);
    $villageOptions = app(\App\Repositories\HouseholdRepository::class)->villages();

    /* [hc => รหัส PA] ครัวเรือนที่ติดกฎ «ห้ามซ้ำกิจกรรมชื่อเดียวกันในปีงบเดียวกัน» */
    $blocked = $blocked ?? [];
    $openCount = 0;

    foreach ($candidates as $h) {
        if (! isset($blocked[$h['hc']])) {
            $openCount++;
        }
    }
@endphp

<form method="POST" action="{{ route('enrollments.store') }}" style="display:flex;flex-direction:column;min-height:0">
    @csrf
    <input type="hidden" name="pa" value="{{ $activity['pa'] }}">

    <div class="md-h">
        <div class="ic-cir brand"><x-icon name="link" :size="19" :stroke="2.1" /></div>
        <div style="flex:1">
            <h3>เพิ่มครัวเรือนเข้ากิจกรรม</h3>
            <p><span class="code" style="color:var(--brand);font-weight:600">{{ $activity['pa'] }}</span>
                {{ mb_substr($activity['name'], 0, 60) }}{{ mb_strlen($activity['name']) > 60 ? '…' : '' }}</p>
        </div>
        <a class="icon-btn" href="{{ $closeUrl }}" aria-label="ปิด"><x-icon name="x" :size="16" :stroke="2.2" /></a>
    </div>

    <div class="tbar" style="padding:12px 20px">
        <div class="fsearch has" style="max-width:none">
            <x-icon name="search" :size="15" :stroke="2.2" />
            <input id="pkq" placeholder="ค้นหา HC, ชื่อ, บ้านเลขที่…" autofocus>
        </div>
        <select class="sel" id="pkv">
            <option value="">ทุกหมู่บ้าน</option>
            @foreach ($villageOptions as $v)
                <option value="{{ $v }}">{{ $v }}</option>
            @endforeach
        </select>
        <select class="sel" id="pkf">
            <option value="ok" selected>เฉพาะที่ยังเพิ่มได้ ({{ $openCount }})</option>
            <option value="">ทั้งหมด ({{ count($candidates) }})</option>
            <option value="np">ยังไม่เข้าร่วมกิจกรรมใด</option>
            <option value="y">มีข้อมูลรายได้</option>
        </select>
    </div>

    <div class="inline-w" style="margin:0 20px 12px">
        <x-icon name="info" :size="15" :stroke="2.2" />
        <div>เพิ่มครัวเรือนเดิมซ้ำได้ แต่ <b>ห้ามซ้ำกิจกรรมชื่อเดียวกันในปีงบ {{ $activity['fy'] }}</b> —
            รายการที่ติดป้าย «เคยเข้าร่วมแล้ว» จึงเลือกไม่ได้</div>
    </div>

    <div class="md-b" style="padding:0">
        <div id="pkList">
            @foreach ($candidates as $h)
                @php
                    $n = count($enrollments->forHousehold($h['hc']));
                    $dupPa = $blocked[$h['hc']] ?? null;
                @endphp
                <label data-hc="{{ $h['hc'] }}"
                       data-search="{{ mb_strtolower($h['hc'].' '.$h['name'].' '.$h['house']) }}"
                       data-vill="{{ $h['vill'] }}"
                       data-enrolled="{{ $n }}"
                       data-income="{{ $h['income'] !== null ? 1 : 0 }}"
                       data-blocked="{{ $dupPa ? 1 : 0 }}"
                       style="display:flex;gap:11px;align-items:center;padding:10px 20px;border-bottom:1px solid var(--line);cursor:{{ $dupPa ? 'not-allowed' : 'pointer' }};opacity:{{ $dupPa ? '.55' : '1' }}">
                    <input type="checkbox" name="hcs[]" value="{{ $h['hc'] }}" @disabled((bool) $dupPa)>
                    <span style="flex:1;min-width:0">
                        <span style="display:block;font-size:13px;font-weight:500">{{ $h['name'] }}</span>
                        <span style="display:block;font-size:11.5px;color:var(--ink-3)">
                            <span class="code">{{ $h['hc'] }}</span> · บ้านเลขที่ {{ $h['house'] }} ·
                            {{ $h['vill'] }} ม.{{ $h['moo'] }}
                        </span>
                    </span>
                    @if ($dupPa)
                        <span class="bg b-warn" data-tip="<b>เคยเข้าร่วมกิจกรรมชื่อนี้แล้ว</b>ปีงบ {{ $activity['fy'] }} · {{ $dupPa }}">
                            เคยเข้าร่วมแล้ว · <span class="code">{{ $dupPa }}</span>
                        </span>
                    @elseif ($h['income'] !== null)
                        <span class="bg b-good">{{ Thai::fmt($h['income']) }}</span>
                    @else
                        <span class="bg out">ไม่มีรายได้</span>
                    @endif
                    @if ($n)
                        <span class="pill">{{ $n }} กิจกรรม</span>
                    @endif
                </label>
            @endforeach

            <div class="empty" id="pkEmpty" hidden>
                <div class="ic"><x-icon name="search" :size="22" :stroke="1.9" /></div>
                <b>ไม่พบครัวเรือนที่ตรงกับเงื่อนไข</b>
                <p>ลองลดตัวกรองลง หรือค้นหาด้วยคำอื่น</p>
            </div>
        </div>
    </div>

    <div class="md-f">
        <span style="font-size:12.5px;color:var(--ink-3)" id="pkCnt">เลือก 0 รายการ</span>
        <span style="flex:1"></span>
        <a class="btn out" href="{{ $closeUrl }}">ยกเลิก</a>
        <button class="btn pri"><x-icon name="plus" :size="14" :stroke="2.4" /> เพิ่มที่เลือก</button>
    </div>
</form>
