@extends('layouts.app')

@php
    use App\Support\Thai;

    /* รายการไฟล์ที่ส่งออกได้: [ชนิด, ชื่อ, คำอธิบาย, ไอคอน] */
    $exports = [
        ['households', 'ทะเบียนครัวเรือน', $householdCount.' แถว · 11 คอลัมน์', 'users'],
        ['activities', 'โครงการ / กิจกรรม', $activityCount.' แถว · พร้อมงบประมาณ', 'box'],
        ['enrollments', 'รายชื่อเข้าร่วมโครงการ', $enrollmentCount.' แถว · HC ↔ PA', 'link'],
        ['areas', 'ข้อมูลพื้นที่', $areaCount.' แถว · รหัสพื้นที่', 'map'],
        ['quality', 'รายงานคุณภาพข้อมูล', $issueCount.' ประเด็น', 'shield'],
    ];

    $steps = [1 => 'แหล่งข้อมูล', 2 => 'จับคู่คอลัมน์', 3 => 'ตรวจสอบ', 4 => 'เสร็จสิ้น'];
@endphp

@section('content')
    <div class="page-head">
        <div class="lead">
            <h2>นำเข้า / ส่งออกข้อมูล</h2>
            <p>ซิงก์กับ Google Sheet เดิม หรือส่งออกเป็นไฟล์เพื่อทำรายงาน</p>
        </div>
    </div>

    <div class="grid2" style="align-items:start">
        {{-- ------------------------------------------------------- นำเข้า --}}
        <div class="card">
            <div class="card-h">
                <span style="color:var(--brand)"><x-icon name="up" :size="18" :stroke="2" /></span>
                <div style="flex:1">
                    <h3>นำเข้าข้อมูล</h3>
                    <p>4 ขั้นตอน มีการตรวจสอบก่อนบันทึกจริง</p>
                </div>
            </div>

            <div class="card-b">
                <div class="steps">
                    @foreach ($steps as $n => $label)
                        @if ($n > 1)
                            <div class="step-ln {{ $step > $n - 1 ? 'dn' : '' }}"></div>
                        @endif
                        <div class="step {{ $step === $n ? 'on' : ($step > $n ? 'dn' : '') }}">
                            <div class="sn">{{ $step > $n ? '✓' : $n }}</div>{{ $label }}
                        </div>
                    @endforeach
                </div>

                @if ($step === 1)
                    <div class="f full" style="margin-bottom:14px">
                        <label>ลิงก์ Google Sheet</label>
                        <input id="shurl" value="{{ $sheetUrl }}">
                        <span class="hint">ระบบจะอ่านทั้ง 4 ชีต: ครัวเรือน · โครงการ/กิจกรรม · รายชื่อเข้าร่วมโครงการ · ข้อมูลจังหวัด</span>
                    </div>
                    <div class="f full" style="margin-bottom:14px">
                        <label>ชีตที่ต้องการนำเข้า</label>
                        <select>
                            <option>ครัวเรือน ({{ $householdCount }} แถว)</option>
                            <option>โครงการ/กิจกรรม ({{ $activityCount }} แถว)</option>
                            <option>รายชื่อเข้าร่วมโครงการ ({{ $enrollmentCount }} แถว)</option>
                            <option>ข้อมูลจังหวัด ({{ $areaCount }} แถว)</option>
                            <option>ทั้ง 4 ชีต</option>
                        </select>
                    </div>

                    <div style="display:flex;align-items:center;gap:11px;margin:16px 0">
                        <div style="flex:1;height:1px;background:var(--line)"></div>
                        <span style="font-size:11.5px;color:var(--ink-3)">หรือ</span>
                        <div style="flex:1;height:1px;background:var(--line)"></div>
                    </div>

                    <label class="drop" style="display:block;cursor:pointer">
                        <div style="color:var(--ink-3);margin-bottom:9px"><x-icon name="sheet" :size="26" :stroke="1.8" /></div>
                        <b style="font-size:13.5px">ลากไฟล์มาวาง หรือกดเพื่อเลือก</b>
                        <div style="font-size:12px;color:var(--ink-3);margin-top:3px">.xlsx · .csv · ขนาดไม่เกิน 10 MB</div>
                        <input type="file" accept=".csv,.xlsx" style="display:none">
                    </label>
                @endif

                @if ($step === 2)
                    <p style="font-size:12.5px;color:var(--ink-3);margin-bottom:12px">
                        ระบบจับคู่คอลัมน์ให้อัตโนมัติแล้ว ตรวจสอบและปรับได้ตามต้องการ
                    </p>
                    @foreach ($columnMap as [$src, $field, $dst])
                        <div class="map-row">
                            <div class="map-src"><span class="bg out">{{ $src }}</span></div>
                            <div style="text-align:center;color:var(--good-ink)"><x-icon name="chk" :size="15" :stroke="2.6" /></div>
                            <select class="sel" style="max-width:none;width:100%">
                                <option>{{ $dst }}</option>
                                <option>— ไม่นำเข้า —</option>
                            </select>
                        </div>
                    @endforeach
                    <div class="inline-w" style="margin-top:14px">
                        <x-icon name="warn" :size="15" :stroke="2.2" />
                        <div>คอลัมน์ <b>K</b> ในชีตต้นทางไม่มีหัวตาราง — ระบบจะข้ามคอลัมน์นี้</div>
                    </div>
                @endif

                @if ($step === 3)
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:11px;margin-bottom:16px">
                        <div style="padding:13px;border:1px solid var(--line);border-radius:var(--r-m);background:var(--good-bg)">
                            <div style="font-size:11.5px;color:var(--good-ink);font-weight:600">พร้อมนำเข้า</div>
                            <div style="font-size:24px;font-weight:600;color:var(--good-ink);letter-spacing:-.02em">{{ $householdCount - $errorRows }}</div>
                        </div>
                        <div style="padding:13px;border:1px solid var(--line);border-radius:var(--r-m);background:var(--warning-bg)">
                            <div style="font-size:11.5px;color:var(--warning-ink);font-weight:600">มีคำเตือน</div>
                            <div style="font-size:24px;font-weight:600;color:var(--warning-ink);letter-spacing:-.02em">{{ $warnRows }}</div>
                        </div>
                        <div style="padding:13px;border:1px solid var(--line);border-radius:var(--r-m);background:var(--critical-bg)">
                            <div style="font-size:11.5px;color:var(--critical-ink);font-weight:600">ต้องแก้ก่อน</div>
                            <div style="font-size:24px;font-weight:600;color:var(--critical-ink);letter-spacing:-.02em">{{ $errorRows }}</div>
                        </div>
                    </div>

                    <b style="font-size:13px">ตัวอย่างแถวที่มีปัญหา</b>
                    <div style="border:1px solid var(--line);border-radius:var(--r-m);margin-top:9px;overflow:hidden">
                        @foreach (array_slice($issues, 0, 4) as $i)
                            <div style="display:flex;gap:9px;padding:10px 12px;border-bottom:1px solid var(--line);align-items:flex-start">
                                <span style="color:var(--{{ $i['sev'] }}-ink);margin-top:1px">
                                    <x-icon :name="$i['sev'] === 'warning' ? 'info' : 'warn'" :size="14" :stroke="2.2" />
                                </span>
                                <div style="flex:1;font-size:12.3px;line-height:1.5">
                                    <b>{{ $i['title'] }}</b>
                                    <div style="color:var(--ink-3);margin-top:2px">{!! $i['desc'] !!}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="f full" style="margin-top:14px">
                        <label>วิธีจัดการข้อมูลที่มีอยู่แล้ว</label>
                        <select>
                            <option>อัปเดตทับด้วยค่าจากชีต (แนะนำ)</option>
                            <option>ข้ามแถวที่มี HC ซ้ำ</option>
                            <option>เพิ่มเป็นรายการใหม่ทั้งหมด</option>
                        </select>
                    </div>
                @endif

                @if ($step === 4)
                    <div style="text-align:center;padding:22px 10px">
                        <div style="width:56px;height:56px;border-radius:17px;background:var(--good-bg);color:var(--good-ink);display:grid;place-items:center;margin:0 auto 14px">
                            <x-icon name="chk" :size="28" :stroke="2.6" />
                        </div>
                        <b style="font-size:16px">ขั้นตอนนำเข้าพร้อมใช้งาน</b>
                        <p style="font-size:13px;color:var(--ink-3);margin-top:5px">
                            จะอัปเดต {{ $householdCount - $errorRows }} ครัวเรือน · ข้าม {{ $errorRows }} แถวที่ต้องแก้ก่อน
                            <br>(ยังไม่บันทึกจริงเพราะยังไม่เชื่อมฐานข้อมูล)
                        </p>
                        <div style="display:flex;gap:9px;justify-content:center;margin-top:18px">
                            <a class="btn out" href="{{ route('quality.index') }}">ดูรายการที่ต้องแก้</a>
                            <a class="btn pri" href="{{ route('households.index') }}">เปิดทะเบียนครัวเรือน</a>
                        </div>
                    </div>
                @endif
            </div>

            @if ($step < 4)
                <div class="card-f">
                    @if ($step > 1)
                        <a class="btn out" href="{{ route('io.index', ['step' => $step - 1]) }}">ย้อนกลับ</a>
                    @endif
                    <span style="font-size:12px;color:var(--ink-3);margin-left:auto">ขั้นที่ {{ $step }} จาก 4</span>
                    <a class="btn pri" href="{{ route('io.index', ['step' => $step + 1]) }}">
                        {{ $step === 3 ? 'เริ่มนำเข้า' : 'ถัดไป' }}
                    </a>
                </div>
            @else
                <div class="card-f">
                    <a class="btn ghost" href="{{ route('io.index', ['step' => 1]) }}" style="margin-left:auto">นำเข้าอีกครั้ง</a>
                </div>
            @endif
        </div>

        {{-- ------------------------------------------------------- ส่งออก --}}
        <div style="display:flex;flex-direction:column;gap:16px">
            <div class="card">
                <div class="card-h">
                    <span style="color:var(--brand)"><x-icon name="down" :size="18" :stroke="2" /></span>
                    <div style="flex:1">
                        <h3>ส่งออกข้อมูล</h3>
                        <p>ไฟล์ที่ได้เปิดใน Excel และ Google Sheets ได้ทันที (UTF-8 BOM)</p>
                    </div>
                </div>
                <div class="card-b" style="display:flex;flex-direction:column;gap:9px">
                    @foreach ($exports as [$type, $title, $desc, $icon])
                        <a class="qcard" href="{{ route('export', $type) }}">
                            <span style="width:34px;height:34px;border-radius:10px;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center;flex:none">
                                <x-icon :name="$icon" :size="17" :stroke="2" />
                            </span>
                            <span style="flex:1">
                                <span style="display:block;font-size:13.3px;font-weight:600">{{ $title }}</span>
                                <span style="display:block;font-size:11.8px;color:var(--ink-3)">{{ $desc }}</span>
                            </span>
                            <span style="color:var(--ink-3)"><x-icon name="down" :size="16" :stroke="2.1" /></span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-h">
                    <div style="flex:1">
                        <h3>โครงสร้างข้อมูลที่ระบบใช้</h3>
                        <p>ความสัมพันธ์ระหว่าง 4 ชีต</p>
                    </div>
                </div>
                <div class="card-b">
                    <div style="font-family:'IBM Plex Sans',monospace;font-size:12px;line-height:2;color:var(--ink-2)">
                        <div><span class="bg b-brand">ครัวเรือน</span> <b>HC</b> ← คีย์หลัก</div>
                        <div style="padding-left:18px;color:var(--ink-3)">↳ ตำบล → <span class="bg out">ข้อมูลจังหวัด</span> รหัสพื้นที่</div>
                        <div style="padding-left:18px;color:var(--ink-3)">↳ HC → <span class="bg out">รายชื่อเข้าร่วมโครงการ</span></div>
                        <div style="padding-left:36px;color:var(--ink-3)">↳ PA → <span class="bg b-brand">โครงการ/กิจกรรม</span></div>
                    </div>
                    <div class="note" style="margin-top:13px">
                        <x-icon name="info" :size="14" :stroke="2" />
                        <span>รหัส HC ถอดความได้: <b class="code">PY 67 10 00001</b> = ยะลา · ปีงบ 67 · ตำบลรหัส 10 ·
                            ลำดับที่ 1 — ระบบจะออกเลขให้อัตโนมัติเมื่อเลือกตำบล</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
