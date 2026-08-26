@php
    use App\Support\Thai;

    $editing = (bool) $household;

    $prov = old('prov', $editing ? $household['prov'] : 'ยะลา');
    $dist = old('dist', $editing ? $household['dist'] : '');
    $tam = old('tam', $editing ? $household['tam'] : '');

    $areas = app(\App\Repositories\AreaRepository::class);
    $distOptions = $areas->districtsIn($prov ?: null);
    $tamOptions = $areas->tambonsIn($prov ?: null, $dist ?: null);

    /* คลาส .err ใช้แสดงกรอบแดง + ข้อความใต้ช่อง */
    $err = fn (string $field) => $errors->has($field) ? 'err' : '';
@endphp

<div class="dw-h">
    <div class="ic-cir brand"><x-icon :name="$editing ? 'edit' : 'plus'" :size="19" :stroke="2.1" /></div>
    <div style="flex:1">
        <h3>{{ $editing ? 'แก้ไขข้อมูลครัวเรือน' : 'เพิ่มครัวเรือนใหม่' }}</h3>
        <p>
            @if ($editing)
                <span class="code">{{ $household['hc'] }}</span> · แก้ไขล่าสุดวันนี้
            @else
                ระบบจะออกรหัส HC ให้อัตโนมัติจากตำบลที่เลือก
            @endif
        </p>
    </div>
    <a class="icon-btn" href="{{ $closeUrl }}" aria-label="ปิด"><x-icon name="x" :size="16" :stroke="2.2" /></a>
</div>

