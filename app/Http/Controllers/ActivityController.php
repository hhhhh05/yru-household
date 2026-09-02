<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Program;
use App\Repositories\ActivityRepository;
use App\Repositories\EnrollmentRepository;
use App\Support\Thai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * โครงการ / กิจกรรม — CRUD จริงบนฐานข้อมูล
 */
class ActivityController extends Controller
{
    public function __construct(
        private ActivityRepository $activities,
        private EnrollmentRepository $enrollments,
    ) {}

    public function index(Request $request)
    {
        return view('activities.index', $this->pageData($request));
    }

    public function create(Request $request)
    {
        return view('activities.index', $this->pageData($request) + [
            'drawer' => 'activities.form',
            'activity' => null,
        ]);
    }

    public function edit(Request $request, string $pa)
    {
        $activity = $this->activities->find($pa);
        abort_if(! $activity, 404);

        return view('activities.index', $this->pageData($request) + [
            'drawer' => 'activities.form',
            'activity' => $activity,
        ]);
    }

    /* ============================================================ เขียน ==== */

    public function store(Request $request)
    {
        $v = $this->validated($request);

        /* ปีงบเป็นของโครงการหลัก — ยึดค่าจากตาราง programs เป็นหลัก */
        $program = $this->resolveProgram($v);
        $fiscalYear = $program->fiscal_year;

        $activity = Activity::create($this->onlyExistingColumns([
            'pa' => Activity::nextPa($fiscalYear, $program->id),
            'program_id' => $program->id,
            'fiscal_year' => $fiscalYear,
            'name' => trim($v['name']),
            'budget' => $v['budget'],
            'target_households' => $v['target'] ?? null,
            'unit' => $v['unit'] ?? null,
            'lecturer_name' => $v['lecturer_name'] ?? null,
            'lecturer_phone' => $v['lecturer_phone'] ?? null,
            'lecturer_id_card' => $v['lecturer_id_card'] ?? null,
            'workload_per_week' => $v['workload'] ?? null,
            'description' => $v['description'] ?? null,
        ]));

        return redirect()
            ->route('activities.index', ['fy' => $fiscalYear])
            ->with('toasts', [[
                'title' => 'เพิ่มกิจกรรมสำเร็จ',
                'msg' => 'ออกรหัส '.$activity->pa.' ให้อัตโนมัติ',
                'kind' => 'ok',
            ]]);
    }

    public function update(Request $request, string $pa)
    {
        $activity = Activity::where('pa', $pa)->first();
        abort_if(! $activity, 404);

        $v = $this->validated($request);

        $program = $this->resolveProgram($v);
        $fiscalYear = $program->fiscal_year;

        $activity->update($this->onlyExistingColumns([
            'program_id' => $program->id,
            'fiscal_year' => $fiscalYear,
            'name' => trim($v['name']),
            'budget' => $v['budget'],
            'target_households' => $v['target'] ?? null,
            'unit' => $v['unit'] ?? null,
            'lecturer_name' => $v['lecturer_name'] ?? null,
            'lecturer_phone' => $v['lecturer_phone'] ?? null,
            'lecturer_id_card' => $v['lecturer_id_card'] ?? null,
            'workload_per_week' => $v['workload'] ?? null,
            'description' => $v['description'] ?? null,
        ]));

        return redirect()
            ->route('activities.index', $request->query())
            ->with('toasts', [[
                'title' => 'บันทึกการแก้ไขแล้ว',
                'msg' => $activity->pa,
                'kind' => 'ok',
            ]]);
    }

    public function destroy(Request $request, string $pa)
    {
        $activity = Activity::where('pa', $pa)->first();
        abort_if(! $activity, 404);

        $enrolled = $activity->enrollments()->count();
        $activity->delete();      // soft delete — รายการลงทะเบียนยังอยู่ กู้คืนได้

        return redirect()
            ->route('activities.index', $request->query())
            ->with('toasts', [[
                'title' => 'ลบกิจกรรมแล้ว',
                'msg' => $activity->pa.($enrolled ? ' (มี '.$enrolled.' ครัวเรือนในกิจกรรมนี้)' : ''),
                'kind' => 'ok',
            ]]);
    }

