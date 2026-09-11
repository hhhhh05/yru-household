<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** เจ้าหน้าที่รับผิดชอบ — รายชื่อกลาง ไม่ผูกกับพื้นที่ */
class Staff extends Model
{
    use SoftDeletes;

    /* ชื่อตารางระบุเอง — Laravel จะเดาเป็น "staves" ตามกฎพหูพจน์อังกฤษ */
    protected $table = 'staff';

    protected $fillable = ['full_name', 'position', 'unit', 'phone', 'email'];
}
