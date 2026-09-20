<?php

namespace App\Http\Requests;

use App\Models\AnswerSheet;
use Illuminate\Foundation\Http\FormRequest;

class AnswerSheetSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        foreach (AnswerSheet::questionColumns() as $column) {
            $rules[$column] = ['nullable', 'in:A,B,C,D'];
        }

        return $rules;
    }
}
