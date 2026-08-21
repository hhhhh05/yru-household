<?php

if (! function_exists('qs')) {
    /**
     * รวม query string ปัจจุบันกับค่าที่ต้องการเปลี่ยน — ใช้ทำลิงก์ตัวกรอง/เรียงลำดับ/แบ่งหน้า
     *
     * @param  array<string, mixed>  $overrides  ค่าที่ต้องการแทนที่ (ใส่ '' เพื่อลบออก)
     * @param  array<int, string>  $except  คีย์ที่ต้องการตัดออก
     * @return array<string, mixed>
     */
    function qs(array $overrides = [], array $except = []): array
    {
        $query = array_merge(request()->query(), $overrides);

        foreach ($except as $key) {
            unset($query[$key]);
        }

        return array_filter($query, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
