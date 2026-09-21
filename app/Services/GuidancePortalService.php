<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ServiceRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GuidancePortalService
{
    public function create(array $data): ServiceRequest
    {
        return DB::transaction(function () use ($data) {
            $studentNumber = $data['student_number'] ?? '';
            $categories    = $data['tests'] ?? ['psychological'];

            // ── Duplicate guard (per test category) ─────────────────────────────
            // Statuses that represent an in-flight guidance appointment.
            $activeGaStatuses = ['Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress'];

            if ($studentNumber !== '') {
                foreach ($categories as $category) {
                    $existing = \App\Models\GuidanceAppointment::where(function ($q) use ($studentNumber) {
                            $q->where('student_id_number', $studentNumber)
                              ->orWhereHas('serviceRequest', fn ($sr) => $sr->where('student_number', $studentNumber));
                        })
                        ->where('test_category', $category)
                        ->whereIn('status', $activeGaStatuses)
                        ->whereNull('batch_id')
                        ->select(['guidance_appointment_id', 'request_code', 'status', 'test_category'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'service' => 'You already have an active request for this item. Please track your existing request using your Tracking Reference code.',
                        ]);
                    }
                }

                // Also guard the service_requests row (testing service) for the same student
                if (\App\Models\ServiceRequest::hasActiveRequest($studentNumber, 'testing')) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'service' => 'You already have an active request for this item. Please track your existing request using your Tracking Reference code.',
                    ]);
                }
            }
            // ── End duplicate guard ──────────────────────────────────────────────

            $entry = ServiceRequest::create($data + [
                'reference' => app(GuidanceReferenceService::class)->reserve(),
                'status'    => 'pending',
            ]);
            $applicant = Applicant::create([
                'application_number' => 'GT-'.strtoupper(bin2hex(random_bytes(12))),
                'first_name'  => $entry->first_name, 'middle_name' => $entry->middle_name,
                'last_name'   => $entry->last_name, 'status' => 'pending',
            ]);
            $defaultInstrument = [
                'psychological' => 'dass21',
                'personality'   => 'bfpi',
                'career'        => 'career',
            ];
            // Multi-instrument mapping per category for the sequential stepper
            $categoryTestTypes = [
                'psychological' => ['dass21', 'phq9', 'gad7'],
                'personality'   => ['bfpi'],
                'career'        => ['career'],
            ];
            $categories = $entry->tests ?? ['psychological'];
            foreach ($categories as $index => $category) {
                $code = $index === 0 ? $entry->reference : app(GuidanceReferenceService::class)->reserve();
                $entry->guidanceAppointments()->create([
                    'applicant_id'      => $applicant->id,
                    'request_code'      => $code,
                    'student_id_number' => $entry->student_number,
                    'test_category'     => $category,
                    'test_type'         => $defaultInstrument[$category] ?? $category,
                    'test_types'        => $categoryTestTypes[$category] ?? [$defaultInstrument[$category] ?? $category],
                    'student_status'    => $entry->student_status,
                    'status'            => 'Pending Payment',
                    'payment_slip_path' => null,
                ]);
            }
            return $entry;
        });
    }

    public function uploadReceipt(string $reference, UploadedFile $receipt, ?string $orNumber = null, ?string $orDate = null): void
    {
        $path = $receipt->store('guidance-receipts', 'local');
        abort_unless($path, 503, 'Receipt could not be stored. Please retry.');
        try {
            DB::transaction(function () use ($reference, $path, $orNumber, $orDate) {
                $entry = ServiceRequest::where('reference', $reference)->lockForUpdate()->first();
                if (!$entry) {
                    $appointment = \App\Models\GuidanceAppointment::whereNull('batch_id')->where('request_code', $reference)->lockForUpdate()->firstOrFail();
                    abort_unless($appointment->status === 'Pending Payment' && !$appointment->payment_slip_path, 409, 'This request is not awaiting payment.');
                    $appointment->update([
                        'payment_slip_path' => $path,
                        'or_number' => $orNumber,
                        'or_date' => $orDate,
                        'status' => 'Receipt Uploaded',
                        'appointment_at' => now(),
                    ]);
                    return;
                }
                $appointments = $entry->guidanceAppointments()->lockForUpdate()->get();
                abort_if($appointments->isEmpty(), 404);
                if ($entry->proof_path || $entry->status !== 'pending' || $appointments->contains(fn ($item) => $item->status !== 'Pending Payment')) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['payment_slip' => 'A receipt has already been uploaded or this request is no longer awaiting payment.']);
                }
                $uploadedAt = now();
                $entry->guidanceAppointments()->update([
                    'payment_slip_path' => $path,
                    'or_number' => $orNumber,
                    'or_date' => $orDate,
                    'status' => 'Receipt Uploaded',
                    'appointment_at' => $uploadedAt,
                ]);
                $entry->update([
                    'proof_path' => $path,
                    'or_number' => $orNumber,
                    'or_date' => $orDate,
                    'status' => 'proof_review',
                    'scheduled_at' => $uploadedAt,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