<form id="hhf" method="POST"
      action="{{ $editing ? route('households.update', ['hc' => $household['hc']] + qs()) : route('households.store') }}"
      data-editing="{{ $editing ? 1 : 0 }}" data-hc="{{ $editing ? $household['hc'] : '' }}"
      data-next-seq="{{ $nextSequence }}">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="dw-b">
        <div id="dupw"></div>

        {{-- ผลค้นชื่อจากฐานข้อมูล + ปุ่มเติมข้อมูลทั่วไป (สร้างด้วย JS) --}}
        <div id="hhFound"></div>

        {{-- รหัส HC ของครัวเรือนเดิมที่ผู้ใช้กดเลือก — ถ้ามีค่า ระบบจะไม่สร้างครัวเรือนใหม่
             แต่บันทึกแค่การเข้าร่วมกิจกรรมให้คนเดิม (JS ล้างค่านี้เมื่อมีการแก้ชื่อ) --}}
        <input type="hidden" name="existing_hc" id="hhExistingHc" value="">

        @if (empty($tamOptions) && empty($distOptions))
            {{-- ยังไม่มีข้อมูลพื้นที่ในฐานข้อมูล → เลือกตำบลไม่ได้ จึงบันทึกไม่ได้ --}}
            <div class="inline-w" style="margin-bottom:18px">
                <x-icon name="warn" :size="16" :stroke="2.2" />
                <div>
                    <b>ยังไม่มีข้อมูลพื้นที่ในฐานข้อมูล</b> — ต้องมีจังหวัด/อำเภอ/ตำบลก่อนจึงบันทึกครัวเรือนได้
                    <div style="margin-top:8px">
                        <button type="submit" form="seedAreasForm" class="btn pri sm">
                            <x-icon name="map" :size="13" :stroke="2.2" /> ใส่ข้อมูลพื้นที่ให้เลย (3 จังหวัด · 34 ตำบล)
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ------------------------------------------------ ข้อมูลครัวเรือน --}}
        <div class="f-sec">
            <div class="f-sec-h"><div class="n">1</div><h4>ข้อมูลครัวเรือน</h4><div class="ln"></div></div>
            <div class="fgrid">
                <div class="f full">
                    <label>รหัส HC</label>
                    <input name="hc" value="{{ $editing ? $household['hc'] : '' }}" placeholder="ออกให้อัตโนมัติ" readonly>
                    <span class="hint" id="hchint">PY + ปีงบ + รหัสพื้นที่ + ลำดับ</span>
                </div>
                {{-- คำนำหน้า + ชื่อ + นามสกุล อยู่ช่องเดียว และเก็บเป็นคอลัมน์เดียว
                     households.full_name — ระบบยังแยกส่วนด้วย Thai::splitName()
                     ก่อนบันทึก เพื่อจัดรูปแบบให้เป็นมาตรฐานเดียวกันทุกแถว --}}
                <div class="f full {{ $err('fullname') }}">
                    <label>คำนำหน้า ชื่อ - สกุล <span class="req">*</span></label>
                    <input name="fullname" id="hhName" list="nameL" autocomplete="off"
                           value="{{ old('fullname', $editing ? $household['name'] : '') }}"
                           placeholder="เช่น นางสาวซูรียะห์ มูซอ">
                    <span class="hint" id="hhNameHint">พิมพ์ต่อกันได้เลย — ระบบแยกคำนำหน้า ชื่อ และนามสกุลให้เอง</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" />
                        {{ $errors->first('fullname') ?: 'กรอกคำนำหน้า ชื่อ และนามสกุล' }}</span>

                    {{-- รายการแนะนำจริงถูกสร้างด้วย JS = คำนำหน้า + ชื่อที่มีอยู่ในฐานข้อมูล --}}
                    <datalist id="nameL"></datalist>

                    {{-- คลังคำนำหน้าที่ระบบรู้จัก (JS อ่านจากที่นี่) --}}
                    <datalist id="pfL">
                        @foreach (Thai::PREFIXES as $p)
                            <option value="{{ $p }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </div>
        </div>

        {{-- ---------------------------------------------- ที่อยู่และพื้นที่ --}}
        <div class="f-sec">
            <div class="f-sec-h"><div class="n">2</div><h4>ที่อยู่และพื้นที่</h4><div class="ln"></div></div>
            <div class="fgrid">
                <div class="f {{ $err('house') }}">
                    <label>บ้านเลขที่ <span class="req">*</span></label>
                    <input name="house" value="{{ old('house', $editing ? $household['house'] : '') }}" placeholder="เช่น 92/2">
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('house') ?: 'กรอกบ้านเลขที่' }}</span>
                </div>
                <div class="f {{ $err('moo') }}">
                    <label>หมู่ที่ <span class="req">*</span></label>
                    <input name="moo" type="number" min="1" max="30"
                           value="{{ old('moo', $editing ? $household['moo'] : '') }}" placeholder="1">
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('moo') ?: 'กรอกหมู่ที่' }}</span>
                </div>
                <div class="f {{ $err('vill') }}">
                    <label>บ้าน / ชุมชน <span class="req">*</span></label>
                    <input name="vill" id="hhVill" list="villL" autocomplete="off"
                           value="{{ old('vill', $editing ? $household['vill'] : '') }}"
                           placeholder="พิมพ์ชื่อหมู่บ้าน เช่น โฉลง">
                    {{-- รายการแนะนำถูกสร้างใหม่ด้วย JS ทุกครั้งที่เปลี่ยนตำบล --}}
                    <datalist id="villL">
                        @foreach ($villages as $v)
                            <option value="{{ $v }}"></option>
                        @endforeach
                    </datalist>
                    <span class="hint" id="hhVillHint">เก็บเป็นชื่อ พิมพ์ได้อิสระ — รายการแนะนำกรองตามตำบลที่เลือก</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('vill') ?: 'กรอกชื่อหมู่บ้าน/ชุมชน' }}</span>
                </div>
                <div class="f">
                    <label>จังหวัด <span class="req">*</span></label>
                    <select name="prov">
                        @foreach ($provinces as $p)
                            <option value="{{ $p }}" @selected($prov === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="f {{ $err('dist') }}">
                    <label>อำเภอ <span class="req">*</span></label>
                    <select name="dist">
                        <option value="">— เลือกอำเภอ —</option>
                        @foreach ($distOptions as $d)
                            <option value="{{ $d }}" @selected($dist === $d)>อ.{{ $d }}</option>
                        @endforeach
                    </select>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('dist') ?: 'เลือกอำเภอ' }}</span>
                </div>
                <div class="f {{ $err('tam') }}">
                    <label>ตำบล <span class="req">*</span></label>
                    <select name="tam">
                        <option value="">— เลือกตำบล —</option>
                        @foreach ($tamOptions as $t)
                            <option value="{{ $t }}" @selected($tam === $t)>
                                ต.{{ Thai::tamName($t) }} (รหัส {{ Thai::tamCode($t) }})
                            </option>
                        @endforeach
                    </select>
                    <span class="hint">รหัสพื้นที่จะถูกนำไปสร้างรหัส HC ให้อัตโนมัติ</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('tam') ?: 'เลือกตำบล — จำเป็นสำหรับออกรหัส HC' }}</span>
                </div>
            </div>
        </div>

        {{-- ------------------------------------------ การติดต่อและรายได้ --}}
        <div class="f-sec">
            <div class="f-sec-h"><div class="n">3</div><h4>การติดต่อและรายได้</h4><div class="ln"></div></div>
            <div class="fgrid">
                <div class="f {{ $err('phone') }}">
                    <label>เบอร์โทรศัพท์</label>
                    <input name="phone" value="{{ old('phone', $editing ? $household['phone'] : '') }}"
                           placeholder="0xx-xxx-xxxx" inputmode="numeric">
                    <span class="hint">ระบบจัดรูปแบบให้อัตโนมัติ</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('phone') ?: 'เบอร์ต้องมี 10 หลัก' }}</span>
                </div>
                <div class="f {{ $err('lat') }}">
                    <label>ละติจูด (Latitude) <span class="tag-sug">พิกัดที่ตั้ง</span></label>
                    <input name="lat" id="latInput" type="text" inputmode="decimal"
                           value="{{ old('lat', $editing ? ($household['lat'] ?? '') : '') }}"
                           placeholder="เช่น 6.512345">
                    <span class="hint">วางพิกัดคู่จาก Google Maps ได้เลย ระบบจะแยกช่องให้</span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('lat') ?: 'ละติจูดต้องอยู่ระหว่าง -90 ถึง 90' }}</span>
                </div>
                <div class="f {{ $err('lng') }}">
                    <label>ลองจิจูด (Longitude) <span class="tag-sug">พิกัดที่ตั้ง</span></label>
                    <input name="lng" id="lngInput" type="text" inputmode="decimal"
                           value="{{ old('lng', $editing ? ($household['lng'] ?? '') : '') }}"
                           placeholder="เช่น 101.280123">
                    <span class="hint">
                        <a id="mapLink" href="https://www.google.com/maps" target="_blank" rel="noopener"
                           hidden>เปิดตำแหน่งนี้ใน Google Maps</a>
                        <span id="mapHint">ทศนิยม 6 ตำแหน่ง</span>
                    </span>
                    <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('lng') ?: 'ลองจิจูดต้องอยู่ระหว่าง -180 ถึง 180' }}</span>
                </div>
                <div class="f">
                    <label>รายได้ BL <span class="tag-sug">บาท/ปี</span></label>
                    <input name="income" type="number" min="0" step="1000"
                           value="{{ old('income', $editing ? $household['income'] : '') }}" placeholder="เช่น 130000">
                    <span class="hint" id="incHint">
                        @if ($editing && $household['income'] !== null)
                            ≈ {{ Thai::fmt(round($household['income'] / 12)) }} บาท/เดือน
                        @else
                            เว้นว่างได้หากยังไม่มีข้อมูล
                        @endif
                    </span>
                </div>
                <div class="f span2">
                    <label>หมายเหตุ</label>
                    {{-- ต้องดึงค่าเดิมมาแสดงด้วย ไม่งั้นตอนแก้ไขช่องจะว่าง
                         แล้วการกดบันทึกจะเขียนทับหมายเหตุเดิมให้หายไป --}}
                    <textarea name="note"
                              placeholder="บันทึกเพิ่มเติม เช่น อาชีพหลัก จำนวนสมาชิก ความต้องการช่วยเหลือ">{{ old('note', $editing ? ($household['note'] ?? '') : '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- ------------------------------------- เข้าร่วมโครงการ/กิจกรรม
             แสดงเฉพาะตอน «เพิ่มครัวเรือนใหม่»
             หน้าแก้ไขไม่ต้องมี — การเพิ่ม/ถอนกิจกรรมของครัวเรือนที่มีอยู่แล้ว
             ทำที่หน้า «รายชื่อเข้าร่วมโครงการ» ซึ่งเห็นรายการที่เข้าร่วมอยู่ทั้งหมด --}}
        @unless ($editing)
        @php
            /* ปีงบ → โครงการหลัก → กิจกรรม (เลือกต่อกันเป็นชั้น ๆ ด้วย JS)
               ไม่บังคับกรอก — เว้นว่างได้ถ้ายังไม่รู้ว่าจะเข้าร่วมกิจกรรมใด */
            $enFy = old('en_fy', '');
            $enPg = old('en_pg', '');
            $enPa = old('en_pa', '');
            $enrollYears = array_keys($programsByYear);
            rsort($enrollYears);
        @endphp
        <div class="f-sec">
            <div class="f-sec-h"><div class="n">4</div><h4>เข้าร่วมโครงการ / กิจกรรม</h4><div class="ln"></div></div>

            @if (! $activities)
                <div class="inline-w">
                    <x-icon name="info" :size="16" :stroke="2.2" />
                    <div>ยังไม่มีกิจกรรมในระบบ — บันทึกครัวเรือนได้เลย
                        แล้วไปเพิ่มรายชื่อเข้าร่วมทีหลังที่หน้า <b>โครงการ / กิจกรรม</b></div>
                </div>
            @else
                <div class="fgrid">
                    <div class="f">
                        <label>ปีงบประมาณ <span class="tag-sug">ไม่บังคับ</span></label>
                        <select name="en_fy" id="enFy">
                            <option value="">— ไม่เข้าร่วมกิจกรรมตอนนี้ —</option>
                            @foreach ($enrollYears as $year)
                                <option value="{{ $year }}" @selected((string) $enFy === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                        <span class="hint">เลือกปีงบก่อน แล้วรายการโครงการจะกรองให้</span>
                    </div>

                    <div class="f">
                        <label>โครงการหลัก</label>
                        <select name="en_pg" id="enPg" data-keep="{{ $enPg }}" @disabled($enFy === '')>
                            <option value="">— เลือกปีงบก่อน —</option>
                        </select>
                        <span class="hint" id="enPgHint">มาจากตาราง programs</span>
                    </div>

                    <div class="f full {{ $err('en_pa') }}">
                        <label>กิจกรรม</label>
                        <select name="en_pa" id="enPa" data-keep="{{ $enPa }}" @disabled($enPg === '')>
                            <option value="">— เลือกโครงการหลักก่อน —</option>
                        </select>
                        <span class="hint" id="enPaHint">
                            เลือกแล้วระบบจะเพิ่มชื่อครัวเรือนนี้เข้ากิจกรรมให้ทันทีที่กดบันทึก
                        </span>
                        <span class="msg"><x-icon name="warn" :size="12" :stroke="2.4" /> {{ $errors->first('en_pa') }}</span>
                    </div>

                    <div class="f">
                        <label>สถานะการเข้าร่วม</label>
                        <select name="en_status">
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption }}" @selected(old('en_status', 'รอเริ่ม') === $statusOption)>{{ $statusOption }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif
        </div>
        @endunless
    </div>

    <div class="dw-f">
        <span style="flex:1"></span>
        <a class="btn out" href="{{ $closeUrl }}">ยกเลิก</a>
        <button class="btn pri">
            <x-icon name="chk" :size="15" :stroke="2.4" /> {{ $editing ? 'บันทึกการแก้ไข' : 'บันทึกครัวเรือน' }}
        </button>
    </div>
