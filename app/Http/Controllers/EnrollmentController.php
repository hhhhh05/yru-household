<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Household;
use App\Repositories\ActivityRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;
use App\Support\Paginate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * รายชื่อเข้าร่วมโครงการ (HC ↔ PA) — CRUD จริงบนฐานข้อมูล
 */
class EnrollmentController extends Controller
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private HouseholdRepository $households,
        private ActivityRepository $activities,
    ) {}

    public function index(Request $request)
    {
        return view('enrollments.index', $this->pageData($request));
    }

    /** เปิดหน้าต่างเลือกครัวเรือนเข้ากิจกรรม */
    public function create(Request $request)
    {
        $data = $this->pageData($request);
        $pa = $data['filters']['pa'];

        if (! $pa) {
            return redirect()->route('enrollments.index')->with('toasts', [[
                'title' => 'เลือกกิจกรรมก่อน',
                'msg' => 'ต้องเลือกกิจกรรมปลายทางก่อนเพิ่มรายชื่อ',
                'kind' => 'warn',
            ]]);
        }

        $activityModel = Activity::where('pa', $pa)->first();
        abort_if(! $activityModel, 404);

        /* กฎ: เพิ่มคนเดิมได้ แต่ห้ามซ้ำ «ชื่อกิจกรรมเดียวกัน + ปีงบเดียวกัน»
           แสดงครัวเรือนทั้งหมด ส่วนคนที่ติดกฎจะติดป้าย «เคยเข้าร่วมแล้ว» และติ๊กไม่ได้ */
        $blocked = $this->blockedByHc($activityModel);

        return view('enrollments.index', $data + [
            'modal' => 'enrollments.picker',
            'activity' => $this->activities->find($pa),
            'candidates' => $this->households->all(),
            'blocked' => $blocked,
        ]);
    }

    /* ============================================================ เขียน ==== */

    /** เพิ่มครัวเรือนที่เลือกเข้ากิจกรรม */
    public function store(Request $request)
    {
        $v = $request->validate([
            'pa' => ['required', 'string'],
            'hcs' => ['required', 'array', 'min:1'],
            'hcs.*' => ['string'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Enrollment::STATUSES)],
        ], [
            'hcs.required' => 'เลือกครัวเรือนอย่างน้อย 1 รายการ',
        ]);

        $activity = Activity::where('pa', $v['pa'])->first();
        abort_if(! $activity, 404);

        $status = $v['status'] ?? 'รอเริ่ม';
        $today = Enrollment::todayBuddhist();
        $added = 0;
        $skipped = [];

        foreach (Household::whereIn('hc', $v['hcs'])->get() as $household) {
            /* กันซ้ำ: ชื่อกิจกรรมเดียวกัน + ปีงบเดียวกัน (รวมกิจกรรมนี้เอง) */
            $conflict = Enrollment::conflictWith($household->id, $activity);

            if ($conflict) {
                $skipped[] = $household->hc.' ('.($conflict->activity->pa ?? '').')';

                continue;
            }

            Enrollment::create([
                'code' => Enrollment::nextCode(),
                'household_id' => $household->id,
                'activity_id' => $activity->id,
                'joined_at' => $today,
                'status' => $status,
            ]);
            $added++;
        }

        $toasts = [[
            'title' => $added ? 'เพิ่มรายชื่อสำเร็จ' : 'ไม่มีรายการที่เพิ่มได้',
            'msg' => $added.' ครัวเรือนเข้าร่วม '.$activity->pa,
            'kind' => $added ? 'ok' : 'warn',
        ]];

        if ($skipped) {
            $toasts[] = [
                'title' => 'ข้าม '.count($skipped).' รายการ (ซ้ำกิจกรรมในปีงบ '.$activity->fiscal_year.')',
                'msg' => implode(' · ', array_slice($skipped, 0, 5))
                    .(count($skipped) > 5 ? ' …' : ''),
                'kind' => 'warn',
            ];
        }

        return redirect()
            ->route('enrollments.index', ['pa' => $v['pa']])
            ->with('toasts', $toasts);
    }

    /** เปลี่ยนสถานะรายการเดียว (จาก dropdown ในตาราง) */
    public function updateStatus(Request $request, string $id)
    {
        $enrollment = Enrollment::with('household')->find($id);
        abort_if(! $enrollment, 404);

        /* «ออกกลางคัน» ต้องระบุเหตุผลเสมอ — ไม่งั้นภายหลังไม่มีใครรู้ว่าออกเพราะอะไร
           ส่วน «สำเร็จ» เปิดช่องรายได้หลังเข้าร่วมให้กรอก แต่ไม่บังคับ
           เพราะบางครั้งยังเก็บตัวเลขไม่ได้ทันทีที่ปิดกิจกรรม */
        $v = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Enrollment::STATUSES)],
            'joined_at' => ['nullable', 'string', 'date_format:Y-m-d'],
            'income_before' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'income_after' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => [
                $request->input('status') === 'ออกกลางคัน' ? 'required' : 'nullable',
                'string', 'max:1000',
            ],
        ], [
            'note.required' => 'ระบุเหตุผลที่ออกกลางคันในช่องหมายเหตุ',
            'joined_at.date_format' => 'วันที่เข้าร่วมต้องอยู่ในรูป ปปปป-ดด-วว (ปี พ.ศ.)',
            'income_before.numeric' => 'รายได้ก่อนเข้าร่วมต้องเป็นตัวเลข',
            'income_after.numeric' => 'รายได้หลังเข้าร่วมต้องเป็นตัวเลข',
        ]);

        $data = ['status' => $v['status']];

        /* บันทึกหมายเหตุเฉพาะเมื่อฟอร์มส่งมาด้วย — การเปลี่ยนสถานะจาก dropdown เฉย ๆ
           ไม่ควรไปล้างหมายเหตุเดิมทิ้ง */
        if ($request->has('note')) {
            $data['note'] = $v['note'] ?? null;
        }

        /* วันที่เก็บเป็น พ.ศ. ในคอลัมน์ DATE — date_format ตรวจได้แค่รูปแบบ
           ต้องเช็คว่าเป็นวันที่มีอยู่จริงด้วย (กัน 2569-02-31) โดยแปลงเป็น ค.ศ. ก่อนเทียบปฏิทิน */
        if ($request->has('joined_at')) {
            $joined = trim((string) ($v['joined_at'] ?? ''));

            if ($joined !== '') {
                [$y, $m, $d] = array_map('intval', explode('-', $joined));

                if ($y < 2400 || $y > 2700 || ! checkdate($m, $d, $y - 543)) {
                    throw ValidationException::withMessages([
                        'joined_at' => 'วันที่เข้าร่วมไม่ถูกต้อง — ปีต้องเป็น พ.ศ. และวันต้องมีอยู่จริง',
                    ]);
                }

                $data['joined_at'] = $joined;
            }
        }

        /* ส่งช่องรายได้ก่อนเข้าร่วมมา = ต้องมีค่าเสมอ ปล่อยว่างไม่ได้
           (การเปลี่ยนสถานะจาก dropdown เฉย ๆ ไม่ได้ส่งช่องนี้มา จึงไม่โดนกฎนี้
            และค่าที่จดไว้เดิมก็ไม่ถูกล้างทิ้ง) */
        if ($request->has('income_before')) {
            if (($v['income_before'] ?? '') === '') {
                throw ValidationException::withMessages([
                    'income_before' => 'ต้องระบุรายได้ก่อนเข้าร่วม — ใส่ 0 ได้ถ้าไม่มีรายได้',
                ]);
            }

            if (Schema::hasColumn('enrollments', 'income_before')) {
                $data['income_before'] = $v['income_before'];
            }
        }

        if ($request->has('income_after') && Schema::hasColumn('enrollments', 'income_after')) {
            $data['income_after'] = ($v['income_after'] ?? '') === '' ? null : $v['income_after'];
        }

        $enrollment->update($data);

        return back()->with('toasts', [[
            'title' => 'อัปเดตสถานะแล้ว',
            'msg' => ($enrollment->household->hc ?? '').' → '.$v['status']
                .(isset($data['income_after']) && $data['income_after'] !== null
                    ? ' · รายได้หลังเข้าร่วม '.number_format((float) $data['income_after']).' บาท'
                    : ''),
            'kind' => 'ok',
        ]]);
    }

    /** นำออกจากกิจกรรม (ลบรายการลงทะเบียน — ข้อมูลครัวเรือนยังอยู่) */
    public function destroy(Request $request, string $id)
    {
        $enrollment = Enrollment::with(['household', 'activity'])->find($id);
        abort_if(! $enrollment, 404);

        $hc = $enrollment->household->hc ?? '';
        $pa = $enrollment->activity->pa ?? '';
        $enrollment->delete();

        return back()->with('toasts', [[
            'title' => 'นำออกจากกิจกรรมแล้ว',
            'msg' => $hc.' ออกจาก '.$pa,
            'kind' => 'ok',
        ]]);
    }

    /** จัดการแบบกลุ่ม: เปลี่ยนสถานะ · ย้าย/คัดลอกไปกิจกรรมอื่น · นำออก */
    public function bulk(Request $request, string $action)
    {
        $ids = array_values(array_filter(explode(',', (string) $request->input('ids'))));

        if (! $ids) {
            return back()->with('toasts', [[
                'title' => 'ยังไม่ได้เลือกรายการ',
                'msg' => 'เลือกอย่างน้อย 1 รายการ',
                'kind' => 'err',
            ]]);
        }

        $enrollments = Enrollment::whereIn('id', $ids)->get();

        $result = match ($action) {
            'status' => $this->bulkStatus($request, $enrollments),
            'move' => $this->bulkMove($request, $enrollments),
            'remove' => $this->bulkRemove($enrollments),
            default => ['title' => 'ไม่รู้จักคำสั่งนี้', 'msg' => $action, 'kind' => 'err'],
        };

        return back()->with('toasts', [$result]);
    }

    private function bulkStatus(Request $request, $enrollments): array
    {
        $status = $request->input('status');

        if (! in_array($status, Enrollment::STATUSES, true)) {
            return ['title' => 'สถานะไม่ถูกต้อง', 'msg' => (string) $status, 'kind' => 'err'];
        }

        $count = 0;

        foreach ($enrollments as $enrollment) {
            $enrollment->update(['status' => $status]);
            $count++;
        }

        return ['title' => 'เปลี่ยนสถานะแล้ว', 'msg' => $count.' รายการ → '.$status, 'kind' => 'ok'];
    }

    private function bulkMove(Request $request, $enrollments): array
    {
        $activity = Activity::where('pa', $request->input('pa'))->first();

        if (! $activity) {
            return ['title' => 'ไม่พบกิจกรรมปลายทาง', 'msg' => (string) $request->input('pa'), 'kind' => 'err'];
        }

        $copy = $request->input('mode') === 'copy';
        $moved = 0;
        $skipped = 0;

        DB::transaction(function () use ($enrollments, $activity, $copy, &$moved, &$skipped) {
            foreach ($enrollments as $enrollment) {
                if ($enrollment->activity_id === $activity->id) {
                    $skipped++;

                    continue;
                }

                /* กันซ้ำ: ชื่อกิจกรรมเดียวกัน + ปีงบเดียวกันที่ปลายทาง */
                $conflict = Enrollment::conflictWith($enrollment->household_id, $activity);

                if ($conflict && $conflict->id !== $enrollment->id) {
                    $skipped++;

                    /* ย้าย (ไม่ใช่คัดลอก) แล้วปลายทางมีอยู่แล้ว → ลบรายการต้นทางทิ้ง */
                    if (! $copy) {
                        $enrollment->delete();
                    }

                    continue;
                }

                if ($copy) {
                    Enrollment::create([
                        'code' => Enrollment::nextCode(),
                        'household_id' => $enrollment->household_id,
                        'activity_id' => $activity->id,
                        'joined_at' => $enrollment->getRawOriginal('joined_at'),
                        'status' => $enrollment->status,
                        'note' => $enrollment->note,
                    ]);
                } else {
                    $enrollment->update(['activity_id' => $activity->id]);
                }

                $moved++;
            }
        });

        return [
            'title' => $copy ? 'คัดลอกรายการแล้ว' : 'ย้ายรายการแล้ว',
            'msg' => $moved.' รายการ → '.$activity->pa.($skipped ? " (ข้าม $skipped ที่มีอยู่แล้ว)" : ''),
            'kind' => 'ok',
        ];
    }

    private function bulkRemove($enrollments): array
    {
        $count = 0;

        foreach ($enrollments as $enrollment) {
            $enrollment->delete();
            $count++;
        }

        return [
            'title' => 'นำออกจากกิจกรรมแล้ว',
            'msg' => $count.' รายการ (ข้อมูลครัวเรือนในทะเบียนยังอยู่ครบ)',
            'kind' => 'ok',
        ];
    }

    /* ============================================================ อ่าน ==== */

    /**
     * ครัวเรือนที่ติดกฎ «ห้ามซ้ำกิจกรรมชื่อเดียวกันในปีงบเดียวกัน» — คีย์เป็นรหัส HC
     *
     * @return array<string, string> [hc => รหัส PA ที่เคยเข้าร่วม]
     */
    private function blockedByHc(Activity $activity): array
    {
        $byId = Enrollment::blockedHouseholdIds($activity);

        if (! $byId) {
            return [];
        }

        $out = [];

        foreach (Household::whereIn('id', array_keys($byId))->pluck('hc', 'id') as $id => $hc) {
            $out[$hc] = $byId[$id];
        }

        return $out;
    }

    /**
     * ครัวเรือนที่มีการลงทะเบียนอย่างน้อย 1 รายการ (ไม่ซ้ำ)
     *
     * ใช้ทำตัวเลือกช่องกรองพื้นที่ — จำกัดได้ตามโครงการหลัก/กิจกรรม (ขั้นบนของตัวกรอง)
     * แต่จงใจไม่คิดตามตัวกรองพื้นที่เอง ไม่งั้นพอเลือกจังหวัดแล้วจังหวัดอื่นจะหายจนเปลี่ยนกลับไม่ได้
     *
     * @return array<int, array<string, mixed>>
     */
    private function enrolledHouseholds(string $programId = '', string $pa = ''): array
    {
        $out = [];

        foreach ($this->enrollments->all() as $enrollment) {
            if ($pa !== '' && $enrollment['pa'] !== $pa) {
                continue;
            }

            if ($programId !== '') {
                $activity = $this->activities->find($enrollment['pa']);

                if ((string) ($activity['program_id'] ?? '') !== $programId) {
                    continue;
                }
            }

            if (! isset($out[$enrollment['hc']]) && $household = $this->households->find($enrollment['hc'])) {
                $out[$enrollment['hc']] = $household;
            }
        }

        return array_values($out);
    }

    private function pageData(Request $request): array
    {
        $filters = [
            'pg' => (string) $request->query('pg', ''),      // โครงการหลัก
            'pa' => (string) $request->query('pa', 'LP69002'),
            'q' => (string) $request->query('q', ''),
            'st' => (string) $request->query('st', ''),
            'vill' => (string) $request->query('vill', ''),
            'prov' => (string) $request->query('prov', ''),
            'dist' => (string) $request->query('dist', ''),
            'tam' => (string) $request->query('tam', ''),
            'sort' => (string) $request->query('sort', 'hc'),
            'dir' => (int) $request->query('dir', 1),
        ];

        /* ถ้ารหัสกิจกรรมไม่มีอยู่จริง ให้กลับไปแสดงทุกกิจกรรม */
        if ($filters['pa'] && ! $this->activities->find($filters['pa'])) {
            $filters['pa'] = '';
        }

        /* เลือกโครงการหลักแล้ว แต่กิจกรรมที่ค้างอยู่ไม่ได้อยู่ในโครงการนั้น → ล้างกิจกรรมทิ้ง
           ไม่งั้นสองตัวกรองจะขัดกันเองจนไม่เหลือรายการ โดยผู้ใช้ไม่รู้สาเหตุ */
        if ($filters['pg'] && $filters['pa']) {
            $current = $this->activities->find($filters['pa']);

            if ((string) ($current['program_id'] ?? '') !== $filters['pg']) {
                $filters['pa'] = '';
            }
        }

        $activity = $filters['pa'] ? $this->activities->find($filters['pa']) : null;

        /* ---------------------------------------------- ตัวกรองแบบเป็นขั้น ----
           ลำดับขั้น: โครงการหลัก → กิจกรรม → จังหวัด → อำเภอ → ตำบล
           ตัวเลือกพื้นที่จึงคิดจาก «ครัวเรือนที่อยู่ในโครงการ/กิจกรรมที่เลือกไว้» เท่านั้น
           ไม่ใช่ทั้งระบบ — เลือกกิจกรรมแล้วจะเห็นเฉพาะพื้นที่ที่กิจกรรมนั้นลงจริง

           จงใจไม่เอาตัวกรองพื้นที่มาคิดตัวเลือกของตัวเอง ไม่งั้นพอเลือกจังหวัดแล้ว
           จังหวัดอื่นจะหายไปจนเปลี่ยนกลับไม่ได้ */
        $areaPaths = $this->households->areaOptions(
            $this->enrolledHouseholds($filters['pg'], $filters['pa'])
        );

        $matchesArea = fn (string $prov, string $dist, string $tam) => (bool) array_filter(
            $areaPaths,
            fn ($a) => ($prov === '' || $a['prov'] === $prov)
                && ($dist === '' || $a['dist'] === $dist)
                && ($tam === '' || $a['tam'] === $tam),
        );

        /* ล้างจากขั้นล่างขึ้นบน — ค่าที่ค้างอยู่แต่ไม่มีในขั้นบนที่เลือกใหม่ ต้องหลุดไป
           ไม่งั้นตัวกรองขัดกันเองจนไม่เหลือรายชื่อ โดยผู้ใช้ไม่รู้สาเหตุ */
        if ($filters['tam'] !== '' && ! $matchesArea($filters['prov'], $filters['dist'], $filters['tam'])) {
            $filters['tam'] = '';
        }

        if ($filters['dist'] !== '' && ! $matchesArea($filters['prov'], $filters['dist'], '')) {
            $filters['dist'] = '';
        }

        if ($filters['prov'] !== '' && ! $matchesArea($filters['prov'], '', '')) {
            $filters['prov'] = '';
        }


        /* ตัวเลือกหมู่บ้าน = ขั้นสุดท้ายของสายตัวกรอง (โครงการ → กิจกรรม → จังหวัด → อำเภอ → ตำบล → หมู่บ้าน)
           คิดจากขั้นบนเท่านั้น ไม่เอาหมู่บ้านที่เลือกอยู่มาคิดด้วย ไม่งั้นเหลือตัวเลือกเดียวจนสลับหมู่บ้านไม่ได้ */
        $villages = [];

        foreach ($this->enrolledHouseholds($filters['pg'], $filters['pa']) as $household) {
            if ($filters['prov'] !== '' && trim((string) $household['prov']) !== $filters['prov']) {
                continue;
            }

            if ($filters['dist'] !== '' && trim((string) $household['dist']) !== $filters['dist']) {
                continue;
            }

            if ($filters['tam'] !== '' && trim((string) $household['tam']) !== $filters['tam']) {
                continue;
            }

            if ($household['vill'] !== '') {
                $villages[$household['vill']] = true;
            }
        }

        ksort($villages);

        /* หมู่บ้านที่ค้างอยู่ไม่มีในขั้นบนที่เลือกใหม่ → ล้างทิ้ง (ขั้นสุดท้ายของสาย) */
        if ($filters['vill'] !== '' && ! isset($villages[$filters['vill']])) {
            $filters['vill'] = '';
        }

        $rows = $this->enrollments->rows($filters);
        $page = Paginate::make($rows, (int) $request->query('page', 1), (int) $request->query('per', 25));

        /* ขอบเขตข้อมูลสำหรับการ์ดสรุป = ผลลัพธ์หลังกรองทั้งหมด (ก่อนแบ่งหน้า)
           ใช้ $rows ตัวเดียวกับตาราง ตัวเลขในการ์ดจึงตรงกับที่เห็นในตารางเสมอ
           ไม่ใช้ทั้งระบบหรือทั้งกิจกรรม ไม่งั้นกรองพื้นที่แล้วการ์ดจะไม่ขยับ */
        $scope = $rows;

        /* กรองให้แคบกว่า «ทั้งกิจกรรม» หรือยัง — ใช้ตัดสินว่าจะโชว์งบเฉลี่ย/ครัวเรือนได้ไหม
           งบเป็นของทั้งกิจกรรม ถ้าเอามาหารเฉพาะคนที่กรองเหลือ ตัวเลขจะเกินจริง */
        $narrowed = $filters['q'] !== '' || $filters['st'] !== '' || $filters['vill'] !== ''
            || $filters['prov'] !== '' || $filters['dist'] !== '' || $filters['tam'] !== '';

        $incomes = [];
        $incomesAfter = [];
        $incomePairs = [];        // เฉพาะรายที่มีทั้งรายได้ตั้งต้นและหลังจบ — ใช้คิดส่วนต่าง


        /* ครัวเรือนที่อยู่ในขอบเขตที่กำลังดู — ใช้นับพื้นที่ครอบคลุม
           เก็บทีละรายซ้ำไม่ได้ เพราะครัวเรือนเดียวเข้าได้หลายกิจกรรม จึงคีย์ด้วย HC */
        $scopeHouseholds = [];

        foreach ($scope as $enrollment) {
            $household = $this->households->find($enrollment['hc']);
            $after = $enrollment['income_after'] ?? null;

            if ($after !== null) {
                $incomesAfter[] = $after;
            }

            /* รายได้ก่อนเข้าร่วม ใช้ค่าที่จดไว้กับรายการนี้ ไม่ใช่รายได้ปัจจุบันของครัวเรือน
               (แถวเก่าที่ยังไม่มีค่าจะถอยไปอ่านจากครัวเรือนให้เอง) */
            $before = EnrollmentRepository::incomeBeforeOf($enrollment, $this->households);

            if ($before !== null) {
                $incomes[] = $before;

                /* ส่วนต่างคิดได้เฉพาะรายที่มีตัวเลขครบทั้งสองฝั่ง
                   ถ้าเอาค่าเฉลี่ยสองชุดมาลบกันตรง ๆ จะเพี้ยน เพราะคนละกลุ่มตัวอย่าง */
                if ($after !== null) {
                    $incomePairs[] = $after - $before;
                }
            }

            if ($household) {
                $scopeHouseholds[$household['hc']] = $household;
            }
        }

        $areaCounts = $this->households->areaCounts(array_values($scopeHouseholds));

        $counts = [];

        foreach ($this->activities->all() as $item) {
            $counts[$item['pa']] = $this->enrollments->countForActivity($item['pa']);
        }

        return [
            'navKey' => 'en',
            'filters' => $filters,
            'activity' => $activity,
            'page' => $page,
            'scope' => $scope,
            'narrowed' => $narrowed,
            'scopeUniqueHouseholds' => count(array_unique(array_column($scope, 'hc'))),
            'scopeActivityCount' => count(array_unique(array_column($scope, 'pa'))),
            'statusCounts' => $this->enrollments->statusCounts($scope),
            'incomes' => $incomes,
            'incomesAfter' => $incomesAfter,
            'incomePairs' => $incomePairs,
            'villages' => array_keys($villages),
            'areaCounts' => $areaCounts,
            /* ตัวเลือกช่องกรองพื้นที่ — เอาจากครัวเรือนที่มีการลงทะเบียนจริงเท่านั้น
               ไม่ใช่ทั้งทะเบียน จะได้ไม่มีตัวเลือกที่เลือกแล้วไม่เหลือรายชื่อ */
            'areaOptions' => $areaPaths,
            'statuses' => EnrollmentRepository::STATUSES,
            'statusClass' => EnrollmentRepository::STATUS_CLASS,
            'activities' => $this->activities->all(),
            'programsByYear' => $this->activities->programsByYear(),
            'fiscalYears' => $this->activities->fiscalYears(),
            'counts' => $counts,
            'totalEnrollments' => count($this->enrollments->all()),
            'totalActivities' => $this->activities->count(),
            'activeActivityCount' => $this->enrollments->activeActivityCount(),
            'totalHouseholds' => $this->households->count(),
        ];
    }
}
