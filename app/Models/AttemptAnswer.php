<?php

namespace App\Models;

use App\Enums\GradingStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class AttemptAnswer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'client_answered_at' => 'datetime',
            'answered_at' => 'datetime',
            'grading_status' => GradingStatus::class,
            'score' => 'decimal:2',
            'is_correct' => 'boolean',
            'graded_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(AttemptQuestion::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AnswerRevision::class);
    }

    public function manualGrades(): HasMany
    {
        return $this->hasMany(ManualGrade::class);
    }
}