</form>

@if ($editing)
    @php
        /* เข้าร่วมโครงการอยู่กี่กิจกรรม — ใช้ตัดสินว่าลบได้ไหม */
        $joinedCount = count(app(\App\Repositories\EnrollmentRepository::class)->forHousehold($household['hc']));
    @endphp
    <div style="padding:0 20px 14px;background:var(--surface-2)">
        @if ($joinedCount)
            <div class="inline-w" style="margin:0">
                <x-icon name="warn" :size="16" :stroke="2.2" />
                <div><b>ลบครัวเรือนนี้ไม่ได้</b> — เข้าร่วมอยู่ {{ $joinedCount }} กิจกรรม
                    ถอนออกจากกิจกรรมให้ครบก่อน แล้วปุ่มลบจะกลับมา
                    <div style="margin-top:8px">
                        {{-- ต้องส่ง pa='' ด้วย ไม่งั้นหน้านั้นจะกรองเฉพาะกิจกรรมค่าตั้งต้น
                             แล้วจะไม่เห็นกิจกรรมอื่นที่ครัวเรือนนี้เข้าร่วมอยู่ --}}
                        <a class="btn out sm" href="{{ route('enrollments.index', ['pa' => '', 'q' => $household['hc']]) }}">
                            <x-icon name="link" :size="13" :stroke="2.2" /> ไปที่รายชื่อเข้าร่วมของครัวเรือนนี้
                        </a>
                    </div>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('households.destroy', ['hc' => $household['hc']] + qs()) }}">
                @csrf
                @method('DELETE')
                <button type="button" class="btn dang" data-confirm
                        data-confirm-title="ยืนยันการลบครัวเรือน"
                        data-confirm-label="ลบครัวเรือน"
                        data-confirm-body="กำลังจะลบ <b>{{ e($household['name']) }}</b> ({{ $household['hc'] }})">
                    <x-icon name="trash" :size="14" :stroke="2.2" /> ลบครัวเรือนนี้
                </button>
            </form>
        @endif
    </div>
