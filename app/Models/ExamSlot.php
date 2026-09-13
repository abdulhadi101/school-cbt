<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\SlotType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class ExamSlot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'slot_type' => SlotType::class,
            'difficulty' => Difficulty::class,
            'tag_ids' => 'array',
            'marks_per_question' => 'decimal:2',
        ];
    }

    public function examRevision(): BelongsTo
    {
        return $this->belongsTo(ExamRevision::class);
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }

    public function questionCategory(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class);
    }
}
