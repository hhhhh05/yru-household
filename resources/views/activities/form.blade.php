@php
    use App\Support\Thai;
    use Illuminate\Support\Facades\Schema;

    /* คอลัมน์ใหม่ (อาจารย์ผู้รับผิดชอบ · ภาระงาน ฯลฯ) มีในฐานข้อมูลแล้วหรือยัง
       ต้องเช็กคอลัมน์ล่าสุดด้วย ไม่งั้นค่าที่กรอกจะถูกตัดทิ้งเงียบ ๆ ตอนบันทึก */
    $hasLecturerColumns = Schema::hasColumn('activities', 'lecturer_name')
        && Schema::hasColumn('activities', 'workload_per_week');

    $editing = (bool) $activity;
    $count = $editing ? ($counts[$activity['pa']] ?? 0) : 0;
    $err = fn (string $field) => $errors->has($field) ? 'err' : '';
    $fy = old('fy', $editing ? $activity['fy'] : 2569);
@endphp

<div class="dw-h">
    <div class="ic-cir brand"><x-icon :name="$editing ? 'edit' : 'plus'" :size="19" :stroke="2.1" /></div>
    <div style="flex:1">
        <h3>{{ $editing ? 'แก้ไขกิจกรรม' : 'เพิ่มกิจกรรมใหม่' }}</h3>
        <p>
            @if ($editing)
                <span class="code">{{ $activity['pa'] }}</span> · {{ $count }} ครัวเรือนเข้าร่วม
            @else
                ภายใต้ยุทธศาสตร์ที่ 1 การพัฒนาท้องถิ่น
            @endif
        </p>
    </div>
    <a class="icon-btn" href="{{ $closeUrl }}" aria-label="ปิด"><x-icon name="x" :size="16" :stroke="2.2" /></a>
</div>