    /**
     * ตัดคีย์ที่ยังไม่มีคอลัมน์รองรับออก
     * (เผื่อยังไม่ได้กดปุ่ม «อัปเดตฐานข้อมูล» — บันทึกกิจกรรมได้ก่อน
     *  ส่วนข้อมูลอาจารย์/คำอธิบายจะเก็บได้หลังอัปเดตโครงสร้างแล้ว)
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyExistingColumns(array $data): array
    {
        static $columns = null;

        $columns ??= Schema::getColumnListing('activities');

        return array_intersect_key($data, array_flip($columns));
    }

    /**
     * หาโครงการหลักจากตาราง programs ตามที่เลือกในฟอร์ม
     * — ปกติเลือกจาก dropdown (program_id)
     * — ถ้าเลือก «เพิ่มโครงการหลักใหม่» จะสร้างแถวใหม่ของปีงบที่เลือก
     *
     * ปีงบของกิจกรรมยึดตาม programs.fiscal_year ของแถวนี้เสมอ
     *
     * @param  array<string, mixed>  $v
     */
    private function resolveProgram(array $v): Program
    {
        if ($v['program_id'] === '__new') {
            return Program::firstOrCreate([
                'fiscal_year' => (int) $v['fy'],
                'name' => trim((string) $v['program_new']),
            ]);
        }

        return Program::findOrFail((int) $v['program_id']);
    }

