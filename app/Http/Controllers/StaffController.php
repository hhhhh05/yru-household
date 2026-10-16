<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Program;
use App\Models\Staff;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * เจ้าหน้าที่รับผิดชอบ — หน้าของตัวเอง เพิ่ม / แก้ไข / ลบ
 * เป็นรายชื่อกลาง ไม่ผูกกับพื้นที่หรือกิจกรรม
 */
class StaffController extends Controller
{
    public function index(Request $request)
    {
        /* ยังไม่ได้รัน migration ก็ต้องเปิดหน้านี้ได้ ไม่ใช่พังทั้งหน้า
           หน้าเว็บจะขึ้นคำแนะนำให้กดปุ่มอัปเดตฐานข้อมูลแทน */
        $ready = Schema::hasTable('staff');

        /* คอลัมน์งานที่รับผิดชอบ/สัญญาจ้าง เพิ่งเพิ่มทีหลัง — ยังไม่ได้รัน migration ก็ต้องเปิดหน้าได้ */
        $jobReady = $ready && Schema::hasColumn('staff', 'program_id');

        $filters = [
            'unit' => (string) $request->query('unit', ''),
            'pg' => (string) $request->query('pg', ''),
            'pa' => (string) $request->query('pa', ''),
            'ct' => (string) $request->query('ct', ''),      // มี/ยังไม่มีสัญญาจ้าง
        ];

        $staff = $ready
            ? Staff::with($jobReady ? ['program', 'activity'] : [])->orderBy('full_name')->get()
            : collect();

        /* กรองเป็นขั้น เหมือนหน้าโครงการ/กิจกรรม — เลือกโครงการแล้วกิจกรรมจะแคบตาม */
        if ($filters['unit'] !== '') {
            $staff = $staff->filter(fn ($p) => trim((string) $p->unit) === $filters['unit']);
        }

        if ($jobReady) {
            if ($filters['pg'] !== '') {
                $staff = $staff->filter(fn ($p) => (string) $p->program_id === $filters['pg']);
            }

            if ($filters['pa'] !== '') {
                $staff = $staff->filter(fn ($p) => (string) $p->activity_id === $filters['pa']);
            }

            if ($filters['ct'] === 'yes') {
                $staff = $staff->filter(fn ($p) => (bool) $p->contract_file);
            } elseif ($filters['ct'] === 'no') {
                $staff = $staff->filter(fn ($p) => ! $p->contract_file);
            }
        }

        $staff = $staff->values();

        return view('staff.index', [
            'navKey' => 'st',
            'staff' => $staff,
            'staffReady' => $ready,
            'jobReady' => $jobReady,
            'filters' => $filters,
            'filtering' => (bool) array_filter($filters, fn ($v) => $v !== ''),
            'totalAll' => $ready ? Staff::count() : 0,
            /* ตัวเลขในการ์ดคิดตามที่กรองอยู่ ไม่ใช่ทั้งระบบ — จะได้ตรงกับตารางข้างล่าง */
            'salarySum' => $jobReady ? (int) $staff->sum(fn ($p) => (int) $p->salary) : 0,
            'salaryCount' => $jobReady ? $staff->filter(fn ($p) => $p->salary !== null)->count() : 0,
            'contractCount' => $jobReady ? $staff->filter(fn ($p) => (bool) $p->contract_file)->count() : 0,
            'assignedCount' => $jobReady ? $staff->filter(fn ($p) => (bool) $p->program_id)->count() : 0,
            /* ตัวเลือกคณะ — ตารางอาจยังไม่ถูกสร้าง ให้เป็นรายการว่างแทนที่จะพัง */
            'units' => Schema::hasTable('units') ? Unit::orderBy('name')->get() : collect(),
            /* ตัวเลือกโครงการ/กิจกรรม — กิจกรรมพ่วงรหัสโครงการไว้ ใช้กรองตามโครงการที่เลือกฝั่งเบราว์เซอร์ */
            /* เลือกได้เฉพาะโครงการที่ยังดำเนินการอยู่ — โครงการที่ปิดแล้วไม่รับงานใหม่
               คนที่เคยผูกกับโครงการที่ปิดไปแล้ว JS จะคงตัวเลือกเดิมไว้ให้ ไม่ถูกล้างทิ้ง */
            'programs' => Program::when(
                Schema::hasColumn('programs', 'status'),
                fn ($q) => $q->where(fn ($w) => $w->where('status', Program::STATUS_OPEN)->orWhereNull('status'))
            )->orderByDesc('fiscal_year')->orderBy('name')->get(),
            'activities' => Activity::orderBy('pa')->get(['id', 'pa', 'name', 'program_id']),
            'uploadLimit' => self::uploadLimitBytes(),
        ]);
    }

    public function store(Request $request)
    {
        $staff = Staff::create($this->validated($request));
        $warning = $this->applyContract($request, $staff);

        return $this->backTo(
            $staff->full_name.' ถูกเพิ่มในรายชื่อแล้ว'.($warning ? ' · '.$warning : ''),
            'เพิ่มเจ้าหน้าที่แล้ว',
            $warning ? 'warn' : 'ok'
        );
    }

    public function update(Request $request, string $id)
    {
        $staff = Staff::find($id);
        abort_if(! $staff, 404);

        $staff->update($this->validated($request));
        $warning = $this->applyContract($request, $staff);

        return $this->backTo(
            $staff->full_name.' อัปเดตแล้ว'.($warning ? ' · '.$warning : ''),
            'แก้ไขเจ้าหน้าที่แล้ว',
            $warning ? 'warn' : 'ok'
        );
    }

