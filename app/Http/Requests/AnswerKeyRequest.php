<?php

namespace App\Http\Requests;

use App\Models\AnswerSheet;
use Illuminate\Foundation\Http\FormRequest;

class AnswerKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'passing_score' => ['required', 'integer', 'min:1', 'max:80'],
        ];

        foreach (AnswerSheet::questionColumns() as $column) {
            $rules[$column] = ['nullable', 'in:A,B,C,D'];
        }

        return $rules;
    }
}
