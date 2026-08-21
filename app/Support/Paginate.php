<?php

namespace App\Support;

/**
 * แบ่งหน้าอย่างง่ายสำหรับข้อมูลที่เป็นอาร์เรย์ (ยังไม่ใช้ฐานข้อมูล)
 * เมื่อย้ายไป Eloquent สามารถเปลี่ยนไปใช้ $query->paginate() ได้เลย
 */
class Paginate
{
    public const SIZES = [10, 25, 50, 100];

    /**
     * @param  array<int, mixed>  $rows
     * @return array{rows:array<int,mixed>,total:int,page:int,per:int,pages:int,from:int,to:int,buttons:array<int,int>}
     */
    public static function make(array $rows, int $page = 1, int $per = 25): array
    {
        $per = in_array($per, self::SIZES, true) ? $per : 25;
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($page, $pages));

        /* ปุ่มเลขหน้า: แสดงไม่เกิน 5 ปุ่มรอบหน้าปัจจุบัน */
        $start = max(1, $page - 2);
        $end = min($pages, $start + 4);
        $start = max(1, $end - 4);

        return [
            'rows' => array_slice($rows, ($page - 1) * $per, $per),
            'total' => $total,
            'page' => $page,
            'per' => $per,
            'pages' => $pages,
            'from' => $total ? ($page - 1) * $per + 1 : 0,
            'to' => min($page * $per, $total),
            'buttons' => range($start, $end),
        ];
    }
}
