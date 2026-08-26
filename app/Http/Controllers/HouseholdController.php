<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Household;
use App\Repositories\ActivityRepository;
use App\Repositories\AreaRepository;
use App\Repositories\DataQualityAnalyzer;
use App\Repositories\EnrollmentRepository;
use App\Repositories\HouseholdRepository;
use App\Support\Paginate;
use App\Support\Thai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ทะเบียนครัวเรือน — CRUD จริงบนฐานข้อมูล
 */
class HouseholdController extends Controller
{
    public function __construct(
        private HouseholdRepository $households,
        private EnrollmentRepository $enrollments,
        private ActivityRepository $activities,
        private AreaRepository $areas,
        private DataQualityAnalyzer $quality,
    ) {}

    public function index(Request $request)
    {
        return view('households.index', $this->pageData($request));
    }

    public function create(Request $request)
    {
        return view('households.index', $this->pageData($request) + [
            'drawer' => 'households.form',
            'household' => null,
        ]);
    }

    public function show(Request $request, string $hc)
    {
        $household = $this->households->find($hc);
        abort_if(! $household, 404);

        $rows = [];

        foreach ($this->enrollments->forHousehold($hc) as $enrollment) {
            $enrollment['p'] = $this->activities->find($enrollment['pa']);
            $rows[] = $enrollment;
        }

        return view('households.index', $this->pageData($request) + [
            'drawer' => 'households.show',
            'household' => $household,
            'householdEnrollments' => $rows,
            'householdIssues' => $this->quality->forHousehold($hc),
        ]);
    }

    public function edit(Request $request, string $hc)
    {
        $household = $this->households->find($hc);
        abort_if(! $household, 404);

        return view('households.index', $this->pageData($request) + [
            'drawer' => 'households.form',
            'household' => $household,
        ]);
    }

    /* ============================================================ เขียน ==== */

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $areaCode = $data['area_code'];
        unset($data['area_code']);

        /* ชื่อนี้มีอยู่ในทะเบียนแล้วหรือยัง
           ถ้ามี จะไม่สร้างครัวเรือนซ้ำ — บันทึกแค่การเข้าร่วมกิจกรรมให้คนเดิม
           และไม่ไปแก้ที่อยู่/เบอร์/รายได้ของเขาด้วย เพราะหน้านี้คือหน้า «เพิ่ม» ไม่ใช่ «แก้ไข» */
        if ($existing = $this->findExistingHousehold($request, $data['full_name'])) {
            $enrollToast = $this->enrollFromForm($request, $existing);

            $toasts = [[
                'title' => 'ใช้ครัวเรือนเดิม ไม่ได้สร้างรายการใหม่',
                'msg' => $existing->fullName().' · '.$existing->hc
                    .' มีอยู่ในทะเบียนแล้ว'.($enrollToast ? '' : ' — และยังไม่ได้เลือกกิจกรรม จึงไม่มีอะไรถูกบันทึก'),
                'kind' => $enrollToast ? 'ok' : 'warn',
            ]];

            if ($enrollToast) {
                $toasts[] = $enrollToast;
            }

            return redirect()
                ->route('households.index', ['q' => $existing->hc])
                ->with('toasts', $toasts);
        }

        $data['hc'] = Household::nextHc($areaCode);
        $household = Household::create($data);

        $toasts = [[
            'title' => 'เพิ่มครัวเรือนสำเร็จ',
            'msg' => 'ออกรหัส '.$household->hc.' ให้อัตโนมัติ',
            'kind' => 'ok',
        ]];

        if ($enrollToast = $this->enrollFromForm($request, $household)) {
            $toasts[] = $enrollToast;
        }

