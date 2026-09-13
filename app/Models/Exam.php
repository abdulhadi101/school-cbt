<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\ScoreReleasePolicy;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded([])]
class Exam extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'exam_type' => ExamType::class,
            'score_release_policy' => ScoreReleasePolicy::class,
            'duration_minutes' => 'integer',
            'total_marks' => 'decimal:2',
            'pass_percentage' => 'decimal:2',
            'max_attempts' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'score_release_at' => 'datetime',
            'show_responses' => 'boolean',
            'show_correct_answers' => 'boolean',
            'show_feedback' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ExamRevision::class);
    }

    public function draftSlots(): HasMany
    {
        return $this->hasMany(ExamDraftSlot::class)->orderBy('position');
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(ExamRevision::class)->latestOfMany('revision_number');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(ExamAudience::class);
    }

    public function accommodations(): HasMany
    {
        return $this->hasMany(ExamAccommodation::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
