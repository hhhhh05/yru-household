<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Program;
use App\Models\Unit;
use App\Repositories\ActivityRepository;
use App\Repositories\EnrollmentRepository;
use App\Support\Thai;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * โครงการหลัก — หน้าของตัวเอง แยกจากหน้ากิจกรรม
 *
 * โครงการหลักคือ «ร่ม» ของกิจกรรม: หนึ่งโครงการมีได้หลายกิจกรรม
 * ปีงบเป็นของโครงการ กิจกรรมยึดปีงบตามโครงการแม่เสมอ
 */
class ProgramController extends Controller
{
    public function __construct(
        private ActivityRepository $activities,
        private EnrollmentRepository $enrollments,
    ) {}

    public function index(Request $request)
    {
        $fy = (string) $request->query('fy', '');

        /* ค่าเริ่มต้นของหน้าแรก = เห็นเฉพาะโครงการที่ยังเดินอยู่
           โครงการที่ปิดแล้วไม่ได้หายไปไหน เลือกจากช่องสถานะเพื่อดูได้ตลอด
           ('all' = ทุกสถานะ — ต้องเลือกเอง ไม่ใช่ค่าตั้งต้น) */
        $statusReady = Schema::hasColumn('programs', 'status');
        $st = (string) $request->query('st', Program::STATUS_OPEN);

        if (! $statusReady || ! in_array($st, [...Program::STATUSES, 'all'], true)) {
            $st = 'all';
        }

        $programs = Program::orderByDesc('fiscal_year')->orderBy('name')->get();

        if ($fy !== '') {
            $programs = $programs->filter(fn ($p) => (string) $p->fiscal_year === $fy)->values();
        }

        if ($st !== 'all') {
            $programs = $programs
                ->filter(fn ($p) => ($p->status ?: Program::STATUS_OPEN) === $st)
                ->values();
        }

        /* จำนวนที่ถูกซ่อนเพราะตัวกรองสถานะ — บอกไว้ในหน้า จะได้ไม่งงว่าโครงการหายไปไหน */
        $hiddenByStatus = $statusReady && $st !== 'all'
            ? Program::when($fy !== '', fn ($q) => $q->where('fiscal_year', $fy))->count() - count($programs)
            : 0;

        /* สรุปต่อโครงการ — จำนวนกิจกรรม · งบรวม · จำนวนรายการลงทะเบียน
           วนจากชุดข้อมูลที่โหลดไว้แล้วครั้งเดียว ไม่ยิงคิวรีต่อแถว */
        $activityCount = [];
        $budget = [];
        $enrolled = [];

        /* นับเฉพาะโครงการที่แสดงอยู่ ตัวเลขในการ์ดสรุปจะได้ตรงกับตารางข้างล่าง */
        $shown = $programs->pluck('id')->all();

        foreach ($this->activities->all() as $activity) {
            $id = $activity['program_id'] ?? null;

            if (! $id || ! in_array($id, $shown)) {
                continue;
            }

            $activityCount[$id] = ($activityCount[$id] ?? 0) + 1;
            $budget[$id] = ($budget[$id] ?? 0) + $activity['budget'];
            $enrolled[$id] = ($enrolled[$id] ?? 0) + $this->enrollments->countForActivity($activity['pa']);
        }

        /* กิจกรรมที่ยังไม่ผูกโครงการหลัก — เตือนไว้ เพราะจะไม่โผล่ในหน้านี้เลย */
        $unlinked = count(array_filter($this->activities->all(), fn ($a) => empty($a['program_id'])));

        return view('programs.index', [
            'navKey' => 'pgm',
            'fy' => $fy,
            'st' => $st,
            'hiddenByStatus' => max(0, $hiddenByStatus),
            'programs' => $programs,
            'fiscalYears' => $this->activities->yearOptions(),   /* ยึดจากตาราง programs ปีที่ยังไม่มีกิจกรรมจึงไม่หาย */
            'activityCount' => $activityCount,
            'budgetOf' => $budget,
            'enrolledOf' => $enrolled,
            'unlinked' => $unlinked,
            'totalBudget' => array_sum($budget),
            'statuses' => Program::STATUSES,
            /* ยังไม่ได้รัน migration = ยังไม่มีคอลัมน์สถานะ/เล่มรายงาน
               หน้าต้องเปิดได้ตามปกติ แค่ซ่อนส่วนนั้นไว้ก่อน */
            'statusReady' => $statusReady,
            /* ขีดจำกัดจริงของ PHP บนเครื่องนี้ — เอาไปบอกผู้ใช้และกันไว้ฝั่งเบราว์เซอร์
               ไฟล์ที่ใหญ่เกิน post_max_size จะถูก PHP ทิ้งตั้งแต่ก่อนถึง Laravel
               ผลคือฟอร์ม «กดบันทึกแล้วเงียบ» โดยไม่มีข้อความอะไรเลย */
            'uploadLimit' => self::uploadLimitBytes(),
            /* ยังไม่ได้รัน migration ของคอลัมน์ผู้รับผิดชอบ ก็ต้องเปิดหน้านี้ได้ */
            'leadReady' => Schema::hasColumn('programs', 'lead_name'),
            'docReady' => Schema::hasColumn('programs', 'doc_file'),
            /* ตัวเลือกคณะ — จากเมนู «คณะ / หน่วยงาน» ถ้ายังไม่มี ใช้ชื่อที่พบในข้อมูลกิจกรรมแทน */
            'unitOptions' => Schema::hasTable('units')
                ? Unit::orderBy('name')->pluck('name')->all()
                : $this->activities->units(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($existing = $this->findSame($data['name'], $data['fiscal_year'])) {
            return $this->backTo(
                $existing->name.' มีอยู่ในปีงบ '.$existing->fiscal_year.' แล้ว',
                'ไม่ได้เพิ่มโครงการซ้ำ',
                'warn'
            );
        }

        $program = Program::create($data);
        $docWarning = $this->applyDocument($request, $program);
        $warning = $this->applyStatus($request, $program) ?? $docWarning;

        return $warning
            ? $this->backTo($program->name.' · '.$warning, 'เพิ่มโครงการหลักแล้ว', 'warn')
            : $this->backTo($program->name.' · ปีงบ '.$program->fiscal_year, 'เพิ่มโครงการหลักแล้ว');
    }

    public function update(Request $request, string $id)
    {
        $program = Program::find($id);
        abort_if(! $program, 404);

        $data = $this->validated($request);
        $clash = $this->findSame($data['name'], $data['fiscal_year']);

        if ($clash && $clash->id !== $program->id) {
            return $this->backTo(
                $clash->name.' มีอยู่ในปีงบ '.$clash->fiscal_year.' แล้ว',
                'ไม่ได้แก้ให้ซ้ำกับโครงการอื่น',
                'warn'
            );
        }

        $program->update($data);
        $docWarning = $this->applyDocument($request, $program);
        $warning = $this->applyStatus($request, $program) ?? $docWarning;

        /* ปีงบของกิจกรรมยึดตามโครงการแม่ — ย้ายปีงบโครงการแล้วต้องตามไปแก้กิจกรรมด้วย
           ไม่งั้นกิจกรรมจะค้างอยู่คนละปีกับโครงการของตัวเอง และรหัส PA จะอ่านแล้วขัดกับข้อมูล */
        $moved = Activity::where('program_id', $program->id)
            ->where('fiscal_year', '!=', $program->fiscal_year)
            ->update(['fiscal_year' => $program->fiscal_year]);

        return $this->backTo(
            $program->name
                .($moved ? ' · ย้ายปีงบของกิจกรรม '.Thai::fmt($moved).' รายการตามไปด้วย' : '')
                .($warning ? ' · '.$warning : ''),
            'แก้ไขโครงการหลักแล้ว',
            $warning ? 'warn' : 'ok'
        );
    }

    public function destroy(string $id)
    {
        $program = Program::find($id);
        abort_if(! $program, 404);

        /* มีกิจกรรมอยู่ใต้โครงการนี้ = ลบไม่ได้
           ถ้าปล่อยให้ลบ กิจกรรมจะกลายเป็นกิจกรรมลอยที่ไม่มีปีงบต้นทาง
           ให้ย้ายหรือลบกิจกรรมก่อน จะได้รู้ตัวว่ากำลังทำอะไรกับข้อมูลชุดไหน */
        $count = Activity::where('program_id', $program->id)->count();

        if ($count) {
            return $this->backTo(
                $program->name.' ยังมีกิจกรรมอยู่ '.Thai::fmt($count).' รายการ — ย้ายหรือลบกิจกรรมก่อน',
                'ลบไม่ได้',
                'err'
            );
        }

        $name = $program->name;
        $path = $program->reportPath();
        $docPath = $program->docPath();
        $program->delete();

        if ($docPath && is_file($docPath)) {
            @unlink($docPath);
        }

        /* ลบไฟล์เล่มรายงานตามไปด้วย ไม่งั้นไฟล์จะค้างในเครื่องโดยไม่มีอะไรอ้างถึงอีก */
        if ($path && is_file($path)) {
            @unlink($path);
        }

        return $this->backTo($name, 'ลบโครงการหลักแล้ว');
    }

    /**
     * บันทึกสถานะโครงการ + เล่มรายงาน
     *
     * กฎสำคัญ: «ปิดโครงการ» ต้องมีเล่มรายงาน PDF เสมอ
     * ถ้ายังไม่เคยแนบและรอบนี้ก็ไม่ได้แนบมา จะคงสถานะ «กำลังดำเนินการ» ไว้
     * แล้วคืนข้อความเตือนให้ผู้เรียกเอาไปแสดง (ตรวจซ้ำที่นี่ เพราะ required ฝั่งเบราว์เซอร์ข้ามได้)
     *
     * @return string|null ข้อความเตือน ถ้ามี
     */
    private function applyStatus(Request $request, Program $program): ?string
    {
        if (! Schema::hasColumn('programs', 'status')) {
            return null;
        }

        $status = in_array($request->input('status'), Program::STATUSES, true)
            ? $request->input('status')
            : Program::STATUS_OPEN;

        /** @var UploadedFile|null $file */
        $file = $request->file('report');

        /* อัปโหลดไม่สำเร็จตั้งแต่ระดับ PHP (ไฟล์ใหญ่เกิน ini, ส่งมาไม่ครบ ฯลฯ)
           ต้องบอกสาเหตุให้ชัด ไม่งั้นผู้ใช้จะเห็นแค่ «กดแล้วไม่มีอะไรเกิดขึ้น» */
        if ($file && ! $file->isValid()) {
            $limit = round(self::uploadLimitBytes() / 1048576, 1);

            return $this->finishStatus($program, $status, null, match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินที่เครื่องนี้รับได้ ('.$limit.' MB) — ย่อไฟล์ก่อน หรือเพิ่มค่า upload_max_filesize/post_max_size ใน php.ini',
                UPLOAD_ERR_PARTIAL => 'ไฟล์อัปโหลดมาไม่ครบ ลองใหม่อีกครั้ง',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์เขียนไฟล์ชั่วคราวไม่ได้ ตรวจสิทธิ์โฟลเดอร์ temp ของ PHP',
                default => 'อัปโหลดไฟล์ไม่สำเร็จ (รหัส '.$file->getError().')',
            });
        }

        /* ตรวจว่าเป็น PDF จริงจากหัวไฟล์ (%PDF-) ไม่พึ่ง mimetypes ของ Laravel
           เพราะการตรวจ MIME ต้องมีส่วนขยาย fileinfo ซึ่งบางเครื่องปิดไว้
           แล้วจะกลายเป็น «ไฟล์ถูกต้องแต่บันทึกไม่ผ่าน» โดยไม่มีคำอธิบาย */
        if ($file) {
            $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 5);

            if (! str_starts_with($head, '%PDF-')) {
                return $this->finishStatus($program, $status, null, 'ไฟล์ที่แนบไม่ใช่ PDF — แนบเล่มรายงานเป็นไฟล์ .pdf เท่านั้น');
            }

            if ($file->getSize() > self::uploadLimitBytes()) {
                return $this->finishStatus($program, $status, null,
                    'ไฟล์ใหญ่เกินที่เครื่องนี้รับได้ ('.round(self::uploadLimitBytes() / 1048576, 1).' MB)');
            }
        }

        $warning = null;

        if ($status === Program::STATUS_CLOSED && ! $file && ! $program->report_file) {
            $status = Program::STATUS_OPEN;
            $warning = 'ยังไม่ได้ปิดโครงการ — ต้องแนบเล่มรายงาน (PDF) ก่อน';
        }

        $changes = ['status' => $status];

        if ($file) {
            $dir = public_path('uploads/program-reports');

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            /* ชื่อไฟล์บนดิสก์ตั้งเอง ไม่ใช้ชื่อที่ผู้ใช้อัปโหลดมาตรง ๆ
               กันชื่อซ้ำทับกัน และกันชื่อที่มีอักขระซึ่งอ่านเป็นเส้นทางไฟล์ได้ */
            $stored = 'program-'.$program->id.'-'.now()->format('YmdHis').'.pdf';
            $old = $program->reportPath();

            $file->move($dir, $stored);

            if ($old && is_file($old) && basename($old) !== $stored) {
                @unlink($old);       // แทนที่เล่มเดิม ไม่เก็บไฟล์กำพร้าไว้
            }

            $changes['report_file'] = $stored;
            $changes['report_name'] = mb_substr((string) $file->getClientOriginalName(), 0, 255);
        }

        /* วันที่ปิด: ลงวันตอนปิด และล้างทิ้งเมื่อกลับมาดำเนินการต่อ */
        $changes['closed_at'] = $status === Program::STATUS_CLOSED
            ? ($program->closed_at ?: Program::todayBuddhist())
            : null;

        $program->update($changes);

        return $warning;
    }

    /**
     * แนบ «ไฟล์โครงการ» (เอกสารโครงการฉบับเต็ม)
     *
     * ไม่บังคับ และรับได้ทั้ง PDF และไฟล์ Word เพราะเอกสารโครงการมักส่งกันเป็น .docx
     * เว้นว่าง = ใช้ไฟล์เดิม · เลือกไฟล์ใหม่ = แทนที่ของเดิม
     *
     * @return string|null ข้อความเตือน ถ้ารับไฟล์ไม่ได้
     */
    private function applyDocument(Request $request, Program $program): ?string
    {
        if (! Schema::hasColumn('programs', 'doc_file')) {
            return null;
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('document');

        if (! $file) {
            return null;
        }

        if (! $file->isValid()) {
            $limit = round(self::uploadLimitBytes() / 1048576, 1);

            return match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์โครงการใหญ่เกินที่เครื่องนี้รับได้ ('.$limit.' MB)',
                UPLOAD_ERR_PARTIAL => 'ไฟล์โครงการอัปโหลดมาไม่ครบ ลองใหม่อีกครั้ง',
                default => 'อัปโหลดไฟล์โครงการไม่สำเร็จ (รหัส '.$file->getError().')',
            };
        }

        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            return 'ไฟล์โครงการรับเฉพาะ PDF หรือ Word (.pdf .doc .docx)';
        }

        if ($file->getSize() > self::uploadLimitBytes()) {
            return 'ไฟล์โครงการใหญ่เกินที่เครื่องนี้รับได้ ('.round(self::uploadLimitBytes() / 1048576, 1).' MB)';
        }

        $dir = public_path('uploads/program-docs');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        /* ชื่อไฟล์บนดิสก์ตั้งเอง ไม่ใช้ชื่อที่ผู้ใช้อัปโหลดมาตรง ๆ */
        $stored = 'program-'.$program->id.'-doc-'.now()->format('YmdHis').'.'.$ext;
        $old = $program->docPath();

        $file->move($dir, $stored);

        if ($old && is_file($old) && basename($old) !== $stored) {
            @unlink($old);
        }

        $program->update([
            'doc_file' => $stored,
            'doc_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
        ]);

        return null;
    }

