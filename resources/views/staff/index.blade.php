@extends('layouts.app')

@php use App\Support\Thai; @endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>เจ้าหน้าที่รับผิดชอบ</h2>
            <p>รายชื่อกลางของหน่วยงาน · ใช้อ้างอิงในเอกสารและการติดต่อ ·
                {{ Thai::fmt(count($staff)) }} คน</p>
        </div>

        @if ($staffReady)
            <button class="btn pri" data-modal-open="m-staff" data-staff-new>
                <x-icon name="plus" :size="15" :stroke="2.4" /> เพิ่มเจ้าหน้าที่
            </button>
        @endif
    </div>

    @unless ($staffReady)
        {{-- ยังไม่ได้รัน migration — เปิดหน้านี้ได้ แต่ยังบันทึกอะไรไม่ได้ --}}
        <div class="card">
            <div class="card-b">
                <div class="note">
                    <x-icon name="info" :size="14" :stroke="2" />
                    <span>ยังไม่มีตารางเจ้าหน้าที่ในฐานข้อมูล — กดปุ่ม <b>อัปเดตฐานข้อมูล</b>
                        ในแถบด้านบนสุดของหน้าก่อน แล้วหน้านี้จะใช้งานได้ทันที</span>
                </div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="tw">
                <table>
                    <thead>
                    <tr>
                        <th style="min-width:180px">ชื่อ - สกุล</th>
                        <th style="min-width:140px">ตำแหน่ง</th>
                        <th style="min-width:180px">คณะ / หน่วยงาน</th>
                        <th style="min-width:120px">เบอร์โทร</th>
                        <th style="min-width:180px">อีเมล</th>
                        <th style="width:84px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($staff as $person)
                        <tr>
                            <td><span class="t-name">{{ $person->full_name }}</span></td>
                            <td>{{ $person->position ?: '' }}
                                @unless ($person->position)<span class="t-empty"></span>@endunless
                            </td>
                            <td>{{ $person->unit ?: '' }}
                                @unless ($person->unit)<span class="t-empty"></span>@endunless
                            </td>
                            <td>
                                @if ($person->phone)
                                    <span class="num">{{ $person->phone }}</span>
                                @else
                                    <span class="t-empty"></span>
                                @endif
                            </td>
                            <td>
                                @if ($person->email)
                                    <a href="mailto:{{ $person->email }}">{{ $person->email }}</a>
                                @else
                                    <span class="t-empty"></span>
                                @endif
                            </td>
                            <td>
                                <div class="rowacts">
                                    <button type="button" class="ia" data-modal-open="m-staff"
                                            data-staff-id="{{ $person->id }}"
                                            data-staff-name="{{ $person->full_name }}"
                                            data-staff-position="{{ $person->position }}"
                                            data-staff-unit="{{ $person->unit }}"
                                            data-staff-phone="{{ $person->phone }}"
                                            data-staff-email="{{ $person->email }}" title="แก้ไข">
                                        <x-icon name="edit" :size="15" :stroke="2" />
                                    </button>

                                    <form method="POST" action="{{ route('staff.destroy', $person->id) }}" class="f-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="ia d" title="ลบ" data-confirm
                                                data-confirm-title="ยืนยันการลบเจ้าหน้าที่"
                                                data-confirm-label="ลบรายชื่อ"
                                                data-confirm-body="กำลังจะลบ <b>{{ e($person->full_name) }}</b> ออกจากรายชื่อ">
                                            <x-icon name="trash" :size="15" :stroke="2" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty">
                                    <div class="ic"><x-icon name="users" :size="22" :stroke="1.9" /></div>
                                    <b>ยังไม่มีรายชื่อเจ้าหน้าที่</b>
                                    <p>กดปุ่ม «เพิ่มเจ้าหน้าที่» ด้านบนเพื่อเริ่มบันทึก</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endunless

    {{-- หน้าต่างเดียวใช้ทั้งเพิ่มและแก้ไข — JS สลับ action กับหัวเรื่องให้ --}}
    <div class="modal sm js-modal" id="m-staff" role="dialog" aria-modal="true">
        <form method="POST" id="staffForm" action="{{ route('staff.store') }}">
            @csrf
            {{-- ตอนแก้ไข JS จะเปลี่ยนค่าเป็น PATCH — ตอนเพิ่มปล่อยว่างไว้เพื่อให้เป็น POST --}}
            <input type="hidden" name="_method" id="staffMethod" value="">

            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="users" :size="18" :stroke="2.2" /></div>
                <div style="flex:1">
                    <h3 id="staffTitle">เพิ่มเจ้าหน้าที่</h3>
                    <p id="staffSub">รายชื่อกลาง ไม่ผูกกับพื้นที่</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="ปิด">
                    <x-icon name="x" :size="16" :stroke="2.2" />
                </button>
            </div>

            <div class="md-b">
                <div class="fgrid">
                    <div class="f full">
                        <label>ชื่อ - สกุล <span class="req">*</span></label>
                        <input name="full_name" id="staffName" required maxlength="180"
                               placeholder="เช่น นางสาวสมหญิง ใจดี">
                    </div>
                    <div class="f full">
                        <label>ตำแหน่ง</label>
                        <input name="position" id="staffPosition" maxlength="120" placeholder="เช่น นักวิชาการศึกษา">
                    </div>
                    <div class="f full">
                        <label>คณะ / หน่วยงาน</label>
                        {{-- ตัวเลือกมาจากหน้า «คณะ / หน่วยงาน»
                             ถ้าคนนี้มีชื่อคณะที่ไม่อยู่ในรายการ (ข้อมูลเก่า) JS จะเติมตัวเลือกให้ชั่วคราว
                             จะได้ไม่โดนล้างทิ้งเงียบ ๆ ตอนกดบันทึก --}}
                        <select name="unit" id="staffUnit">
                            <option value="">— ไม่ระบุ —</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->name }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @unless (count($units))
                            <span class="hint">ยังไม่มีรายชื่อคณะ — เพิ่มได้ที่เมนู
                                <a href="{{ route('units.index') }}">คณะ / หน่วยงาน</a></span>
                        @endunless
                    </div>
                    <div class="f full">
                        <label>เบอร์โทร</label>
                        <input name="phone" id="staffPhone" maxlength="30" placeholder="0812345678">
                    </div>
                    <div class="f full">
                        <label>อีเมล</label>
                        <input name="email" id="staffEmail" type="email" maxlength="180" placeholder="name@yru.ac.th">
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

    {{-- แม่แบบ URL ตอนแก้ไข — JS แทน __ID__ ด้วยรหัสเจ้าหน้าที่ --}}
    <script type="application/json" id="staff-update-url">@json(route('staff.update', ['id' => '__ID__']))</script>
    <script type="application/json" id="staff-store-url">@json(route('staff.store'))</script>
@endsection
