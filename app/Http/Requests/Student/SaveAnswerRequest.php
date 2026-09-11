<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SaveAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attempt_question_id' => ['required', 'integer', 'exists:attempt_questions,id'],
            'response' => ['required', 'array'],
            'client_sequence' => ['required', 'integer', 'min:0'],
            'client_answered_at' => ['nullable', 'date'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
