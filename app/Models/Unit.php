<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** คณะ / หน่วยงาน — รายชื่อกลาง ใช้เป็นตัวเลือกในหน้าอื่น */
class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'short_name', 'note'];
}
