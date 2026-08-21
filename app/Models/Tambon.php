<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ตำบล — มีรหัสพื้นที่เป้าหมาย (area_code) ใช้ประกอบรหัส HC */
class Tambon extends Model
{
    protected $fillable = ['district_id', 'name', 'area_code'];

    protected $casts = ['area_code' => 'integer'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    /** ป้ายชื่อรูปแบบเดิม "ปุโรง(10)" */
    public function label(): string
    {
        return $this->name.'('.$this->area_code.')';
    }
}