    private function validated(Request $request): array
    {
        /* เลือก «เพิ่มปีงบใหม่» → ใช้ค่าจากช่องตัวเลขแทน */
        if ($request->input('fy') === '__new') {
            $request->merge(['fy' => $request->input('fy_new')]);
        }

        $v = $request->validate([
            'fy' => ['required', 'integer', 'min:2560', 'max:2600'],
            'fy_new' => ['nullable', 'integer', 'min:2560', 'max:2600'],
            'program_id' => ['required', 'string'],          // id ของโครงการหลัก หรือ '__new'
            'program_new' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:500'],
            'budget' => ['required', 'numeric', 'min:0'],
            'target' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:150'],
            'lecturer_name' => ['nullable', 'string', 'max:150'],
            'lecturer_phone' => ['nullable', 'string', 'regex:/^\d{3}-\d{3}-\d{4}$|^\d{9,10}$/'],
            'lecturer_id_card' => ['nullable', 'string'],
            'workload' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'กรอกชื่อกิจกรรม',
            'budget.required' => 'กรอกงบประมาณ',
            'lecturer_phone.regex' => 'เบอร์โทรต้องมี 9–10 หลัก',
            'workload.numeric' => 'ภาระงานต้องเป็นตัวเลข เช่น 6 หรือ 7.5',
            'workload.max' => 'ภาระงานต่อสัปดาห์เกิน 168 ชั่วโมงไม่ได้ (1 สัปดาห์มี 168 ชั่วโมง)',
            'fy.required' => 'เลือกปีงบประมาณ (หรือกรอกปีงบใหม่)',
            'fy.integer' => 'ปีงบประมาณต้องเป็นตัวเลข พ.ศ. เช่น 2570',
            'program_id.required' => 'เลือกโครงการหลัก',
        ]);

        /* เลือก «เพิ่มโครงการหลักใหม่» ต้องกรอกชื่อ */
        if ($v['program_id'] === '__new' && trim((string) ($v['program_new'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'program_new' => 'กรอกชื่อโครงการหลักใหม่ของปีงบนี้',
            ]);
        }

        /* เลขประจำตัวประชาชน: ตรวจหลักตรวจสอบก่อนบันทึก */
        $idCard = preg_replace('/\D/', '', (string) ($v['lecturer_id_card'] ?? ''));

        if ($idCard !== '' && ! Thai::isValidCitizenId($idCard)) {
            throw ValidationException::withMessages([
                'lecturer_id_card' => 'เลขประจำตัวประชาชนไม่ถูกต้อง (ต้องเป็นตัวเลข 13 หลักและผ่านการตรวจหลักสุดท้าย)',
            ]);
        }

        $v['lecturer_id_card'] = $idCard ?: null;

        return $v;
    }

    private function pageData(Request $request): array
    {
        $fy = (string) $request->query('fy', '');
        $q = (string) $request->query('q', '');
        $pg = (string) $request->query('pg', '');       // กรองเฉพาะโครงการหลักเดียว
        $pa = (string) $request->query('pa', '');       // กรองเฉพาะกิจกรรมเดียว
        $unit = (string) $request->query('unit', '');   // กรองตามคณะ/หน่วยงานที่รับผิดชอบ
        /* เปลี่ยนปีงบแล้วโครงการหลักที่ค้างอยู่อาจเป็นของปีอื่น → ทิ้งไป
           ไม่งั้นสองตัวกรองขัดกันเองจนตารางว่าง โดยผู้ใช้ไม่รู้สาเหตุ */
        if ($fy && $pg) {
            $stillValid = false;

            foreach ($this->activities->programsByYear()[$fy] ?? [] as $item) {
                if ((string) $item['id'] === $pg) {
                    $stillValid = true;

                    break;
                }
            }

            if (! $stillValid) {
                $pg = '';
            }
        }

        /* กิจกรรมที่ค้างอยู่ต้องเข้ากับตัวกรองอื่นด้วย ไม่งั้นสองตัวกรองจะขัดกันเองจนไม่เหลือรายการ
           โดยผู้ใช้ไม่รู้สาเหตุ (เช่นเลือกกิจกรรมไว้ แล้วไปสลับปีงบ) — ถ้าขัดกันให้ทิ้งกิจกรรมไป */
        if ($pa) {
            $current = $this->activities->find($pa);

            $mismatch = ! $current
                || ($fy && (string) $current['fy'] !== $fy)
                || ($pg && (string) ($current['program_id'] ?? '') !== $pg)
                || ($unit && trim((string) ($current['unit'] ?? '')) !== trim($unit));

            if ($mismatch) {
                $pa = '';
            }
        }

        $rows = $this->activities->filter($fy, $q, $pg, $pa, $unit);

        $counts = [];

        foreach ($this->activities->all() as $activity) {
            $counts[$activity['pa']] = $this->enrollments->countForActivity($activity['pa']);
        }

        /* สรุปตามขอบเขตที่กำลังดู (ตัวกรองปีงบ/คำค้น/โครงการ) ไม่ใช่ทั้งระบบ
           โครงการนับจาก program_id ที่ปรากฏจริงในผลลัพธ์ — กิจกรรมที่ยังไม่ผูกโครงการ (id ว่าง) ไม่นับ
           เพราะยังไม่ใช่โครงการ ถ้านับรวมจะได้ตัวเลขเกินจริง 1 */
        $scopeProgramIds = array_filter(array_unique(array_column($rows, 'program_id')));

        return [
            'navKey' => 'pj',
            'fy' => $fy,
            'q' => $q,
            'pg' => $pg,
            'pa' => $pa,
            'unit' => $unit,
            'units' => $this->activities->units(),
            'scopeCount' => count($rows),
            'scopeBudget' => (int) array_sum(array_column($rows, 'budget')),
            'scopeProgramCount' => count($scopeProgramIds),
            'scopeUnlinkedCount' => count(array_filter($rows, fn ($r) => empty($r['program_id']))),
            'programCount' => $this->activities->programCount(),
            'rows' => $rows,
            'grouped' => $this->activities->groupByYear($rows),
            'tree' => $this->activities->groupByProgram($rows),
            'fiscalYears' => $this->activities->fiscalYears(),
            'programs' => $this->activities->programs(),
            'programsByYear' => $this->activities->programsByYear(),
            'yearOptions' => $this->activities->yearOptions(),
            'counts' => $counts,
            'maxCount' => max(1, max($counts ?: [1])),
            'totalCount' => $this->activities->count(),
            'totalBudget' => $this->activities->totalBudget(),
            'activities' => $this->activities->all(),
        ];
    }
}
