<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Guarded([])]
class QuestionTag extends Model
{
    use HasFactory;

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBankEntry::class, 'question_bank_entry_question_tag');
    }
}
