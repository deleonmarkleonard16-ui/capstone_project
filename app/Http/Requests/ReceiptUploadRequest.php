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

    public function messages(): array
    {
        return [
            'reference.required' => 'The tracking reference is required.',
            'or_number.required' => 'Official Receipt (OR) Number is required.',
            'or_number.max' => 'The Official Receipt Number cannot exceed 50 characters.',
            'or_date.required' => 'Receipt Date is required.',
            'or_date.date' => 'Please enter a valid Receipt Date.',
            'or_date.before_or_equal' => 'The Receipt Date cannot be in the future.',
            'payment_slip.required' => 'Please select a valid image file under 5 MB.',
            'payment_slip.image' => 'Please upload a valid image file (JPG, PNG, or WebP).',
            'payment_slip.mimes' => 'Please upload a valid image file (JPG, PNG, WebP) or PDF under 5 MB.',
            'payment_slip.max' => 'The receipt file size must be under 5 MB.',
            'proof.required' => 'Please select a valid image file under 5 MB.',
            'proof.image' => 'Please upload a valid image file (JPG, PNG, or WebP).',
            'proof.mimes' => 'Please upload a valid image file (JPG, PNG, WebP) or PDF under 5 MB.',
            'proof.max' => 'The receipt file size must be under 5 MB.',
        ];
    }
}
