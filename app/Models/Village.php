<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** หมู่บ้าน / ชุมชน */
class Village extends Model
{
    protected $fillable = ['tambon_id', 'name', 'moo'];

    protected $casts = ['moo' => 'integer'];

    public function tambon(): BelongsTo
    {
        return $this->belongsTo(Tambon::class);
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }
}
