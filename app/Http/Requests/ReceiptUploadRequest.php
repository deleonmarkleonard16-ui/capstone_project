<?php

namespace App\Http\Requests;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
            $this->files->set('proof', $file);
        }
        // Rebuild Laravel's uploaded-file cache after normalizing the alias.
        $this->convertedFiles = null;

        // If client passed neither OR number nor OR date, auto-fill for legacy/simplified submissions
        if (! $this->filled('or_number') && ! $this->filled('or_date')) {
            $this->merge([
                'or_number' => 'OR-' . strtoupper(Str::random(8)),
                'or_date'   => now()->toDateString(),
            ]);
        }
    }

    public function rules(): array
    {
        $isPortalReceipt = $this->routeIs('portal.receipt') || $this->is('portal/psychological/receipt');
        $fileRule = $isPortalReceipt 
            ? 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:5120' 
            : 'required|file|image|max:5120';

        return [
            'reference'    => ServiceRequest::referenceRules(),
            'or_number'    => 'required|string|max:50',
            'or_date'      => 'required|date|before_or_equal:today',
            'payment_slip' => $fileRule,
            'proof'        => $fileRule,
        ];
    }
}