        return redirect()
            ->route('households.index', ['sort' => 'hc', 'dir' => -1])
            ->with('toasts', $toasts);
    }

    /**
     * หาครัวเรือนเดิมที่ตรงกับชื่อที่กรอกมา
     *
     * ลำดับการตัดสิน
     *   1. ผู้ใช้กดปุ่มเลือกครัวเรือนจากกล่อง «พบชื่อนี้ในฐานข้อมูลแล้ว» → ใช้รหัสนั้นตรง ๆ
     *   2. ไม่ได้กด แต่ชื่อไปตรงกับครัวเรือนเดียวพอดี → ใช้ครัวเรือนนั้น
     *   3. ชื่อซ้ำกันหลายครัวเรือน → ไม่เดาให้ ให้ผู้ใช้ระบุเองว่าหลังไหน
     *
     * เทียบชื่อแบบตัดช่องว่างออก เพราะคนกรอกเว้นวรรคไม่เท่ากัน
     */
    private function findExistingHousehold(Request $request, string $fullName): ?Household
    {
        $hc = trim((string) $request->input('existing_hc'));

        if ($hc !== '') {
            return Household::where('hc', $hc)->first();
        }

        $key = preg_replace('/\s+/u', '', $fullName);

        if ($key === '') {
            return null;
        }

        $matches = Household::whereRaw("REPLACE(full_name, ' ', '') = ?", [$key])->get();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'fullname' => 'ชื่อนี้มีอยู่ '.$matches->count().' ครัวเรือน ('
                    .$matches->pluck('hc')->implode(' · ')
                    .') — กดปุ่มเลือกครัวเรือนในกล่องด้านบนก่อน ระบบจะได้รู้ว่าหมายถึงหลังไหน',
            ]);
        }

        return $matches->first();
    }

    /**
     * เพิ่มครัวเรือนเข้ากิจกรรมตามที่เลือกไว้ในหัวข้อ «เข้าร่วมโครงการ/กิจกรรม» ของฟอร์ม
     *
     * เว้นว่างได้ — ถ้าไม่เลือกกิจกรรมจะไม่ทำอะไรเลย
     * ใช้กฎกันซ้ำชุดเดียวกับหน้ารายชื่อเข้าร่วม (ชื่อกิจกรรมเดียวกัน + ปีงบเดียวกัน)
     *
     * @return array<string, string>|null toast ที่จะแสดงต่อท้าย หรือ null ถ้าไม่ได้ทำอะไร
     */
    private function enrollFromForm(Request $request, Household $household): ?array
    {
        $pa = trim((string) $request->input('en_pa'));

        if ($pa === '') {
            return null;
        }

        $activity = Activity::where('pa', $pa)->first();

        if (! $activity) {
            return ['title' => 'ไม่พบกิจกรรมที่เลือก', 'msg' => $pa, 'kind' => 'err'];
        }

        if ($conflict = Enrollment::conflictWith($household->id, $activity)) {
            return [
                'title' => 'ไม่ได้เพิ่มเข้ากิจกรรม',
                'msg' => 'เคยเข้าร่วมกิจกรรมชื่อนี้ในปีงบ '.$activity->fiscal_year
                    .' แล้ว ('.($conflict->activity->pa ?? '').')',
                'kind' => 'warn',
            ];
        }

        $status = in_array($request->input('en_status'), Enrollment::STATUSES, true)
            ? $request->input('en_status')
            : 'รอเริ่ม';

        Enrollment::create([
            'code' => Enrollment::nextCode(),
            'household_id' => $household->id,
            'activity_id' => $activity->id,
            'joined_at' => Enrollment::todayBuddhist(),
            'status' => $status,
        ]);

        return [
            'title' => 'เพิ่มเข้ากิจกรรมแล้ว',
            'msg' => $activity->pa.' · ปีงบ '.$activity->fiscal_year.' · '.$status,
            'kind' => 'ok',
        ];
    }

    public function update(Request $request, string $hc)
    {
        $household = $this->households->model($hc);
        abort_if(! $household, 404);

        $data = $this->validated($request);
        unset($data['area_code']);

        $household->update($data);

        /* หน้าแก้ไขไม่มีหัวข้อ «เข้าร่วมโครงการ/กิจกรรม» จึงไม่รับค่าเหล่านั้นที่นี่
           การเพิ่ม/ถอนกิจกรรมของครัวเรือนที่มีอยู่แล้ว ทำที่หน้ารายชื่อเข้าร่วม */
        return redirect()
            ->route('households.index', $request->query())
            ->with('toasts', [[
                'title' => 'บันทึกการแก้ไขแล้ว',
                'msg' => $household->fullName().' · '.$household->hc,
                'kind' => 'ok',
            ]]);
    }

    public function destroy(Request $request, string $hc)
    {
        $household = $this->households->model($hc);
        abort_if(! $household, 404);

        $name = $household->fullName();
        $enrollmentCount = $household->enrollments()->count();

        /* ครัวเรือนที่เข้าร่วมโครงการแล้ว ห้ามลบ
           เพราะรายการลงทะเบียนเป็นหลักฐานการดำเนินงานตามปีงบ
           ต้องถอนออกจากกิจกรรมให้หมดก่อน จึงจะลบทะเบียนได้ */
        if ($enrollmentCount > 0) {
            return redirect()
                ->route('households.index', $request->query())
                ->with('toasts', [[
                    'title' => 'ลบไม่ได้ — ครัวเรือนนี้เข้าร่วมโครงการอยู่',
                    'msg' => $name.' อยู่ใน '.$enrollmentCount.' กิจกรรม '
                        .'· ถอนออกจากกิจกรรมให้ครบก่อนที่หน้า «รายชื่อเข้าร่วมโครงการ»',
                    'kind' => 'err',
                ]]);
        }

        $household->delete();      // soft delete — กู้คืนได้

        return redirect()
            ->route('households.index', $request->query())
            ->with('toasts', [[
                'title' => 'ลบครัวเรือนแล้ว',
                'msg' => $name,
                'kind' => 'ok',
            ]]);
    }

    /** จัดการแบบกลุ่ม: เพิ่มเข้ากิจกรรม · แก้พื้นที่ · ลบ · รวมรายการซ้ำ */
    public function bulk(Request $request, string $action)
    {
        $hcs = array_values(array_filter(explode(',', (string) $request->input('hcs'))));

        if (! $hcs) {
            return back()->with('toasts', [[
                'title' => 'ยังไม่ได้เลือกครัวเรือน',
                'msg' => 'เลือกอย่างน้อย 1 รายการ',
                'kind' => 'err',
            ]]);
        }

        $households = Household::whereIn('hc', $hcs)->get();

        $result = match ($action) {
            'enroll' => $this->bulkEnroll($request, $households),
            'area' => $this->bulkArea($request, $households),
            'delete' => $this->bulkDelete($households),
            'merge' => $this->bulkMerge($households),
            'fix-district' => [
                'title' => 'ไม่ต้องแก้แล้ว',
                'msg' => 'โครงสร้างฐานข้อมูลใหม่อ้างอำเภอจากตำบลโดยตรง จึงไม่มีปัญหาอำเภอไม่ตรงกับตำบลอีก',
                'kind' => 'warn',
            ],
            default => ['title' => 'ไม่รู้จักคำสั่งนี้', 'msg' => $action, 'kind' => 'err'],
        };

        return back()->with('toasts', [$result]);
    }

    /** เพิ่มครัวเรือนที่เลือกเข้ากิจกรรม */
    private function bulkEnroll(Request $request, $households): array
    {
        $activity = Activity::where('pa', $request->input('pa'))->first();

        if (! $activity) {
            return ['title' => 'ไม่พบกิจกรรม', 'msg' => (string) $request->input('pa'), 'kind' => 'err'];
        }

        $status = in_array($request->input('status'), Enrollment::STATUSES, true)
            ? $request->input('status')
            : 'รอเริ่ม';

        $added = 0;
        $skipped = 0;

        foreach ($households as $household) {
            /* กันซ้ำ: ชื่อกิจกรรมเดียวกัน + ปีงบเดียวกัน */
            if (Enrollment::conflictWith($household->id, $activity)) {
                $skipped++;

                continue;
            }

            Enrollment::create([
                'code' => Enrollment::nextCode(),
                'household_id' => $household->id,
                'activity_id' => $activity->id,
                'joined_at' => Enrollment::todayBuddhist(),
                'status' => $status,
            ]);
            $added++;
        }

        return [
            'title' => $added ? 'เพิ่มเข้ากิจกรรมแล้ว' : 'ไม่มีรายการที่เพิ่มได้',
            'msg' => $added.' ครัวเรือน → '.$activity->pa
                .($skipped ? " (ข้าม $skipped — ซ้ำกิจกรรมในปีงบ ".$activity->fiscal_year.')' : ''),
            'kind' => $added ? 'ok' : 'warn',
        ];
    }

    /** แก้ตำบล/อำเภอของหลายครัวเรือนพร้อมกัน */
    private function bulkArea(Request $request, $households): array
    {
        $tambonId = $this->areas->idFromLabel($request->input('tam'));

        if (! $tambonId) {
            return ['title' => 'ยังไม่ได้เลือกตำบล', 'msg' => 'เลือกตำบลปลายทางก่อน', 'kind' => 'warn'];
        }

        $count = 0;

        foreach ($households as $household) {
            /* ชื่อหมู่บ้านติดไปกับแถวอยู่แล้ว เปลี่ยนแค่ตำบล
               แล้วจดชื่อเดิมเข้าคลังคำแนะนำของตำบลปลายทาง */
            Household::rememberVillage($tambonId, $household->village_name, $household->moo);
            $household->update(['tambon_id' => $tambonId]);
            $count++;
        }

        return ['title' => 'แก้พื้นที่เรียบร้อย', 'msg' => 'ปรับปรุง '.$count.' ครัวเรือน', 'kind' => 'ok'];
    }

    private function bulkDelete($households): array
    {
        $count = 0;
        $blocked = [];

        foreach ($households as $household) {
            /* กฎเดียวกับการลบทีละรายการ — เข้าร่วมโครงการแล้วลบไม่ได้ */
            if ($household->enrollments()->exists()) {
                $blocked[] = $household->hc;

                continue;
            }

            $household->delete();
            $count++;
        }

        if (! $count) {
            return [
                'title' => 'ลบไม่ได้ทั้งหมด',
                'msg' => count($blocked).' ครัวเรือนเข้าร่วมโครงการอยู่ — ถอนออกจากกิจกรรมก่อน',
                'kind' => 'err',
            ];
        }

        return [
            'title' => 'ลบเรียบร้อย',
            'msg' => $count.' ครัวเรือน'
                .($blocked
                    ? ' · ข้าม '.count($blocked).' ที่เข้าร่วมโครงการอยู่ ('
                        .implode(' ', array_slice($blocked, 0, 5)).(count($blocked) > 5 ? ' …' : '').')'
                    : ''),
            'kind' => $blocked ? 'warn' : 'ok',
        ];
    }

    /** รวมครัวเรือนซ้ำ: เก็บรายการแรกเป็นหลัก ย้ายกิจกรรมมารวม แล้วลบที่เหลือ */
    private function bulkMerge($households): array
    {
        if (count($households) < 2) {
            return ['title' => 'ต้องเลือกอย่างน้อย 2 รายการ', 'msg' => '', 'kind' => 'warn'];
        }

        $main = $households->first();
        $merged = 0;

        DB::transaction(function () use ($households, $main, &$merged) {
            foreach ($households as $household) {
                if ($household->id === $main->id) {
                    continue;
                }

                /* เก็บค่าที่รายการหลักยังว่างไว้ */
                foreach (['phone', 'income_bl', 'tambon_id', 'village_name', 'moo', 'lat', 'lng'] as $field) {
                    if (empty($main->{$field}) && ! empty($household->{$field})) {
                        $main->{$field} = $household->{$field};
                    }
                }

                /* ย้ายรายการลงทะเบียนที่ยังไม่ซ้ำมาไว้ที่รายการหลัก */
                foreach ($household->enrollments as $enrollment) {
                    $exists = Enrollment::where('household_id', $main->id)
                        ->where('activity_id', $enrollment->activity_id)
                        ->exists();

                    $exists
                        ? $enrollment->delete()
                        : $enrollment->update(['household_id' => $main->id]);
                }

                $household->delete();
                $merged++;
            }

            $main->save();
        });

        return [
            'title' => 'รวมรายการสำเร็จ',
            'msg' => 'เหลือ '.$main->hc.' เป็นรายการหลัก (รวมมา '.$merged.' รายการ)',
            'kind' => 'ok',
        ];
    }

    /* ============================================================ ตรวจ ==== */

    /**
     * ขยายคำนำหน้าแบบย่อที่คนกรอกคุ้นมือ ให้เป็นรูปเต็มที่ระบบรู้จัก
     * เช่น «ด.ญ.นูรีซัน อาแว» → «เด็กหญิงนูรีซัน อาแว»
     * ทำเฉพาะตอนขึ้นต้นข้อความ เพื่อไม่ให้ไปแตะชื่อหรือนามสกุลกลางประโยค
     */
    private function expandPrefixAbbreviation(string $name): string
    {
        $map = [
            'ด.ช.' => 'เด็กชาย',
            'ด.ญ.' => 'เด็กหญิง',
            'น.ส.' => 'นางสาว',
            'นส.' => 'นางสาว',
        ];

        foreach ($map as $short => $full) {
            if (str_starts_with($name, $short)) {
                return $full.ltrim(mb_substr($name, mb_strlen($short)));
            }
        }

        return $name;
    }

    /**
     * ตรวจข้อมูลฟอร์มและแปลงเป็นฟิลด์ของตาราง households
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'fullname' => ['required', 'string', 'max:180'],
            'house' => ['required', 'string', 'max:30'],
            'moo' => ['required', 'integer', 'min:1', 'max:30'],
            'vill' => ['required', 'string', 'max:120'],
            'prov' => ['required', 'string'],
            'dist' => ['required', 'string'],
            'tam' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'regex:/^\d{3}-\d{3}-\d{4}$|^\d{10}$/'],
            'income' => ['nullable', 'numeric', 'min:0'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'fullname.required' => 'กรอกคำนำหน้า ชื่อ และนามสกุล',
            'house.required' => 'กรอกบ้านเลขที่',
            'moo.required' => 'กรอกหมู่ที่',
            'vill.required' => 'กรอกชื่อหมู่บ้าน/ชุมชน',
            'dist.required' => 'เลือกอำเภอ',
            'tam.required' => 'เลือกตำบล — จำเป็นสำหรับออกรหัส HC',
            'phone.regex' => 'เบอร์ต้องมี 10 หลัก',
            'lat.numeric' => 'ละติจูดต้องเป็นตัวเลข เช่น 6.512345',
            'lat.between' => 'ละติจูดต้องอยู่ระหว่าง -90 ถึง 90',
            'lng.numeric' => 'ลองจิจูดต้องเป็นตัวเลข เช่น 101.280123',
            'lng.between' => 'ลองจิจูดต้องอยู่ระหว่าง -180 ถึง 180',
        ]);

        /* ช่องเดียว «คำนำหน้า ชื่อ - สกุล» → แยกเก็บลงคอลัมน์เดิม
           dedup() ก่อน จึงซ่อมสระซ้ำที่พบบ่อยให้เอง เช่น «นาางสาว» → «นางสาว»
           splitName() เทียบคำนำหน้าที่ยาวที่สุดก่อน จึงไม่ตัด «นางสาว» เป็น «นาง» */
        $fullName = $this->expandPrefixAbbreviation(Thai::dedup(trim($v['fullname'])));

        [$prefix, $firstName, $lastName] = Thai::splitName($fullName);

        $problems = [];

        if ($prefix === '') {
            $problems[] = 'ต้องขึ้นต้นด้วยคำนำหน้า ('.implode(' / ', Thai::PREFIXES).')';
        }

        if ($firstName === '') {
            $problems[] = 'ต้องมีชื่อ';
        }

        if ($lastName === '') {
            $problems[] = 'ต้องมีนามสกุล — เว้นวรรคระหว่างชื่อกับนามสกุล';
        }

        if ($problems) {
            throw ValidationException::withMessages([
                'fullname' => implode(' · ', $problems),
            ]);
        }

        $tambonId = $this->areas->idFromLabel($v['tam']);
        $moo = (int) $v['moo'];

        /* ชื่อหมู่บ้านเก็บเป็นข้อความบนแถวครัวเรือน — จดเข้าคลังคำแนะนำไว้ด้วย
           เพื่อให้คนกรอกคนถัดไปเลือกจากรายการได้ */
        $villageName = Thai::dedup(trim((string) $v['vill']));
        Household::rememberVillage($tambonId, $villageName, $moo);

        return [
            /* เก็บเป็นคอลัมน์เดียว แต่ประกอบกลับจากส่วนที่แยกได้
               ชื่อในฐานข้อมูลจึงอยู่ในรูปแบบเดียวกันเสมอ — คำนำหน้าติดชื่อ
               เว้นวรรคเดียวก่อนนามสกุล ไม่ว่าผู้ใช้จะพิมพ์มาแบบไหน */
            'full_name' => $prefix.$firstName.' '.$lastName,
            'house_no' => trim($v['house']),
            'tambon_id' => $tambonId,
            'village_name' => $villageName,
            'moo' => $moo ?: null,
            'phone' => trim((string) ($v['phone'] ?? '')) ?: null,
            'income_bl' => ($v['income'] ?? '') === '' || $v['income'] === null ? null : (float) $v['income'],
            'lat' => ($v['lat'] ?? '') === '' || $v['lat'] === null ? null : (float) $v['lat'],
            'lng' => ($v['lng'] ?? '') === '' || $v['lng'] === null ? null : (float) $v['lng'],
            'note' => $v['note'] ?? null,
            'area_code' => Thai::tamCode($v['tam']),      // ใช้ออกรหัส HC เท่านั้น
        ];
    }

    /** ข้อมูลที่ทุกมุมมองของหน้าทะเบียนต้องใช้ */
    private function pageData(Request $request): array
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'prov' => (string) $request->query('prov', ''),
            'dist' => (string) $request->query('dist', ''),
            'tam' => (string) $request->query('tam', ''),
            'vill' => (string) $request->query('vill', ''),
            'inc' => (string) $request->query('inc', ''),
            'sort' => (string) $request->query('sort', 'hc'),
            'dir' => (int) $request->query('dir', 1),
        ];

        $rows = $this->households->filter($filters);

        /* ?hcs=A,B,C — เจาะดูเฉพาะรายการที่ส่งมาจากหน้าตรวจสอบคุณภาพข้อมูล */
        $preselect = array_values(array_filter(explode(',', (string) $request->query('hcs'))));

        if ($preselect) {
            $rows = array_values(array_filter($rows, fn ($h) => in_array($h['hc'], $preselect, true)));
        }

        $page = Paginate::make($rows, (int) $request->query('page', 1), (int) $request->query('per', 25));

        return [
            'navKey' => 'hh',
            'filters' => $filters,
            'preselect' => $preselect,
            'nextSequence' => mb_substr($this->households->nextHc(0), 6),
            /* ใช้สองงาน: เตือนรายการซ้ำ + ค้นชื่อแล้วเติมข้อมูลทั่วไปให้ในฟอร์ม
               จึงต้องมีฟิลด์ที่อยู่/ติดต่อ/พิกัด ครบพอที่จะเติมได้ */
            'householdIndex' => array_map(
                fn ($h) => [
                    'hc' => $h['hc'],
                    'name' => $h['name'],
                    'house' => $h['house'],
                    'vill' => $h['vill'],
                    'moo' => $h['moo'],
                    'prov' => $h['prov'],
                    'dist' => $h['dist'],
                    'tam' => $h['tam'],
                    'phone' => $h['phone'],
                    'income' => $h['income'],
                    'lat' => $h['lat'],
                    'lng' => $h['lng'],
                ],
                $this->households->all()
            ),
            'areaData' => $this->areas->all(),
            'page' => $page,
            'totalAll' => $this->households->count(),
            'provinces' => $this->areas->provinces(),
            'districts' => $this->areas->districtsIn($filters['prov'] ?: null),
            'tambons' => $this->areas->tambonsIn($filters['prov'] ?: null, $filters['dist'] ?: null),
            'villages' => $this->households->villages(),
            /* ตัวช่วยกรองตอนพิมพ์ชื่อหมู่บ้าน — แยกตามตำบล ไม่เสนอชื่อข้ามพื้นที่ */
            'villageSuggestions' => Household::villageSuggestions(),
            'tambonIds' => $this->areas->labelToId(),
            'activities' => $this->activities->all(),
            /* ใช้ทำ dropdown ปีงบ → โครงการหลัก → กิจกรรม ในฟอร์มครัวเรือน */
            'programsByYear' => $this->activities->programsByYear(),
            'statuses' => EnrollmentRepository::STATUSES,
        ];
    }
}
