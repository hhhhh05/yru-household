@extends('layouts.app')

@php use App\Models\Program; use App\Support\Thai; @endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>โครงการหลัก</h2>
            <p>ร่มของกิจกรรม · หนึ่งโครงการมีได้หลายกิจกรรม · ปีงบเป็นของโครงการ
                กิจกรรมยึดปีงบตามโครงการแม่</p>
        </div>

        <button class="btn pri" data-modal-open="m-program" data-program-new>
            <x-icon name="plus" :size="15" :stroke="2.4" /> เพิ่มโครงการหลัก
        </button>
    </div>

    @if ($errors->any())
        <div class="card" style="margin-bottom:14px">
            <div class="card-b">
                <div class="inline-w">
                    <x-icon name="warn" :size="16" :stroke="2.2" />
                    <div>
                        <b>บันทึกไม่สำเร็จ</b>
                        <ul style="margin:6px 0 0 16px;font-size:12.5px;line-height:1.6">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-bottom:14px">
        <div class="tile">
            <div class="tl">โครงการหลัก</div>
            <div class="tv">{{ Thai::fmt(count($programs)) }}<small> โครงการ</small></div>
            <div class="tile-ic"><x-icon name="box" :size="16" :stroke="2" /></div>
        </div>

        <div class="tile">
            <div class="tl">กิจกรรมภายใต้โครงการ</div>
            <div class="tv">{{ Thai::fmt(array_sum($activityCount)) }}<small> กิจกรรม</small></div>
            <div class="tile-ic"><x-icon name="link" :size="16" :stroke="2" /></div>
        </div>

        <div class="tile">
            <div class="tl">งบประมาณรวม</div>
            <div class="tv money">{{ Thai::fmt($totalBudget) }}<small> บาท</small></div>
            <div class="tile-ic"><x-icon name="cash" :size="16" :stroke="2" /></div>
        </div>
    </div>

    @if ($unlinked)
        <div class="card" style="margin-bottom:14px">
            <div class="card-b">
                <div class="note">
                    <x-icon name="info" :size="14" :stroke="2" />
                    <span>มีกิจกรรม {{ Thai::fmt($unlinked) }} รายการที่ยังไม่ผูกกับโครงการหลัก
                        จึงไม่ปรากฏในหน้านี้ — เปิด
                        <a href="{{ route('activities.index') }}">หน้ากิจกรรม</a>
                        แล้วแก้กิจกรรมนั้นให้เลือกโครงการหลัก</span>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        {{-- ตัวกรองอยู่ในการ์ดเดียวกับตาราง เหมือนหน้าทะเบียนครัวเรือน
             เดิมแยกเป็นการ์ดของตัวเองที่มีช่องเดียว ทำให้หน้าดูโหว่ --}}
        <form method="GET" action="{{ route('programs.index') }}" class="tbar js-auto">
            <div class="ff">
                <span>ปีงบประมาณ</span>
                <select class="sel" name="fy">
                    <option value="">ทุกปีงบประมาณ</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected($fy === (string) $year)>ปีงบ {{ $year }}</option>
                    @endforeach
                </select>
            </div>

            @if ($statusReady)
                {{-- ค่าตั้งต้นคือ «กำลังดำเนินการ» — หน้าแรกจึงเห็นเฉพาะโครงการที่ยังเดินอยู่ --}}
                <div class="ff">
                    <span>สถานะ</span>
                    <select class="sel" name="st">
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($st === $status)>{{ $status }}</option>
                        @endforeach
                        <option value="all" @selected($st === 'all')>ทุกสถานะ</option>
                    </select>
                </div>
            @endif

            <span style="flex:1"></span>

            <span style="font-size:12.5px;color:var(--ink-3)">
                แสดง {{ Thai::fmt(count($programs)) }} โครงการ{{ $fy !== '' ? ' · ปีงบ '.$fy : ' · ทุกปีงบ' }}
                @if ($hiddenByStatus)
                    · ซ่อนไว้ {{ Thai::fmt($hiddenByStatus) }} โครงการ
                @endif
            </span>

            @if ($fy !== '' || ($statusReady && $st !== Program::STATUS_OPEN))
                <a class="btn ghost sm" href="{{ route('programs.index') }}">
                    <x-icon name="x" :size="14" :stroke="2.4" /> กลับค่าเริ่มต้น
                </a>
            @endif
        </form>

        <div class="tw">
            <table>
                <thead>
                <tr>
                    <th style="min-width:240px;width:auto">ชื่อโครงการหลัก</th>
                    <th style="width:100px">ปีงบ</th>
                    @if ($leadReady)
                        <th style="min-width:170px">อาจารย์หัวหน้าโครงการ</th>
                        <th style="min-width:180px">คณะที่รับผิดชอบ</th>
                    @endif
                    @if ($docReady)
                        <th style="width:170px">ไฟล์โครงการ</th>
                    @endif
                    @if ($statusReady)
                        <th style="width:150px">สถานะ</th>
                        <th style="width:180px">เล่มรายงาน (PDF)</th>
                    @endif
                    <th class="r" style="width:110px">กิจกรรม</th>
                    <th class="r" style="width:150px">งบประมาณ (บาท)</th>
                    <th class="r" style="width:130px">ครัวเรือนเข้าร่วม</th>
                    <th style="width:120px"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($programs as $program)
                    @php
                        $n = $activityCount[$program->id] ?? 0;
                        $b = $budgetOf[$program->id] ?? 0;
                        $e = $enrolledOf[$program->id] ?? 0;
                    @endphp
                    <tr>
                        <td><span class="t-name">{{ $program->name }}</span></td>
                        <td><span class="num">{{ $program->fiscal_year }}</span></td>
                        @if ($leadReady)
                            <td>
                                @if ($program->lead_name)
                                    <span class="t-name">{{ $program->lead_name }}</span>
                                @else
                                    <span class="t-empty">ยังไม่ระบุ</span>
                                @endif
                            </td>
                            <td>
                                @if ($program->unit)
                                    {{ $program->unit }}
                                @else
                                    <span class="t-empty">ยังไม่ระบุ</span>
                                @endif
                            </td>
                        @endif
                        @if ($docReady)
                            <td>
                                @if ($program->doc_file)
                                    <a href="{{ asset('uploads/program-docs/'.$program->doc_file) }}"
                                       target="_blank" rel="noopener"
                                       title="{{ $program->doc_name }}"
                                       style="display:inline-flex;align-items:center;gap:5px;white-space:nowrap">
                                        <x-icon name="down" :size="13" :stroke="2.2" />
                                        เปิดไฟล์โครงการ
                                    </a>
                                @else
                                    <span class="t-empty">ยังไม่มี</span>
                                @endif
                            </td>
                        @endif
                        @if ($statusReady)
                            <td>
                                {{-- ปิดโครงการ = ป้ายแดง ให้กวาดตาเห็นได้ทันทีว่าโครงการไหนจบแล้ว --}}
                                <span class="bg {{ $program->isClosed() ? 'b-crit' : 'b-brand' }}">
                                    {{ $program->status ?: Program::STATUS_OPEN }}
                                </span>
                                @if ($program->isClosed() && $program->closed_at)
                                    <div class="t-sub">ปิดเมื่อ {{ Thai::date($program->closed_at) }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($program->report_file)
                                    {{-- inline-flex + nowrap — ไม่งั้นไอคอนกับข้อความตกคนละบรรทัดเวลาช่องแคบ --}}
                                    <a href="{{ asset('uploads/program-reports/'.$program->report_file) }}"
                                       target="_blank" rel="noopener"
                                       title="{{ $program->report_name }}"
                                       style="display:inline-flex;align-items:center;gap:5px;white-space:nowrap">
                                        <x-icon name="down" :size="13" :stroke="2.2" />
                                        เปิดเล่มรายงาน
                                    </a>
                                @else
                                    <span class="t-empty">ยังไม่มี</span>
                                @endif
                            </td>
                        @endif
                        <td class="r">
                            @if ($n)
                                <a href="{{ route('activities.index', ['pg' => $program->id]) }}">
                                    <span class="num" style="font-weight:600">{{ Thai::fmt($n) }}</span>
                                </a>
                            @else
                                <span class="t-empty">—</span>
                            @endif
                        </td>
                        <td class="r"><span class="num" style="font-weight:600">{{ Thai::fmt($b) }}</span></td>
                        <td class="r">
                            @if ($e)
                                <span class="num">{{ Thai::fmt($e) }}</span>
                            @else
                                <span class="t-empty">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="rowacts">
                                <a class="ia" href="{{ route('activities.index', ['pg' => $program->id]) }}"
                                   title="ดูกิจกรรมของโครงการนี้">
                                    <x-icon name="link" :size="15" :stroke="2" />
                                </a>

                                <button type="button" class="ia" data-modal-open="m-program"
                                        data-program-id="{{ $program->id }}"
                                        data-program-name="{{ $program->name }}"
                                        data-program-fy="{{ $program->fiscal_year }}"
                                        data-program-lead="{{ $program->lead_name }}"
                                        data-program-unit="{{ $program->unit }}"
                                        data-program-status="{{ $program->status ?: Program::STATUS_OPEN }}"
                                        data-program-report="{{ $program->report_name }}"
                                        data-program-doc="{{ $program->doc_name }}" title="แก้ไข">
                                    <x-icon name="edit" :size="15" :stroke="2" />
                                </button>

                                <form method="POST" action="{{ route('programs.destroy', $program->id) }}" class="f-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="ia d" title="ลบ" data-confirm
                                            data-confirm-title="ยืนยันการลบโครงการหลัก"
                                            data-confirm-label="ลบโครงการ"
                                            data-confirm-body="กำลังจะลบ <b>{{ e($program->name) }}</b> ปีงบ {{ $program->fiscal_year }}@if ($n) — โครงการนี้ยังมีกิจกรรมอยู่ {{ Thai::fmt($n) }} รายการ ระบบจะไม่ยอมลบจนกว่าจะย้ายหรือลบกิจกรรมก่อน@endif">
                                        <x-icon name="trash" :size="15" :stroke="2" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($statusReady ? 8 : 6) + ($leadReady ? 2 : 0) + ($docReady ? 1 : 0) }}">
                            <div class="empty">
                                <div class="ic"><x-icon name="box" :size="22" :stroke="1.9" /></div>
                                <b>ไม่พบโครงการหลัก{{ $statusReady && $st !== 'all' ? 'ที่สถานะ «'.$st.'»' : '' }}{{ $fy !== '' ? ' ในปีงบ '.$fy : '' }}</b>
                                <p>เปลี่ยนช่องสถานะเป็น «ทุกสถานะ» เพื่อดูโครงการที่ปิดแล้ว
    หรือกดปุ่ม «เพิ่มโครงการหลัก» ด้านบนเพื่อเริ่มโครงการใหม่</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- หน้าต่างเดียวใช้ทั้งเพิ่มและแก้ไข — JS สลับ action กับหัวเรื่องให้ --}}
    <div class="modal sm js-modal" id="m-program" role="dialog" aria-modal="true">
        <form method="POST" id="programForm" action="{{ route('programs.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="programMethod" value="">

            <div class="md-h">
                <div class="ic-cir brand"><x-icon name="box" :size="18" :stroke="2.2" /></div>
                <div style="flex:1">
                    <h3 id="programTitle">เพิ่มโครงการหลัก</h3>
                    <p id="programSub">กิจกรรมจะยึดปีงบตามโครงการนี้</p>
                </div>
                <button type="button" class="icon-btn" data-modal-close aria-label="ปิด">
                    <x-icon name="x" :size="16" :stroke="2.2" />
                </button>
            </div>

            <div class="md-b">
                <div class="fgrid">
                    <div class="f full">
                        <label>ชื่อโครงการหลัก <span class="req">*</span></label>
                        <input name="name" id="programName" required maxlength="255"
                               placeholder="เช่น โครงการพัฒนาคุณภาพชีวิตครัวเรือนยากจน">
                    </div>
                    <div class="f full">
                        <label>ปีงบประมาณ (พ.ศ.) <span class="req">*</span></label>
                        <input name="fiscal_year" id="programFy" type="number" required
                               min="2560" max="2600" value="{{ $fy ?: 2569 }}" placeholder="2569">
                        <span class="hint">เปลี่ยนปีงบที่นี่ กิจกรรมใต้โครงการจะถูกย้ายปีงบตามให้อัตโนมัติ</span>
                    </div>

                    @if ($leadReady)
                        <div class="f full">
                            <label>อาจารย์หัวหน้าโครงการ</label>
                            <input name="lead_name" id="programLead" maxlength="180"
                                   placeholder="เช่น ผศ.ดร.สมชาย ใจดี">
                        </div>

                        <div class="f full">
                            <label>คณะ / หน่วยงานที่รับผิดชอบ</label>
                            {{-- ค่าเดิมที่ไม่อยู่ในรายการ (ข้อมูลเก่า) JS จะเติมเป็นตัวเลือกชั่วคราวให้
                                 จะได้ไม่ถูกล้างทิ้งตอนกดบันทึก --}}
                            <select name="unit" id="programUnit">
                                <option value="">— ไม่ระบุ —</option>
                                @foreach ($unitOptions as $unitOption)
                                    <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                @endforeach
                            </select>
                            @unless (count($unitOptions))
                                <span class="hint">ยังไม่มีรายชื่อคณะ — เพิ่มได้ที่เมนู
                                    <a href="{{ route('units.index') }}">คณะ / หน่วยงาน</a></span>
                            @endunless
                        </div>
                    @endif

                    @if ($docReady)
                        <div class="f full">
                            <label>ไฟล์โครงการ (PDF หรือ Word)</label>
                            <input type="file" name="document" id="programDoc"
                                   accept=".pdf,.doc,.docx,application/pdf"
                                   data-max-bytes="{{ $uploadLimit }}">
                            <span class="hint" id="programDocHint">
                                เอกสารโครงการฉบับเต็ม · ไม่เกิน {{ round($uploadLimit / 1048576, 1) }} MB · ไม่บังคับ
                            </span>
                        </div>
                    @endif

                    @if ($statusReady)
                        <div class="f full">
                            <label>สถานะโครงการ <span class="req">*</span></label>
                            <select name="status" id="programStatus" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ช่องแนบเล่มรายงาน — เปิดเฉพาะตอนเลือก «ปิดโครงการ»
                             ถ้าเคยแนบไว้แล้ว จะไม่บังคับแนบใหม่ (เว้นว่างไว้ = ใช้เล่มเดิม) --}}
                        <div class="f full" id="programReportBox" hidden>
                            <label>เล่มรายงานฉบับสมบูรณ์ (PDF) <span class="req">*</span></label>
                            <input type="file" name="report" id="programReport" accept="application/pdf,.pdf"
                                   data-max-bytes="{{ $uploadLimit }}">
                            <span class="hint" id="programReportHint">ไฟล์ PDF ไม่เกิน {{ round($uploadLimit / 1048576, 1) }} MB</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="md-f">
                <span style="flex:1"></span>
                <button type="button" class="btn out" data-modal-close>ยกเลิก</button>
                <button class="btn pri"><x-icon name="chk" :size="14" :stroke="2.4" /> บันทึก</button>
            </div>
        </form>
    </div>

    {{-- แม่แบบ URL ตอนแก้ไข — JS แทน __ID__ ด้วยรหัสโครงการ --}}
    <script type="application/json" id="program-update-url">@json(route('programs.update', ['id' => '__ID__']))</script>
    <script type="application/json" id="program-store-url">@json(route('programs.store'))</script>
@endsection
