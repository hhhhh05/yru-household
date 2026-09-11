@extends('layouts.app')

@php use App\Support\Thai; @endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>คณะ / หน่วยงาน</h2>
            <p>รายชื่อกลาง ใช้เป็นตัวเลือกในหน้าเจ้าหน้าที่รับผิดชอบ ·
                {{ Thai::fmt(count($units)) }} รายการ</p>
        </div>

        @if ($unitsReady)
            <button class="btn pri" data-modal-open="m-unit" data-unit-new>
                <x-icon name="plus" :size="15" :stroke="2.4" /> เพิ่มคณะ / หน่วยงาน
            </button>
        @endif
    </div>

    @unless ($unitsReady)
        {{-- ยังไม่ได้รัน migration — เปิดหน้านี้ได้ แต่ยังบันทึกอะไรไม่ได้ --}}
        <div class="card">
            <div class="card-b">
                <div class="note">
                    <x-icon name="info" :size="14" :stroke="2" />
                    <span>ยังไม่มีตารางคณะ / หน่วยงานในฐานข้อมูล — กดปุ่ม <b>อัปเดตฐานข้อมูล</b>
                        ในแถบด้านบนสุดของหน้าก่อน แล้วหน้านี้จะใช้งานได้ทันที</span>
                </div>
            </div>
        </div>
    @else
        @if (count($missing))
            {{-- ชื่อคณะที่มีในข้อมูลกิจกรรมแล้ว แต่ยังไม่อยู่ในรายการตัวเลือก --}}
            <div class="card" style="margin-bottom:16px">
                <div class="card-h">
                    <span style="color:var(--brand)"><x-icon name="box" :size="18" :stroke="2" /></span>
                    <div style="flex:1">
                        <h3>พบชื่อคณะในข้อมูลกิจกรรมอีก {{ Thai::fmt(count($missing)) }} รายการ</h3>
                        <p>{{ implode(' · ', array_slice($missing, 0, 6)) }}{{ count($missing) > 6 ? ' …' : '' }}</p>
                    </div>
                </div>
                <div class="card-b">
                    <form method="POST" action="{{ route('units.import') }}" class="f-inline">
                        @csrf
                        <button class="btn out" data-tip="<b>เติมเฉพาะชื่อที่ยังขาด</b>ไม่ลบของเดิม กดซ้ำได้">
                            <x-icon name="down" :size="15" :stroke="2.2" /> เติมจากข้อมูลกิจกรรม
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="tw">
                <table>
                    <thead>
                    <tr>
                        <th style="min-width:220px">ชื่อคณะ / หน่วยงาน</th>
                        <th style="min-width:100px">ชื่อย่อ</th>
                        <th style="min-width:200px">หมายเหตุ</th>
                        <th class="r" style="width:110px">เจ้าหน้าที่</th>
                        <th style="width:84px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($units as $unit)
                        @php $used = $staffCounts[$nrm($unit->name)] ?? 0; @endphp
                        <tr>
                            <td><span class="t-name">{{ $unit->name }}</span></td>
                            <td>{{ $unit->short_name ?: '' }}
                                @unless ($unit->short_name)<span class="t-empty"></span>@endunless
                            </td>
                            <td>{{ $unit->note ?: '' }}
                                @unless ($unit->note)<span class="t-empty"></span>@endunless
                            </td>
                            <td class="r">
                                @if ($used)
                                    <span class="num" style="font-weight:600">{{ Thai::fmt($used) }}</span>
                                @else
                                    <span class="t-empty"></span>
                                @endif
                            </td>
                            <td>
                                <div class="rowacts">
                                    <button type="button" class="ia" data-modal-open="m-unit"
                                            data-unit-id="{{ $unit->id }}"
                                            data-unit-name="{{ $unit->name }}"
                                            data-unit-short="{{ $unit->short_name }}"
                                            data-unit-note="{{ $unit->note }}" title="แก้ไข">
                                        <x-icon name="edit" :size="15" :stroke="2" />
                                    </button>

                                    <form method="POST" action="{{ route('units.destroy', $unit->id) }}" class="f-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="ia d" title="ลบ" data-confirm
                                                data-confirm-title="ยืนยันการลบคณะ / หน่วยงาน"
                                                data-confirm-label="ลบออกจากรายการ"
                                                data-confirm-body="กำลังจะลบ <b>{{ e($unit->name) }}</b> ออกจากรายการตัวเลือก@if ($used) — เจ้าหน้าที่ {{ Thai::fmt($used) }} คนที่อยู่คณะนี้ยังคงชื่อคณะเดิมไว้@endif">
                                            <x-icon name="trash" :size="15" :stroke="2" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty">
                                    <div class="ic"><x-icon name="box" :size="22" :stroke="1.9" /></div>
                                    <b>ยังไม่มีรายชื่อคณะ / หน่วยงาน</b>
                                    <p>กดปุ่ม «เพิ่มคณะ / หน่วยงาน» ด้านบน หรือเติมจากข้อมูลกิจกรรมที่มีอยู่</p>
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
    <div class="modal sm js-modal" id="m-unit" role="dialog" aria-modal="true">
        <form method="POST" id="unitForm" action="{{ route('units.store') }}">
            @csrf
            <input type="hidden" name="_method" id="unitMethod" value="">

            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="box" :size="18" :stroke="2.2" /></div>
                <div style="flex:1">
                    <h3 id="unitTitle">เพิ่มคณะ / หน่วยงาน</h3>
                    <p id="unitSub">ใช้เป็นตัวเลือกในหน้าเจ้าหน้าที่</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="ปิด">
                    <x-icon name="x" :size="16" :stroke="2.2" />
                </button>
            </div>

            <div class="md-b">
                <div class="fgrid">
                    <div class="f full">
                        <label>ชื่อคณะ / หน่วยงาน <span class="req">*</span></label>
                        <input name="name" id="unitName" required maxlength="180"
                               placeholder="เช่น คณะวิทยาการจัดการ">
                    </div>
                    <div class="f full">
                        <label>ชื่อย่อ</label>
                        <input name="short_name" id="unitShort" maxlength="60" placeholder="เช่น วจก.">
                    </div>
                    <div class="f full">
                        <label>หมายเหตุ</label>
                        <input name="note" id="unitNote" maxlength="255" placeholder="เว้นว่างได้">
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

    {{-- แม่แบบ URL ตอนแก้ไข — JS แทน __ID__ ด้วยรหัสคณะ --}}
    <script type="application/json" id="unit-update-url">@json(route('units.update', ['id' => '__ID__']))</script>
    <script type="application/json" id="unit-store-url">@json(route('units.store'))</script>
@endsection