<form id="pjf" method="POST"
      action="{{ $editing ? route('activities.update', ['pa' => $activity['pa']] + qs()) : route('activities.store') }}">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="dw-b">
        @unless ($hasLecturerColumns)
            <div class="inline-w" style="margin-bottom:18px">
                <x-icon name="warn" :size="16" :stroke="2.2" />
                <div>
                    <b>ต้องอัปเดตโครงสร้างฐานข้อมูลก่อน</b> — ช่องอาจารย์ผู้รับผิดชอบ เบอร์โทร
                    เลขประจำตัวประชาชน ภาระงาน/สัปดาห์ และคำอธิบายโครงการ ยังไม่มีคอลัมน์รองรับ
                    <div style="margin-top:8px">
                        <button type="submit" form="migrateForm" class="btn pri sm">
                            <x-icon name="swap" :size="13" :stroke="2.2" /> อัปเดตฐานข้อมูลให้เลย
                        </button>
                    </div>
                </div>
            </div>
        @endunless

        <div class="fgrid">
            <div class="f">
                <label>รหัส PA</label>
                <input name="pa_display" value="{{ $editing ? $activity['pa'] : '' }}" placeholder="ออกให้อัตโนมัติ" readonly>
                <span class="hint">LP + ปีงบ 2 หลัก + ลำดับ 3 หลัก</span>
            </div>

            <div class="f {{ $err('fy') }}">
                <label>ปีงบประมาณ <span class="req">*</span></label>
                <select name="fy" id="pjFy">
                    @foreach ($yearOptions as $year)
                        <option value="{{ $year }}" @selected((string) $fy === (string) $year)>{{ $year }}</option>
                    @endforeach
                    <option value="__new" @selected(old('fy') === '__new')>＋ เพิ่มปีงบใหม่…</option>
                </select>

                {{-- ช่องกรอกปีงบใหม่ (โผล่เมื่อเลือก «เพิ่มปีงบใหม่») --}}
                <input type="number" name="fy_new" id="pjFyNew" min="2560" max="2600" step="1"
                       value="{{ old('fy_new') }}" placeholder="เช่น 2571"
                       style="margin-top:6px" @unless(old('fy') === '__new') hidden @endunless>

                <span class="hint">รายการปีงบมาจากตาราง <b>programs</b> (โครงการหลัก)</span>
                <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('fy') ?: 'เลือกปีงบประมาณ' }}</span>
            </div>

            @php
                /* โครงการหลักของปีงบที่เลือกอยู่ — เลือกจากตาราง programs */
                $yearPrograms = $programsByYear[(int) $fy] ?? [];
                $currentProgramId = (string) old('program_id', $editing ? ($activity['program_id'] ?? '') : '');
                $isNewProgram = $currentProgramId === '__new';
            @endphp
            <div class="f full {{ $err('program_id') }}">
                <label>ชื่อโครงการหลัก <span class="req">*</span>
                    <span class="tag-sug" id="pjProgramYear">ปีงบ {{ $fy }}</span></label>

                <select name="program_id" id="pjProgram">
                    @forelse ($yearPrograms as $option)
                        <option value="{{ $option['id'] }}" @selected($currentProgramId === (string) $option['id'])>
                            {{ $option['name'] }}
                        </option>
                    @empty
                        <option value="" disabled @selected(! $isNewProgram)>— ปีงบนี้ยังไม่มีโครงการหลัก —</option>
                    @endforelse
                    <option value="__new" @selected($isNewProgram)>＋ เพิ่มโครงการหลักใหม่…</option>
                </select>

                {{-- ช่องพิมพ์ชื่อโครงการหลักใหม่ (โผล่เมื่อเลือก «เพิ่มโครงการหลักใหม่») --}}
                <input name="program_new" id="pjProgramNew" style="margin-top:6px"
                       value="{{ old('program_new') }}"
                       placeholder="ชื่อโครงการหลักใหม่ของปีงบนี้"
                       @unless ($isNewProgram || ! $yearPrograms) hidden @endunless>

                <span class="hint" id="pjProgramHint">
                    @if ($yearPrograms)
                        เลือกจากตาราง programs — ปีงบ {{ $fy }} มี {{ count($yearPrograms) }} โครงการหลัก
                    @else
                        ปีงบ {{ $fy }} ยังไม่มีโครงการหลัก — พิมพ์ชื่อใหม่ในช่องด้านบน
                    @endif
                </span>
                <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" />
                    {{ $errors->first('program_id') ?: ($errors->first('program_new') ?: 'เลือกโครงการหลัก') }}</span>
            </div>

            <div class="f full {{ $err('name') }}">
                <label>ชื่อกิจกรรม <span class="req">*</span></label>
                <textarea name="name" placeholder="เช่น ส่งเสริมการเลี้ยงผึ้งชันโรงเพื่อแก้ปัญหาความยากจน">{{ old('name', $editing ? $activity['name'] : '') }}</textarea>
                <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('name') ?: 'กรอกชื่อกิจกรรม' }}</span>
            </div>

            <div class="f {{ $err('budget') }}">
                <label>งบประมาณ (บาท) <span class="req">*</span></label>
                <input name="budget" type="number" min="0" step="1000"
                       value="{{ old('budget', $editing ? $activity['budget'] : '') }}" placeholder="450000">
                <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('budget') ?: 'กรอกงบประมาณ' }}</span>
            </div>

            <div class="f">
                <label>เป้าหมายครัวเรือน <span class="tag-sug">แนะนำเพิ่ม</span></label>
                <input name="target" type="number" min="0" value="{{ old('target', $editing ? max(10, $count) : 30) }}">
            </div>

            <div class="f full">
                <label>หน่วยงานรับผิดชอบ <span class="tag-sug">แนะนำเพิ่ม</span></label>
                @php
                    $units = [
                        'สถาบันวิจัยและพัฒนาชายแดนภาคใต้',
                        'คณะวิทยาศาสตร์เทคโนโลยีและการเกษตร',
                        'คณะวิทยาการจัดการ',
                        'คณะมนุษยศาสตร์และสังคมศาสตร์',
                        'คณะครุศาสตร์',
                    ];
                    $currentUnit = old('unit', $editing ? ($activity['unit'] ?? '') : '');
                @endphp
                <select name="unit">
                    @foreach ($units as $unitOption)
                        <option value="{{ $unitOption }}" @selected($currentUnit === $unitOption)>{{ $unitOption }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ------------------------------------------- อาจารย์ผู้รับผิดชอบ --}}
        <div class="f-sec" style="margin-top:18px">
            <div class="f-sec-h"><div class="n">2</div><h4>อาจารย์ผู้รับผิดชอบ</h4><div class="ln"></div></div>
            <div class="fgrid">
                <div class="f {{ $err('lecturer_name') }}">
                    <label>อาจารย์ที่รับผิดชอบ</label>
                    <input name="lecturer_name" value="{{ old('lecturer_name', $editing ? ($activity['lecturer'] ?? '') : '') }}"
                           placeholder="เช่น ผศ.ดร.สมชาย ใจดี">
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('lecturer_name') }}</span>
                </div>

                <div class="f {{ $err('lecturer_phone') }}">
                    <label>เบอร์โทร</label>
                    <input name="lecturer_phone" id="lecturerPhone" inputmode="numeric"
                           value="{{ old('lecturer_phone', $editing ? ($activity['lecturer_phone'] ?? '') : '') }}"
                           placeholder="0xx-xxx-xxxx">
                    <span class="hint">ระบบจัดรูปแบบให้อัตโนมัติ</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('lecturer_phone') ?: 'เบอร์โทรต้องมี 9–10 หลัก' }}</span>
                </div>

                <div class="f {{ $err('lecturer_id_card') }}">
                    <label>เลขประจำตัวประชาชน</label>
                    <input name="lecturer_id_card" id="lecturerIdCard" inputmode="numeric" maxlength="17"
                           value="{{ old('lecturer_id_card', $editing ? Thai::formatCitizenId($activity['lecturer_id_card'] ?? '') : '') }}"
                           placeholder="x-xxxx-xxxxx-xx-x">
                    <span class="hint" id="idCardHint">13 หลัก · ระบบตรวจหลักสุดท้ายให้</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('lecturer_id_card') ?: 'เลขประจำตัวประชาชนไม่ถูกต้อง' }}</span>
                </div>

                <div class="f {{ $err('workload') }}">
                    <label>ภาระงาน / สัปดาห์</label>
                    <input name="workload" type="number" inputmode="decimal" min="0" max="168" step="0.5"
                           value="{{ old('workload', $editing ? ($activity['workload'] ?? '') : '') }}"
                           placeholder="เช่น 6 หรือ 7.5">
                    <span class="hint">หน่วยเป็นชั่วโมงต่อสัปดาห์ · ใส่ครึ่งชั่วโมงได้</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" />
                        {{ $errors->first('workload') ?: 'ภาระงานต้องเป็นตัวเลข 0–168' }}</span>
                </div>

                <div class="f full {{ $err('description') }}">
                    <label>คำอธิบายเกี่ยวกับโครงการ</label>
                    <textarea name="description" rows="3"
                              placeholder="วัตถุประสงค์ · กลุ่มเป้าหมาย · กิจกรรมที่จะทำ · ผลลัพธ์ที่คาดหวัง">{{ old('description', $editing ? ($activity['description'] ?? '') : '') }}</textarea>
                    <span class="hint">ไม่เกิน 2,000 ตัวอักษร</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('description') }}</span>
                </div>
        </div>
    </div>

    <div class="dw-f">
        <span style="flex:1"></span>
        <a class="btn out" href="{{ $closeUrl }}">ยกเลิก</a>
        <button class="btn pri"><x-icon name="chk" :size="15" :stroke="2.4" /> บันทึก</button>
    </div>
</form>

{{-- ฟอร์มสำหรับปุ่ม "อัปเดตฐานข้อมูลให้เลย" (แยกออกมาเพราะซ้อน <form> ไม่ได้) --}}
<form id="migrateForm" method="POST" action="{{ route('setup.migrate') }}">@csrf</form>

{{-- โครงการหลักแยกตามปีงบ — ใช้เปลี่ยนรายการตามปีที่เลือก --}}
<script type="application/json" id="programs-by-year">@json($programsByYear)</script>
