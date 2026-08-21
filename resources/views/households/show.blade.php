@php
    use App\Support\Thai;

    $h = $household;
    $initials = mb_substr(preg_replace('/^(นางสาว|นาย|นาง|เด็กชาย|เด็กหญิง)/u', '', $h['name']), 0, 2);
@endphp

<div class="dw-h">
    <div class="av" style="width:40px;height:40px;border-radius:12px;font-size:14px">{{ trim($initials) }}</div>
    <div style="flex:1">
        <h3>{{ $h['name'] }}</h3>
        <p><span class="code">{{ $h['hc'] }}</span> · {{ $h['vill'] }} ม.{{ $h['moo'] }}</p>
    </div>
    <a class="icon-btn" href="{{ $closeUrl }}" aria-label="ปิด"><x-icon name="x" :size="16" :stroke="2.2" /></a>
</div>

<div class="dw-b">
    @if (count($householdIssues))
        <div class="inline-w" style="margin-bottom:16px">
            <x-icon name="warn" :size="16" :stroke="2.2" />
            <div>
                <b>พบ {{ count($householdIssues) }} ประเด็นคุณภาพข้อมูล</b>
                <ul style="margin:5px 0 0;padding-left:17px">
                    @foreach ($householdIssues as $i)
                        <li>{{ $i['title'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @else
        <div class="inline-w info" style="margin-bottom:16px">
            <x-icon name="chk" :size="16" :stroke="2.4" />
            <div>ข้อมูลครัวเรือนนี้ผ่านการตรวจสอบทั้งหมด</div>
        </div>
    @endif

    <div class="f-sec">
        <div class="f-sec-h"><div class="n">1</div><h4>ข้อมูลทะเบียน</h4><div class="ln"></div></div>
        <dl class="kv">
            <dt>รหัส HC</dt><dd><span class="code">{{ $h['hc'] }}</span></dd>
            <dt>ชื่อ - สกุล</dt><dd>{{ $h['name'] }}</dd>
            <dt>บ้านเลขที่</dt><dd>{{ $h['house'] }}</dd>
            <dt>บ้าน / ชุมชน</dt><dd>{{ $h['vill'] }} <span class="pill">ม.{{ $h['moo'] }}</span></dd>
            <dt>ตำบล</dt>
            <dd>
                @if ($h['tam'])
                    ต.{{ Thai::tamName($h['tam']) }} <span class="pill">รหัส {{ Thai::tamCode($h['tam']) }}</span>
                @else
                    <span class="bg b-crit">ไม่ระบุ</span>
                @endif
            </dd>
            <dt>อำเภอ / จังหวัด</dt><dd>อ.{{ $h['dist'] }} จ.{{ $h['prov'] }}</dd>
            <dt>พิกัดที่ตั้ง</dt>
            <dd>
                @if (($h['lat'] ?? null) !== null && ($h['lng'] ?? null) !== null)
                    <span class="num">{{ $h['lat'] }}, {{ $h['lng'] }}</span>
                    <a href="https://www.google.com/maps?q={{ $h['lat'] }},{{ $h['lng'] }}" target="_blank"
                       rel="noopener" style="font-size:11.5px">เปิดแผนที่</a>
                @else
                    <span class="t-empty"></span>
                @endif
            </dd>
            <dt>ติดต่อ</dt>
            <dd>
                @if ($h['phone'])
                    <span class="num">{{ $h['phone'] }}</span>
                @else
                    <span class="t-empty"></span>
                @endif
            </dd>
            <dt>รายได้ BL</dt>
            <dd>
                @if ($h['income'] !== null)
                    <b class="num">{{ Thai::fmt($h['income']) }}</b> บาท/ปี
                    <span style="color:var(--ink-3)">(≈ {{ Thai::fmt(round($h['income'] / 12)) }} บาท/เดือน)</span>
                @else
                    <span class="t-empty"></span>
                @endif
            </dd>
            <dt>หมายเหตุ</dt>
            <dd>
                @if (trim((string) ($h['note'] ?? '')) !== '')
                    {{-- คงการขึ้นบรรทัดใหม่ตามที่ผู้กรอกพิมพ์ไว้ --}}
                    <div style="white-space:pre-wrap;line-height:1.55">{{ $h['note'] }}</div>
                @else
                    <span class="t-empty"></span>
                @endif
            </dd>
        </dl>
    </div>

    <div class="f-sec">
        <div class="f-sec-h">
            <div class="n">2</div>
            <h4>กิจกรรมที่เข้าร่วม ({{ count($householdEnrollments) }})</h4>
            <div class="ln"></div>
        </div>

        @forelse ($householdEnrollments as $e)
            <div style="border:1px solid var(--line);border-radius:var(--r-m);padding:11px 12px;margin-bottom:8px">
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px">
                    <span class="code" style="color:var(--brand);font-weight:600;font-size:12px">{{ $e['pa'] }}</span>
                    <span class="bg {{ \App\Repositories\EnrollmentRepository::STATUS_CLASS[$e['status']] ?? 'b-brand' }}">{{ $e['status'] }}</span>
                    <span style="margin-left:auto;font-size:11.5px;color:var(--ink-3)" class="num">{{ Thai::date($e['joined']) }}</span>
                </div>
                <div style="font-size:12.8px;line-height:1.45">{{ $e['p']['name'] ?? '' }}</div>
            </div>
        @empty
            <p style="font-size:12.5px;color:var(--ink-3)">ยังไม่เข้าร่วมกิจกรรมใด — กด «เพิ่มเข้ากิจกรรม» เพื่อจับคู่</p>
        @endforelse
    </div>
</div>

<div class="dw-f">
    <a class="btn out" href="{{ route('enrollments.index', ['q' => $h['hc'], 'pa' => '']) }}">
        <x-icon name="link" :size="14" :stroke="2.2" /> ดูรายการลงทะเบียน
    </a>
    <span style="flex:1"></span>
    <a class="btn pri" href="{{ route('households.edit', $h['hc']) }}">
        <x-icon name="edit" :size="14" :stroke="2.2" /> แก้ไขข้อมูล
    </a>
</div>
