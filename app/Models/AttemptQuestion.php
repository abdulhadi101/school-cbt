<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded([])]
class AttemptQuestion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
            'question_snapshot' => 'array',
            'option_order' => 'array',
            'requires_manual_grading' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }

    public function answer(): HasOne
    {
        return $this->hasOne(AttemptAnswer::class);
    }
}
