<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required'],
            'end_time' => ['nullable'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'room' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::exists('test_session_statuses', 'slug')],
            'start_number' => ['nullable', 'integer', 'min:1'],
            'end_number' => ['nullable', 'integer', 'gte:start_number'],
        ];
    }
}
