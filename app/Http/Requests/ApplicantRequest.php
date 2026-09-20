<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $applicantId = $this->route('applicant')?->id;

        return [
            'application_number' => ['required', 'string', Rule::unique('applicants', 'application_number')->ignore($applicantId)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::exists('genders', 'name')],
            'email' => ['nullable', 'email'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::exists('applicant_statuses', 'slug')],
        ];
    }
}
