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
            'income_after' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => [
                $request->input('status') === 'ออกกลางคัน' ? 'required' : 'nullable',
                'string', 'max:1000',
            ],
        ], [
            'note.required' => 'ระบุเหตุผลที่ออกกลางคันในช่องหมายเหตุ',
            'income_after.numeric' => 'รายได้หลังเข้าร่วมต้องเป็นตัวเลข',
        ]);

        $data = ['status' => $v['status']];

        /* บันทึกหมายเหตุเฉพาะเมื่อฟอร์มส่งมาด้วย — การเปลี่ยนสถานะจาก dropdown เฉย ๆ
           ไม่ควรไปล้างหมายเหตุเดิมทิ้ง */
        if ($request->has('note')) {
            $data['note'] = $v['note'] ?? null;
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

    private function pageData(Request $request): array
    {
        $filters = [
            'pg' => (string) $request->query('pg', ''),      // โครงการหลัก
            'pa' => (string) $request->query('pa', 'LP69002'),
            'q' => (string) $request->query('q', ''),
            'st' => (string) $request->query('st', ''),
            'vill' => (string) $request->query('vill', ''),
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

        $rows = $this->enrollments->rows($filters);
        $page = Paginate::make($rows, (int) $request->query('page', 1), (int) $request->query('per', 25));

        /* ขอบเขตข้อมูลสำหรับการ์ดสรุป: เฉพาะกิจกรรมที่เลือก (หรือทั้งระบบ) */
        $scope = $filters['pa']
            ? $this->enrollments->forActivity($filters['pa'])
            : $this->enrollments->all();

        $incomes = [];
        $incomesAfter = [];
        $incomePairs = [];        // เฉพาะรายที่มีทั้งรายได้ตั้งต้นและหลังจบ — ใช้คิดส่วนต่าง
        $villages = [];

        foreach ($scope as $enrollment) {
            $household = $this->households->find($enrollment['hc']);
            $after = $enrollment['income_after'] ?? null;

            if ($after !== null) {
                $incomesAfter[] = $after;
            }

            if ($household) {
                if ($household['income'] !== null) {
                    $incomes[] = $household['income'];

                    /* ส่วนต่างคิดได้เฉพาะรายที่มีตัวเลขครบทั้งสองฝั่ง
                       ถ้าเอาค่าเฉลี่ยสองชุดมาลบกันตรง ๆ จะเพี้ยน เพราะคนละกลุ่มตัวอย่าง */
                    if ($after !== null) {
                        $incomePairs[] = $after - $household['income'];
                    }
                }

                if ($household['vill'] !== '') {
                    $villages[$household['vill']] = true;
                }
            }
        }

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
            'scopeUniqueHouseholds' => count(array_unique(array_column($scope, 'hc'))),
            'scopeActivityCount' => count(array_unique(array_column($scope, 'pa'))),
            'statusCounts' => $this->enrollments->statusCounts($scope),
            'incomes' => $incomes,
            'incomesAfter' => $incomesAfter,
            'incomePairs' => $incomePairs,
            'villages' => array_keys($villages),
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