    /**
     * ปิดงานของ applyStatus เมื่อรับไฟล์ไม่ได้ — บันทึกเฉพาะสถานะที่ยังทำได้
     * แล้วคืนข้อความบอกสาเหตุ (สถานะจะไม่ถูกเปลี่ยนเป็น «ปิดโครงการ» ถ้ายังไม่มีเล่มรายงาน)
     */
    private function finishStatus(Program $program, string $status, ?UploadedFile $file, string $warning): string
    {
        if ($status === Program::STATUS_CLOSED && ! $program->report_file) {
            $status = Program::STATUS_OPEN;
        }

        $program->update([
            'status' => $status,
            'closed_at' => $status === Program::STATUS_CLOSED
                ? ($program->closed_at ?: Program::todayBuddhist())
                : null,
        ]);

        return $warning;
    }

    /** ขีดจำกัดขนาดไฟล์ที่อัปโหลดได้จริง = ค่าที่น้อยกว่าระหว่าง upload_max_filesize กับ post_max_size */
    public static function uploadLimitBytes(): int
    {
        $toBytes = function (string $value): int {
            $value = trim($value);

            if ($value === '') {
                return 0;
            }

            $unit = strtolower(substr($value, -1));
            $num = (int) $value;

            return match ($unit) {
                'g' => $num * 1024 * 1024 * 1024,
                'm' => $num * 1024 * 1024,
                'k' => $num * 1024,
                default => $num,
            };
        };

        $limits = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')),
        ]);

        return $limits ? min($limits) : 2 * 1024 * 1024;
    }

    private function findSame(string $name, int $fiscalYear): ?Program
    {
        $want = Thai::nrm($name);

        foreach (Program::where('fiscal_year', $fiscalYear)->get() as $program) {
            if (Thai::nrm($program->name) === $want) {
                return $program;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'integer', 'min:2560', 'max:2600'],
            'lead_name' => ['nullable', 'string', 'max:180'],
            'unit' => ['nullable', 'string', 'max:180'],
        ], [
            'name.required' => 'กรอกชื่อโครงการหลัก',
            'fiscal_year.required' => 'กรอกปีงบประมาณ',
            'fiscal_year.integer' => 'ปีงบประมาณต้องเป็นตัวเลข พ.ศ. เช่น 2570',
        ]);

        $v['name'] = trim($v['name']);

        foreach (['lead_name', 'unit'] as $field) {
            $v[$field] = trim((string) ($v[$field] ?? '')) ?: null;
        }

        /* ยังไม่มีคอลัมน์ในฐานข้อมูล = อย่าส่งไป ไม่งั้น mass assignment จะพังทั้งคำสั่ง */
        if (! Schema::hasColumn('programs', 'lead_name')) {
            unset($v['lead_name'], $v['unit']);
        }

        return $v;
    }

    private function backTo(string $msg, string $title, string $kind = 'ok')
    {
        return redirect()
            ->route('programs.index')
            ->with('toasts', [['title' => $title, 'msg' => $msg, 'kind' => $kind]]);
    }
}
