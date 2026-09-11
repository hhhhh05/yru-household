<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Unit;
use App\Repositories\ActivityRepository;
use App\Support\Thai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * คณะ / หน่วยงาน — หน้าจัดการรายชื่อกลาง
 * ใช้เป็นตัวเลือกในหน้าเจ้าหน้าที่รับผิดชอบ
 */
class UnitController extends Controller
{
    public function index(ActivityRepository $activities)
    {
        /* ยังไม่ได้รัน migration ก็ต้องเปิดหน้านี้ได้ ไม่ใช่พังทั้งหน้า */
        $ready = Schema::hasTable('units');
        $units = $ready ? Unit::orderBy('name')->get() : collect();

        /* ชื่อคณะที่มีใช้จริงในข้อมูลกิจกรรม แต่ยังไม่อยู่ในรายการนี้
           เทียบแบบตัดช่องว่างและรวมสระซ้ำ กันชื่อเดียวกันที่พิมพ์ต่างกันเล็กน้อย */
        $known = [];

        foreach ($units as $unit) {
            $known[Thai::nrm($unit->name)] = true;
        }

        $missing = [];

        foreach ($activities->units() as $name) {
            if (! isset($known[Thai::nrm($name)])) {
                $missing[] = $name;
            }
        }

        /* จำนวนเจ้าหน้าที่ต่อคณะ — ใช้เตือนก่อนลบ */
        $staffCounts = [];

        if (Schema::hasTable('staff')) {
            foreach (Staff::whereNotNull('unit')->pluck('unit') as $unitName) {
                $key = Thai::nrm((string) $unitName);
                $staffCounts[$key] = ($staffCounts[$key] ?? 0) + 1;
            }
        }

        return view('units.index', [
            'navKey' => 'un',
            'units' => $units,
            'unitsReady' => $ready,
            'missing' => $missing,
            'staffCounts' => $staffCounts,
            'nrm' => fn (string $s) => Thai::nrm($s),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($existing = $this->findByName($data['name'])) {
            return $this->backTo($existing->name.' มีอยู่ในรายการแล้ว', 'ไม่ได้เพิ่มชื่อซ้ำ', 'warn');
        }

        $unit = Unit::create($data);

        return $this->backTo($unit->name.' ถูกเพิ่มในรายการแล้ว', 'เพิ่มคณะ / หน่วยงานแล้ว');
    }

    public function update(Request $request, string $id)
    {
        $unit = Unit::find($id);
        abort_if(! $unit, 404);

        $data = $this->validated($request);
        $before = $unit->name;

        /* ชื่อซ้ำกับรายการอื่น (ไม่นับตัวเอง) — ไม่บันทึก จะได้ไม่มีสองชื่อเหมือนกันในตัวเลือก */
        $clash = $this->findByName($data['name']);

        if ($clash && $clash->id !== $unit->id) {
            return $this->backTo($clash->name.' มีอยู่ในรายการแล้ว', 'ไม่ได้แก้ชื่อให้ซ้ำ', 'warn');
        }

        $unit->update($data);

        /* ชื่อคณะถูกเก็บเป็นข้อความในตารางเจ้าหน้าที่ ไม่ใช่รหัสอ้างอิง
           เปลี่ยนชื่อที่นี่จึงต้องตามไปแก้ให้ด้วย ไม่งั้นคนเดิมจะหลุดออกจากคณะเงียบ ๆ */
        $moved = 0;

        if ($before !== $unit->name && Schema::hasTable('staff')) {
            $moved = Staff::where('unit', $before)->update(['unit' => $unit->name]);
        }

        return $this->backTo(
            $unit->name.' อัปเดตแล้ว'.($moved ? ' · ปรับชื่อคณะให้เจ้าหน้าที่ '.Thai::fmt($moved).' คนด้วย' : ''),
            'แก้ไขคณะ / หน่วยงานแล้ว'
        );
    }

    public function destroy(string $id)
    {
        $unit = Unit::find($id);
        abort_if(! $unit, 404);

        $name = $unit->name;
        $unit->delete();

        /* ตั้งใจไม่ล้างชื่อคณะของเจ้าหน้าที่ที่บันทึกไว้แล้ว
           ลบออกจาก «รายการตัวเลือก» ไม่ใช่ลบข้อมูลที่กรอกไปแล้ว */
        return $this->backTo($name.' ถูกนำออกจากรายการตัวเลือก', 'ลบคณะ / หน่วยงานแล้ว');
    }

    /** เติมชื่อคณะจากข้อมูลกิจกรรมที่มีอยู่ — กดซ้ำได้ เติมเฉพาะที่ยังขาด */
    public function importFromActivities(ActivityRepository $activities)
    {
        abort_unless(Schema::hasTable('units'), 404);

        $added = 0;

        foreach ($activities->units() as $name) {
            $name = trim($name);

            if ($name === '' || $this->findByName($name)) {
                continue;
            }

            Unit::create(['name' => $name]);
            $added++;
        }

        return $added
            ? $this->backTo('เพิ่มชื่อคณะ / หน่วยงานใหม่ '.Thai::fmt($added).' รายการ', 'เติมจากข้อมูลกิจกรรมแล้ว')
            : $this->backTo('ชื่อคณะในข้อมูลกิจกรรมมีอยู่ในรายการครบแล้ว', 'ไม่มีอะไรต้องเติม', 'warn');
    }

    /** หาชื่อที่ตรงกันแบบไม่สนช่องว่าง/สระซ้ำ — กันชื่อซ้ำที่พิมพ์ต่างกันเล็กน้อย */
    private function findByName(string $name): ?Unit
    {
        $want = Thai::nrm($name);

        foreach (Unit::all() as $unit) {
            if (Thai::nrm($unit->name) === $want) {
                return $unit;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'กรอกชื่อคณะหรือหน่วยงาน',
        ]);

        foreach (['short_name', 'note'] as $field) {
            $v[$field] = trim((string) ($v[$field] ?? '')) ?: null;
        }

        $v['name'] = trim($v['name']);

        return $v;
    }

    private function backTo(string $msg, string $title, string $kind = 'ok')
    {
        return redirect()
            ->route('units.index')
            ->with('toasts', [['title' => $title, 'msg' => $msg, 'kind' => $kind]]);
    }
}
