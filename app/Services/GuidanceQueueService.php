<?php

namespace App\Services;

use App\Models\GuidanceAppointment;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuidanceQueueService
{
    public function filters(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(GuidanceAppointment::STATUSES)],
            'test_type' => ['nullable', Rule::in(array_keys(GuidanceCategories::LABELS))],
            'course' => ['nullable', Rule::in(array_keys(\App\Support\CourseCatalog::allOptions()))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }

    public function query(array $filters, bool $archived): Builder
    {
        $query = GuidanceAppointment::query()->whereNull('batch_id')->where('is_archived', $archived);
        if ($search = trim($filters['q'] ?? '')) {
            // Every word must match, so both "Maria Santos" and "Santos Maria" work.
            foreach (preg_split('/\s+/', $search) as $word) {
                $query->where(function (Builder $query) use ($word) {
                    $like = '%'.$word.'%';
                    $query->where('request_code', 'like', $like)
                        ->orWhere('student_id_number', 'like', $like)
                        ->orWhere('origin_section', 'like', $like)
                        ->orWhereHas('applicant', fn (Builder $applicant) => $applicant
                            ->where('first_name', 'like', $like)->orWhere('middle_name', 'like', $like)->orWhere('last_name', 'like', $like))
                        ->orWhereHas('serviceRequest', fn (Builder $entry) => $entry
                            ->where('student_number', 'like', $like)->orWhere('reference', 'like', $like))
                        ->orWhereHas('sourceBatch', fn (Builder $batch) => $batch->where('batch_name', 'like', $like)->orWhere('year_section', 'like', $like));
                });
            }
        }
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['course'])) $query->where(fn (Builder $q) => $q->where('origin_course', $filters['course'])
            ->orWhereHas('serviceRequest', fn (Builder $r) => $r->where('course', $filters['course'])));
        \App\Support\TableFilters::dates($query, $filters);
        if (!empty($filters['test_type'])) {
            $query->where('test_category', $filters['test_type']);
        }
        return $query;
    }

    public function archive(array $ids, bool $archived): void
    {
        DB::transaction(function () use ($ids, $archived) {
            $parents = GuidanceAppointment::whereKey($ids)->whereNotNull('service_request_id')->pluck('service_request_id')->unique()->sort();
            ServiceRequest::whereKey($parents)->orderBy('id')->lockForUpdate()->get();
            $appointments = GuidanceAppointment::whereKey($ids)->orderBy('guidance_appointment_id')->lockForUpdate()->get();
            abort_unless($appointments->count() === count($ids), 422, 'One or more selected requests no longer exist.');
            abort_if($appointments->contains(fn ($entry) => $entry->status !== 'Completed'), 422, 'Only completed assessments can be archived or restored.');
            GuidanceAppointment::whereKey($ids)->update(['is_archived' => $archived, 'archived_at' => $archived ? now() : null]);
            foreach ($parents as $id) {
                $allArchived = !GuidanceAppointment::where('service_request_id', $id)->where('is_archived', false)->exists();
                ServiceRequest::whereKey($id)->update(['archived_at' => $allArchived ? now() : null]);
            }
        }, 3);
    }
}
