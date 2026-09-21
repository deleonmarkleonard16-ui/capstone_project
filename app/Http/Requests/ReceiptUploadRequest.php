<?php

namespace App\Http\Requests;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Http\FormRequest;

class ReceiptUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $reference = $this->input('reference', $this->input('request_code'));
        $this->merge(['reference' => is_string($reference) ? strtoupper(trim($reference)) : $reference]);

        $file = $this->files->get('payment_slip') ?? $this->files->get('proof');
        if ($file !== null) {
            $this->files->set('payment_slip', $file);
        }
        // Rebuild Laravel's uploaded-file cache after normalizing the alias.
        $this->convertedFiles = null;
    }

    public function rules(): array
    {
        return [
            'reference' => ServiceRequest::referenceRules(),
            'or_number' => 'required|string|max:50',
            'or_date' => 'required|date|before_or_equal:today',
            'payment_slip' => 'required|file|image|max:5120',
        ];
    }
}
