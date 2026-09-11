<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class ClassLevel extends Model
{
    use HasFactory;

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
