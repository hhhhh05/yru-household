<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** โครงการหลักตามปีงบประมาณ */
class Program extends Model
{
    protected $fillable = ['fiscal_year', 'name'];

    protected $casts = ['fiscal_year' => 'integer'];

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
