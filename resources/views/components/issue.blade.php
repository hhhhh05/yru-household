@props(['issue'])

@php
    use App\Repositories\DataQualityAnalyzer;

    [$cls, $label] = DataQualityAnalyzer::SEVERITY[$issue['sev']];
    $hcs = implode(',', $issue['hcs']);
@endphp

<div class="issue">
    <span style="color:var(--{{ $issue['sev'] }}-ink);margin-top:2px">
        <x-icon :name="$issue['sev'] === 'warning' ? 'info' : 'warn'" :size="16" :stroke="2.1" />
    </span>

    <div class="ib">
        <div class="it">
            {{ $issue['title'] }}
            <span class="bg {{ $cls }}">{{ $label }}</span>
            @if (count($issue['hcs']) > 1)
                <span class="pill">{{ count($issue['hcs']) }} รายการ</span>
            @endif
        </div>
        <div class="id">{!! $issue['desc'] !!}</div>
    </div>

    <div class="ia2">
        @if ($issue['fix'] === 'merge')
            <form method="POST" action="{{ route('households.bulk', 'merge') }}" class="f-inline">
                @csrf
                <input type="hidden" name="hcs" value="{{ $hcs }}">
                <button type="button" class="btn out xs" data-confirm data-confirm-kind="brand"
                        data-confirm-title="รวมรายการที่ซ้ำกัน"
                        data-confirm-label="รวมรายการ"
                        data-confirm-body="พบ {{ count($issue['hcs']) }} รายการที่เป็นครัวเรือนเดียวกัน — ระบบจะย้ายรายการลงทะเบียนกิจกรรมทั้งหมดมาไว้ที่รายการหลัก ({{ $issue['hcs'][0] ?? '' }})">
                    <x-icon name="merge" :size="12" :stroke="2.2" /> รวมรายการ
                </button>
            </form>
        @endif

        @if ($issue['fix'] === 'edit')
            <a class="btn out xs" href="{{ route('households.edit', $issue['hcs'][0]) }}">
                <x-icon name="edit" :size="12" :stroke="2.2" /> แก้ไข
            </a>
        @endif

        @if ($issue['fix'] === 'bulk')
            <a class="btn out xs" href="{{ route('households.index', ['hcs' => $hcs]) }}">
                <x-icon name="filter" :size="12" :stroke="2.2" /> ดูรายการ
            </a>
        @endif

        @if ($issue['fix'] === 'fixdist')
            <a class="btn out xs" href="{{ route('households.index', ['hcs' => $hcs]) }}">
                <x-icon name="filter" :size="12" :stroke="2.2" /> ดูรายการ
            </a>
            <form method="POST" action="{{ route('households.bulk', 'fix-district') }}" class="f-inline">
                @csrf
                <input type="hidden" name="hcs" value="{{ $hcs }}">
                <input type="hidden" name="to" value="{{ $issue['to'] ?? '' }}">
                <button type="button" class="btn pri xs" data-confirm data-confirm-kind="brand"
                        data-confirm-title="แก้อำเภอเป็นชุด"
                        data-confirm-label="แก้ {{ count($issue['hcs']) }} รายการ"
                        data-confirm-body="ระบบจะเปลี่ยนอำเภอของ {{ count($issue['hcs']) }} ครัวเรือน ให้เป็น «{{ $issue['to'] ?? '' }}» ตามที่ระบุไว้ในชีตข้อมูลจังหวัด">
                    <x-icon name="chk" :size="12" :stroke="2.6" /> แก้เป็นชุด
                </button>
            </form>
        @endif

        @if ($issue['fix'] === 'area')
            <a class="btn out xs" href="{{ route('areas.index') }}">
                <x-icon name="map" :size="12" :stroke="2.2" /> เปิดข้อมูลพื้นที่
            </a>
        @endif
    </div>
</div>