@endif

{{-- ข้อมูลที่สคริปต์ต้องใช้: ตรวจรายการซ้ำ + อำเภอ/ตำบลแบบต่อเนื่อง --}}
<script type="application/json" id="household-index">@json($householdIndex)</script>
<script type="application/json" id="area-data">@json($areaData)</script>

{{-- ชื่อหมู่บ้านแยกตามตำบล + ป้ายตำบล → tambon_id สำหรับตัวช่วยกรองตอนพิมพ์ --}}
<script type="application/json" id="village-suggestions">@json($villageSuggestions)</script>
<script type="application/json" id="tambon-ids">@json($tambonIds)</script>

{{-- ปีงบ → โครงการหลัก → กิจกรรม สำหรับ dropdown แบบต่อเนื่องในหัวข้อที่ 4 --}}
<script type="application/json" id="enroll-programs">@json($programsByYear)</script>
@php
    /* ต้องเตรียมอาร์เรย์ให้เสร็จในบล็อกนี้ก่อน แล้วส่งเข้าไปเป็น «ตัวแปรเดียว»
       ห้ามเขียนอาร์เรย์ซ้อนลงในไดเรกทีฟ json ตรง ๆ เพราะ Laravel ตัดอาร์กิวเมนต์
       ด้วย explode(',') แบบไม่ดูวงเล็บ คอมมาตัวที่ 3 ขึ้นไปจะถูกทิ้ง
       ทำให้ PHP ที่ได้มีก้ามปูไม่ปิด → Unclosed '[' does not match ')' */
    $enrollActivities = [];

    foreach ($activities as $a) {
        $enrollActivities[] = [
            'pa' => $a['pa'],
            'name' => $a['name'],
            'fy' => $a['fy'],
            'pg' => $a['program_id'] ?? null,
        ];
    }
@endphp
<script type="application/json" id="enroll-activities">@json($enrollActivities)</script>

{{-- ฟอร์มสำหรับปุ่ม "ใส่ข้อมูลพื้นที่ให้เลย" ในกล่องเตือน (แยกออกมาเพราะซ้อน <form> ไม่ได้) --}}
<form id="seedAreasForm" method="POST" action="{{ route('setup.seed', 'areas') }}">@csrf</form>
