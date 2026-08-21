@php
    use App\Support\Nav;

    /* ปิดลิ้นชัก/หน้าต่าง = กลับไปหน้าเดิมพร้อมตัวกรองเดิม */
    $closeUrl = route(Nav::find($navKey ?? 'home')['route'], qs([], ['hcs']));

    /* รูปแบบแผงฟอร์ม: 'sheet' = ลอยขึ้นกลางจอ (ค่าเริ่มต้น) · 'side' = ลิ้นชักเลื่อนจากขวา
       ส่งค่ามาจาก Controller ได้ เช่น view(..., ['drawerStyle' => 'side']) */
    $drawerStyle = ($drawerStyle ?? 'sheet') === 'side' ? '' : 'sheet';
@endphp

<div class="veil {{ isset($drawer) || isset($modal) ? 'on' : '' }}" id="veil" data-close-url="{{ $closeUrl }}"></div>

<div class="drawer {{ $drawerStyle }} {{ isset($drawer) ? 'on' : '' }}" id="drawer" role="dialog" aria-modal="true"
     aria-label="แบบฟอร์ม">
    @isset($drawer)
        @include($drawer, ['closeUrl' => $closeUrl])
    @endisset
</div>

<div class="modal {{ isset($modal) ? 'on' : '' }}" id="modal" role="dialog" aria-modal="true">
    @isset($modal)
        @include($modal, ['closeUrl' => $closeUrl])
    @endisset
</div>

{{-- หน้าต่างยืนยัน (สร้างเนื้อหาด้วย JS) --}}
<div class="modal sm js-modal" id="modal-confirm" role="dialog" aria-modal="true"></div>

<div class="toasts" id="toasts" aria-live="polite"></div>
<div class="tt" id="tt"></div>

{{-- ข้อความแจ้งเตือนจากเซิร์ฟเวอร์ --}}
@php
    $toasts = collect(session('toasts', []))->all();

    foreach ($errors->all() as $message) {
        $toasts[] = ['title' => 'ยังกรอกข้อมูลไม่ครบ', 'msg' => $message, 'kind' => 'err'];
    }
@endphp
<script type="application/json" id="flash-toasts">@json(array_slice($toasts, 0, 4))</script>
