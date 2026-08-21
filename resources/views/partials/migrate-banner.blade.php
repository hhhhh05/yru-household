@php
    /* แถบเตือนเมื่อมี migration ค้าง — ผู้ใช้กดปุ่มเดียวจบ ไม่ต้องเปิด Terminal */
    $pendingMigrations = \App\Support\Migrations::pending();
@endphp

@if ($pendingMigrations)
    <div class="inline-w" style="margin-bottom:16px;align-items:flex-start">
        <x-icon name="warn" :size="17" :stroke="2.2" />
        <div style="flex:1">
            <b>ต้องอัปเดตโครงสร้างฐานข้อมูล</b> —
            มี {{ count($pendingMigrations) }} รายการที่ยังไม่ได้รัน
            ข้อมูลเดิมจะถูกย้ายให้อัตโนมัติ และถ้าย้ายไม่ครบระบบจะหยุดเองโดยไม่ลบคอลัมน์เก่า

            <ul style="margin:8px 0 0;padding-left:18px;font-size:11.5px;color:var(--ink-3);line-height:1.7">
                @foreach ($pendingMigrations as $migration)
                    <li><span class="code">{{ $migration }}</span></li>
                @endforeach
            </ul>

            <div style="margin-top:10px">
                <button type="submit" form="migrateBannerForm" class="btn pri sm">
                    <x-icon name="swap" :size="13" :stroke="2.2" /> อัปเดตฐานข้อมูลให้เลย
                </button>
            </div>
        </div>
    </div>

    {{-- แยกฟอร์มออกมา เพราะบางหน้าวางแถบนี้ไว้ในฟอร์มอื่นอยู่แล้ว ซ้อน <form> ไม่ได้ --}}
    <form id="migrateBannerForm" method="POST" action="{{ route('setup.migrate') }}">@csrf</form>
@endif