    public function destroy(string $id)
    {
        $staff = Staff::find($id);
        abort_if(! $staff, 404);

        $name = $staff->full_name;
        $contract = $staff->contractPath();
        $staff->delete();

        /* ลบไฟล์สัญญาตามไปด้วย ไม่ให้เหลือไฟล์ที่ไม่มีอะไรอ้างถึง */
        if ($contract && is_file($contract)) {
            @unlink($contract);
        }

        return $this->backTo($name.' ถูกนำออกจากรายชื่อ', 'ลบเจ้าหน้าที่แล้ว');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'position' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:180'],
            /* รับได้ทั้ง 0812345678 และ 081-234-5678 แบบเดียวกับฟอร์มครัวเรือน */
            'phone' => ['nullable', 'string', 'regex:/^\d{3}-\d{3}-\d{4}$|^\d{9,10}$/'],
            'email' => ['nullable', 'email', 'max:180'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'full_name.required' => 'กรอกชื่อ - สกุลของเจ้าหน้าที่',
            'phone.regex' => 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก หรือรูปแบบ 081-234-5678',
            'email.email' => 'อีเมลไม่ถูกต้อง',
            'salary.numeric' => 'เงินเดือนต้องเป็นตัวเลข',
        ]);

        /* ช่องว่างให้เก็บเป็น null ไม่ใช่สตริงว่าง — เวลาเช็ค «มีข้อมูลไหม» จะได้ไม่ต้องเช็คสองแบบ */
        foreach (['position', 'unit', 'phone', 'email'] as $field) {
            $v[$field] = trim((string) ($v[$field] ?? '')) ?: null;
        }

        $v['full_name'] = trim($v['full_name']);

        foreach (['program_id', 'activity_id'] as $field) {
            $v[$field] = $v[$field] ?? null;
        }

        $v['salary'] = ($v['salary'] ?? '') === '' ? null : $v['salary'];

        /* กิจกรรมต้องอยู่ใต้โครงการที่เลือกจริง ๆ
           ถ้าสลับโครงการแล้วกิจกรรมเก่าค้างอยู่ ให้ทิ้งกิจกรรมไป ดีกว่าบันทึกคู่ที่ขัดกันเอง */
        if ($v['activity_id']) {
            $activity = Activity::find($v['activity_id']);

            if (! $activity || ($v['program_id'] && (int) $activity->program_id !== (int) $v['program_id'])) {
                $v['activity_id'] = null;
            } elseif (! $v['program_id']) {
                $v['program_id'] = $activity->program_id;   // เลือกกิจกรรมแต่ไม่ได้เลือกโครงการ → เติมให้
            }
        }

        /* ยังไม่ได้อัปเดตฐานข้อมูล = ไม่มีคอลัมน์พวกนี้ อย่าส่งไป */
        if (! Schema::hasColumn('staff', 'program_id')) {
            unset($v['program_id'], $v['activity_id'], $v['salary']);
        }

        return $v;
    }

    private function backTo(string $msg, string $title, string $kind = 'ok')
    {
        return redirect()
            ->route('staff.index')
            ->with('toasts', [['title' => $title, 'msg' => $msg, 'kind' => $kind]]);
    }

    /**
     * แนบไฟล์สัญญาจ้าง (PDF หรือ Word) — ไม่บังคับ
     * เว้นว่าง = ใช้ไฟล์เดิม · เลือกใหม่ = แทนที่ของเดิม
     *
     * @return string|null ข้อความเตือน ถ้ารับไฟล์ไม่ได้
     */
    private function applyContract(Request $request, Staff $staff): ?string
    {
        if (! Schema::hasColumn('staff', 'contract_file')) {
            return null;
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('contract');

        if (! $file) {
            return null;
        }

        if (! $file->isValid()) {
            $limit = round(self::uploadLimitBytes() / 1048576, 1);

            return match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์สัญญาใหญ่เกินที่เครื่องนี้รับได้ ('.$limit.' MB)',
                UPLOAD_ERR_PARTIAL => 'ไฟล์สัญญาอัปโหลดมาไม่ครบ ลองใหม่อีกครั้ง',
                default => 'อัปโหลดไฟล์สัญญาไม่สำเร็จ (รหัส '.$file->getError().')',
            };
        }

        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            return 'ไฟล์สัญญารับเฉพาะ PDF หรือ Word (.pdf .doc .docx)';
        }

        if ($file->getSize() > self::uploadLimitBytes()) {
            return 'ไฟล์สัญญาใหญ่เกินที่เครื่องนี้รับได้ ('.round(self::uploadLimitBytes() / 1048576, 1).' MB)';
        }

        $dir = public_path('uploads/staff-contracts');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        /* ชื่อไฟล์บนดิสก์ตั้งเอง ไม่ใช้ชื่อที่ผู้ใช้อัปโหลดมาตรง ๆ
           กันชื่อซ้ำทับกัน และกันอักขระที่อ่านเป็นเส้นทางไฟล์ได้ */
        $stored = 'staff-'.$staff->id.'-'.now()->format('YmdHis').'.'.$ext;
        $old = $staff->contractPath();

        $file->move($dir, $stored);

        if ($old && is_file($old) && basename($old) !== $stored) {
            @unlink($old);
        }

        $staff->update([
            'contract_file' => $stored,
            'contract_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
        ]);

        return null;
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
}
