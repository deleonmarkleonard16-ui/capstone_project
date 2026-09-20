<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignApplicantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'applicant_ids' => ['required', 'array', 'min:1'],
            'applicant_ids.*' => ['required', 'integer', 'exists:applicants,id'],
        ];
    }
}
