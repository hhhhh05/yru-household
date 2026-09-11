<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * เจ้าหน้าที่รับผิดชอบ — หน้าของตัวเอง เพิ่ม / แก้ไข / ลบ
 * เป็นรายชื่อกลาง ไม่ผูกกับพื้นที่หรือกิจกรรม
 */
class StaffController extends Controller
{
    public function index()
    {
        /* ยังไม่ได้รัน migration ก็ต้องเปิดหน้านี้ได้ ไม่ใช่พังทั้งหน้า
           หน้าเว็บจะขึ้นคำแนะนำให้กดปุ่มอัปเดตฐานข้อมูลแทน */
        $ready = Schema::hasTable('staff');

        return view('staff.index', [
            'navKey' => 'st',
            'staff' => $ready ? Staff::orderBy('full_name')->get() : collect(),
            'staffReady' => $ready,
            /* ตัวเลือกคณะ — ตารางอาจยังไม่ถูกสร้าง ให้เป็นรายการว่างแทนที่จะพัง */
            'units' => Schema::hasTable('units') ? Unit::orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $staff = Staff::create($this->validated($request));

        return $this->backTo($staff->full_name.' ถูกเพิ่มในรายชื่อแล้ว', 'เพิ่มเจ้าหน้าที่แล้ว');
    }

    public function update(Request $request, string $id)
    {
        $staff = Staff::find($id);
        abort_if(! $staff, 404);

        $staff->update($this->validated($request));

        return $this->backTo($staff->full_name.' อัปเดตแล้ว', 'แก้ไขเจ้าหน้าที่แล้ว');
    }

    public function destroy(string $id)
    {
        $staff = Staff::find($id);
        abort_if(! $staff, 404);

        $name = $staff->full_name;
        $staff->delete();

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
        ], [
            'full_name.required' => 'กรอกชื่อ - สกุลของเจ้าหน้าที่',
            'phone.regex' => 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก หรือรูปแบบ 081-234-5678',
            'email.email' => 'อีเมลไม่ถูกต้อง',
        ]);

        /* ช่องว่างให้เก็บเป็น null ไม่ใช่สตริงว่าง — เวลาเช็ค «มีข้อมูลไหม» จะได้ไม่ต้องเช็คสองแบบ */
        foreach (['position', 'unit', 'phone', 'email'] as $field) {
            $v[$field] = trim((string) ($v[$field] ?? '')) ?: null;
        }

        $v['full_name'] = trim($v['full_name']);

        return $v;
    }

    private function backTo(string $msg, string $title)
    {
        return redirect()
            ->route('staff.index')
            ->with('toasts', [['title' => $title, 'msg' => $msg, 'kind' => 'ok']]);
    }
}
