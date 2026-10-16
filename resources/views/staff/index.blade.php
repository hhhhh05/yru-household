@extends('layouts.app')

@php use App\Support\Thai; @endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>เจ้าหน้าที่รับผิดชอบ</h2>
            <p>รายชื่อกลางของหน่วยงาน · ใช้อ้างอิงในเอกสารและการติดต่อ ·
                ทั้งหมด {{ Thai::fmt($totalAll) }} คน</p>
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
        {{-- ------------------------------------------------------- แถบตัวกรอง
             รูปแบบเดียวกับหน้าโครงการ/กิจกรรม — ช่องกว้างเท่ากัน มีป้ายกำกับเหนือช่อง
             ตัวเลขในการ์ดด้านล่างขยับตามที่กรองไว้ --}}
        <div class="card" style="margin-bottom:14px">
            <form method="GET" action="{{ route('staff.index') }}" class="tbar fbar js-auto">
                <div class="ff">
                    <span>คณะ / หน่วยงาน</span>
                    <select class="sel" name="unit">
                        <option value="">ทุกคณะ/หน่วยงาน</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->name }}" @selected($filters['unit'] === $unit->name)>
                                {{ mb_strlen($unit->name) > 40 ? mb_substr($unit->name, 0, 40).'…' : $unit->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($jobReady)
                    <div class="ff">
                        <span>โครงการหลัก</span>
                        <select class="sel" name="pg">
                            <option value="">ทุกโครงการหลัก</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected($filters['pg'] === (string) $program->id)>
                                    {{ $program->fiscal_year }} · {{ mb_strlen($program->name) > 36 ? mb_substr($program->name, 0, 36).'…' : $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @php
                        /* กิจกรรมแคบลงตามโครงการที่เลือก จะได้ไม่มีตัวเลือกที่กรองแล้วว่างเปล่า */
                        $activityChoices = $filters['pg'] === ''
                            ? $activities
                            : $activities->filter(fn ($a) => (string) $a->program_id === $filters['pg']);
                    @endphp

                    <div class="ff">
                        <span>กิจกรรม</span>
                        <select class="sel" name="pa">
                            <option value="">ทุกกิจกรรม</option>
                            @foreach ($activityChoices as $activity)
                                <option value="{{ $activity->id }}" @selected($filters['pa'] === (string) $activity->id)>
                                    {{ $activity->pa }} · {{ mb_strlen($activity->name) > 34 ? mb_substr($activity->name, 0, 34).'…' : $activity->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ff">
                        <span>สัญญาจ้าง</span>
                        <select class="sel" name="ct">
                            <option value="">ทุกสถานะสัญญา</option>
                            <option value="yes" @selected($filters['ct'] === 'yes')>แนบแล้ว</option>
                            <option value="no" @selected($filters['ct'] === 'no')>ยังไม่แนบ</option>
                        </select>
                    </div>
                @endif

                @if ($filtering)
                    <a class="btn ghost sm" href="{{ route('staff.index') }}">
                        <x-icon name="x" :size="14" :stroke="2.4" /> ล้างตัวกรอง
                    </a>
                @endif
            </form>
        </div>

        {{-- ------------------------------------------------------- การ์ดสรุป
             คิดตามตัวกรองที่เลือกอยู่ ไม่ใช่ทั้งระบบ --}}
        <div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-bottom:14px">
            <div class="tile">
                <div class="tl">เจ้าหน้าที่</div>
                <div class="tv">{{ Thai::fmt(count($staff)) }}<small> คน</small></div>
                <div class="td">
                    @if ($filtering)
                        จากทั้งหมด {{ Thai::fmt($totalAll) }} คน
                    @else
                        รายชื่อทั้งหมดในระบบ
                    @endif
                </div>
                <div class="tile-ic"><x-icon name="users" :size="16" :stroke="2" /></div>
            </div>

            @php
                /* ความครบถ้วนของข้อมูลเจ้าหน้าที่ — เฉลี่ยจากรายชื่อที่แสดงอยู่ (ตามตัวกรอง)
                   ไม่นับช่องกิจกรรม เพราะตั้งใจให้เว้นว่างได้ */
                $pcts = $staff->map(fn ($p) => $p->completeness($jobReady)['pct'])->all();
                $pctAvg = $pcts ? (int) round(array_sum($pcts) / count($pcts)) : 0;
                $pctFull = count(array_filter($pcts, fn ($v) => $v === 100));
            @endphp

            <div class="tile">
                <div class="tl">ข้อมูลเจ้าหน้าที่ครบถ้วน</div>
                <div class="tv">{{ $pctAvg }}<small> %</small></div>
                <div class="td">ครบ 100% แล้ว {{ Thai::fmt($pctFull) }}/{{ Thai::fmt(count($staff)) }} คน</div>
                <div class="tile-ic"><x-icon name="chk" :size="16" :stroke="2" /></div>
                <div class="meter" style="margin-top:9px">
                    <i style="width:{{ $pctAvg }}%"></i>
                </div>
            </div>

            @if ($jobReady)
                <div class="tile">
                    <div class="tl">เงินเดือนรวม</div>
                    <div class="tv money">{{ Thai::fmt($salarySum) }}<small> บาท/เดือน</small></div>
                    <div class="td">มีข้อมูลเงินเดือน {{ Thai::fmt($salaryCount) }}/{{ Thai::fmt(count($staff)) }} คน</div>
                    <div class="tile-ic"><x-icon name="cash" :size="16" :stroke="2" /></div>
                </div>

                <div class="tile">
                    <div class="tl">สัญญาจ้างที่แนบแล้ว</div>
                    <div class="tv">{{ Thai::fmt($contractCount) }}<small> / {{ Thai::fmt(count($staff)) }}</small></div>
                    <div class="td">ยังไม่แนบ {{ Thai::fmt(count($staff) - $contractCount) }} คน</div>
                    <div class="tile-ic"><x-icon name="sheet" :size="16" :stroke="2" /></div>
                    <div class="meter" style="margin-top:9px">
                        <i style="width:{{ count($staff) ? round($contractCount / count($staff) * 100, 1) : 0 }}%"></i>
                    </div>
                </div>

            @endif
        </div>

        <div class="card">
            <div class="tw">
                <table>
                    <thead>
                    <tr>
                        <th style="min-width:180px">ชื่อ - สกุล</th>
                        <th style="min-width:140px">ตำแหน่ง</th>
                        <th style="min-width:180px">คณะ / หน่วยงาน</th>
                        @if ($jobReady)
                            <th class="r" style="width:130px">เงินเดือน (บาท)</th>
                            <th style="width:150px">สัญญาจ้าง</th>
                        @endif
                        <th style="min-width:120px">เบอร์โทร</th>
                        <th style="min-width:180px">อีเมล</th>
                        <th style="width:150px">ข้อมูลครบ</th>
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
                            @if ($jobReady)
                                <td class="r">
                                    @if ($person->salary !== null)
                                        <span class="num" style="font-weight:600">{{ Thai::fmt((int) $person->salary) }}</span>
                                    @else
                                        <span class="t-empty">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($person->contract_file)
                                        <a href="{{ asset('uploads/staff-contracts/'.$person->contract_file) }}"
                                           target="_blank" rel="noopener" title="{{ $person->contract_name }}"
                                           style="display:inline-flex;align-items:center;gap:5px;white-space:nowrap">
                                            <x-icon name="down" :size="13" :stroke="2.2" />
                                            เปิดสัญญาจ้าง
                                        </a>
                                    @else
                                        <span class="t-empty">ยังไม่มี</span>
                                    @endif
                                </td>
                            @endif
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
                                @php $cm = $person->completeness($jobReady); @endphp
                                {{-- ชี้ที่แถบเพื่อดูว่ายังขาดช่องไหน --}}
                                <div style="display:flex;align-items:center;gap:8px"
                                     data-tip="<b>กรอกแล้ว {{ $cm['filled'] }}/{{ $cm['total'] }} ช่อง</b>{{ $cm['missing'] ? 'ยังขาด: '.e(implode(' · ', $cm['missing'])) : 'ครบทุกช่องแล้ว' }}">
                                    <div class="meter" style="flex:1"><i style="width:{{ $cm['pct'] }}%"></i></div>
                                    <b class="num" style="width:38px;text-align:right;font-weight:600;color:{{ $cm['pct'] === 100 ? 'var(--good-ink)' : 'inherit' }}">{{ $cm['pct'] }}%</b>
                                </div>
                            </td>
                            <td>
                                <div class="rowacts">
                                    <button type="button" class="ia" data-modal-open="m-staff"
                                            data-staff-id="{{ $person->id }}"
                                            data-staff-name="{{ $person->full_name }}"
                                            data-staff-position="{{ $person->position }}"
                                            data-staff-unit="{{ $person->unit }}"
                                            data-staff-phone="{{ $person->phone }}"
                                            data-staff-email="{{ $person->email }}"
                                            data-staff-program="{{ $person->program_id }}"
                                            data-staff-activity="{{ $person->activity_id }}"
                                            data-staff-salary="{{ $person->salary !== null ? (int) $person->salary : '' }}"
                                            data-staff-contract="{{ $person->contract_name }}" title="แก้ไข">
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
                            <td colspan="{{ $jobReady ? 9 : 7 }}">
                                <div class="empty">
                                    <div class="ic"><x-icon name="users" :size="22" :stroke="1.9" /></div>
                                    <b>{{ $filtering ? 'ไม่พบเจ้าหน้าที่ตามตัวกรองนี้' : 'ยังไม่มีรายชื่อเจ้าหน้าที่' }}</b>
                                    <p>{{ $filtering ? 'ลองล้างตัวกรอง หรือเลือกเงื่อนไขอื่น' : 'กดปุ่ม «เพิ่มเจ้าหน้าที่» ด้านบนเพื่อเริ่มบันทึก' }}</p>
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
        <form method="POST" id="staffForm" action="{{ route('staff.store') }}" enctype="multipart/form-data">
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
                    @if ($jobReady)
                        <div class="f full">
                            <label>โครงการหลักที่รับผิดชอบ</label>
                            <select name="program_id" id="staffProgram">
                                <option value="">— ไม่ระบุ —</option>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->id }}">ปีงบ {{ $program->fiscal_year }} · {{ $program->name }}</option>
                                @endforeach
                            </select>
                            @unless (count($programs))
                                <span class="hint">ยังไม่มีโครงการ — เพิ่มได้ที่เมนู
                                    <a href="{{ route('programs.index') }}" target="_blank" rel="noopener">โครงการหลัก</a></span>
                            @endunless
                        </div>

                        <div class="f full">
                            <label>กิจกรรมที่รับผิดชอบ</label>
                            {{-- รายการกิจกรรมถูกกรองตามโครงการที่เลือก (JS)
                                 เว้นว่างได้ = ดูแลทั้งโครงการ ไม่เจาะจงกิจกรรมเดียว --}}
                            <select name="activity_id" id="staffActivity">
                                <option value="">— ทั้งโครงการ / ไม่เจาะจง —</option>
                                @foreach ($activities as $activity)
                                    <option value="{{ $activity->id }}" data-program="{{ $activity->program_id }}">
                                        {{ $activity->pa }} · {{ mb_strlen($activity->name) > 48 ? mb_substr($activity->name, 0, 48).'…' : $activity->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="f full">
                            <label>เงินเดือนที่จ้าง (บาท / เดือน)</label>
                            <input name="salary" id="staffSalary" type="number" min="0" step="any"
                                   placeholder="เช่น 15000">
                        </div>

                        <div class="f full">
                            <label>สัญญาจ้าง (PDF หรือ Word)</label>
                            <input type="file" name="contract" id="staffContract"
                                   accept=".pdf,.doc,.docx,application/pdf"
                                   data-max-bytes="{{ $uploadLimit }}">
                            <span class="hint" id="staffContractHint">
                                ไม่บังคับ · ไฟล์ไม่เกิน {{ round($uploadLimit / 1048576, 1) }} MB
                            </span>
                        </div>
                    @endif

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
