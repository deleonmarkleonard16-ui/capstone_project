<?php

namespace App\Services;

use App\Models\GuidanceAppointment;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuidanceAssessmentSessionService
{
    public const DURATION = 600;

    public function __construct(private GuidanceTestScoringService $scoring) {}

    // All mutations lock parent -> appointment -> QR, including receipt verification.
    public function mutate(GuidanceAppointment $appointment, callable $callback, bool $continueAfterAdvance = false): array
    {
        return DB::transaction(function () use ($appointment, $callback, $continueAfterAdvance) {
            if ($appointment->batch_id) \App\Models\GuidanceTestBatch::whereKey($appointment->batch_id)->lockForUpdate()->firstOrFail();
            if ($appointment->service_request_id) ServiceRequest::whereKey($appointment->service_request_id)->lockForUpdate()->firstOrFail();
            $locked = GuidanceAppointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $qr = $locked->qrCode()->lockForUpdate()->firstOrFail();
            abort_unless($qr->is_active && in_array($locked->status, ['Approved', 'In-Progress']), 410, 'This pass is no longer active.');
            $advanced = false;
            while ($locked->expires_at && now()->greaterThanOrEqualTo($locked->expires_at)) {
                $advanced = true;
                $result = $this->advance($locked, true);
                if ($result['status'] === 'Completed') return $result;
            }
            // Ignore stale writes belonging to a section that just expired.
            if ($advanced && !$continueAfterAdvance) return $this->state($locked);
            return $callback($locked);
        }, 3);
    }

    public function terminate(GuidanceAppointment $appointment, int $staffId, string $reason): array
    {
        return DB::transaction(function () use ($appointment, $staffId, $reason) {
            if ($appointment->batch_id) \App\Models\GuidanceTestBatch::whereKey($appointment->batch_id)->lockForUpdate()->firstOrFail();
            if ($appointment->service_request_id) ServiceRequest::whereKey($appointment->service_request_id)->lockForUpdate()->firstOrFail();
            $locked = GuidanceAppointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === 'Completed') return ['status' => 'Completed'];
            abort_unless($locked->status === 'In-Progress', 409, 'Only a running assessment can be terminated.');
            $locked->qrCode()->lockForUpdate()->firstOrFail();
            $locked->update(['terminated_at' => now(), 'terminated_by' => $staffId, 'termination_reason' => $reason]);
            return $this->finish($locked, $locked->draft_answers ?? [], false);
        }, 3);
    }

    public function terminateForViolation(GuidanceAppointment $appointment, string $reason = 'Terminated - Violation'): array
    {
        if ($appointment->status === 'Completed') {
            return ['status' => 'Completed', 'strike_count' => $appointment->strike_count, 'terminated' => true];
        }
        $appointment->update([
            'terminated_at' => now(),
            'termination_reason' => $reason,
        ]);
        $state = $this->finish($appointment, $appointment->draft_answers ?? [], false);
        $state['strike_count'] = $appointment->strike_count;
        $state['terminated'] = true;
        $state['termination_reason'] = $reason;
        return $state;
    }

    public function state(GuidanceAppointment $appointment): array
    {
        return ['status' => $appointment->status, 'expires_at' => $appointment->expires_at?->toIso8601String(),
            'remaining_seconds' => $appointment->expires_at ? max(0, (int) ceil(now()->diffInSeconds($appointment->expires_at, false))) : self::DURATION,
            'section_index' => $appointment->section_index, 'answers' => $appointment->draft_answers ?? []];
    }

    public function sections(GuidanceAppointment $appointment): array
    {
        $sections = [];
        foreach ($appointment->testTypes() as $test) {
            $definition = $this->scoring->definition($test);
            $sections[] = ['test' => $test, 'label' => in_array($test, ['dass21', 'phq9', 'gad7'], true) ? 'Psychological Assessment' : GuidanceTestScoringService::LABELS[$test], 'items' => range(1, $definition['items'])];
        }
        return $sections;
    }

    public function start(GuidanceAppointment $appointment): array
    {
        return $this->mutate($appointment, function ($locked) {
            foreach ($locked->testTypes() as $test) $this->scoring->definition($test);
            if (!$locked->started_at) {
                $started = now();
                $locked->update(['started_at' => $started, 'expires_at' => $started->copy()->addSeconds(self::DURATION), 'status' => 'In-Progress']);
            }
            return $this->state($locked);
        });
    }

    public function save(GuidanceAppointment $appointment, array $answers, bool $submit = false, ?int $sectionIndex = null): array
    {
        return $this->mutate($appointment, function ($locked) use ($answers, $submit, $sectionIndex) {
            abort_unless($locked->started_at && $locked->status === 'In-Progress', 403, 'Start this assessment first.');
            abort_if($sectionIndex !== null && $sectionIndex !== $locked->section_index, 409, 'This section has already advanced. Refresh the assessment.');
            $normalized = $this->validateAnswers($locked, $answers, $submit);
            // Draft updates are full snapshots; the browser serializes writes to prevent reordering.
            $locked->update(['draft_answers' => $normalized]);
            if ($submit) return $this->advance($locked, false);
            return $this->state($locked);
        });
    }

    private function validateAnswers(GuidanceAppointment $appointment, array $answers, bool $complete): array
    {
        $tests = $appointment->testTypes();
        // Accept the historical flat answer format only for old single-test appointments.
        if (!$appointment->test_types && (!isset($answers[$tests[0]]))) $answers = [$tests[0] => $answers];
        $rules = ['answers' => ['present', 'array:'.implode(',', $tests)]];
        $section = $this->sections($appointment)[$appointment->section_index];
        $saved = $appointment->draft_answers ?? [];
        foreach ($tests as $test) {
            $definition = $this->scoring->definition($test);
            $keys = range(1, $definition['items']);
            $rules["answers.$test"] = [$complete && $test === $section['test'] ? 'required' : 'sometimes', 'array:'.implode(',', $keys)];
            foreach ($keys as $item) {
                $current = $test === $section['test'] && in_array($item, $section['items']);
                $rules["answers.$test.$item"] = [$complete && $current ? 'required' : 'sometimes', 'integer', 'between:'.$definition['min'].','.$definition['max']];
                if (!$current && isset($answers[$test][$item]) && (string) $answers[$test][$item] !== (string) ($saved[$test][$item] ?? '')) {
                    throw ValidationException::withMessages(['answers' => 'Only the current section can be changed.']);
                }
            }
        }
        Validator::make(['answers' => $answers], $rules)->validate();
        return array_replace_recursive($saved, $answers);
    }

    private function advance(GuidanceAppointment $appointment, bool $timedOut): array
    {
        $sections = $this->sections($appointment);
        $history = $appointment->section_history ?? [];
        $history[] = ['section' => $appointment->section_index, 'timed_out' => $timedOut, 'ended_at' => ($timedOut ? $appointment->expires_at : now())->toIso8601String()];
        $appointment->section_history = $history;
        if ($appointment->section_index + 1 >= count($sections)) {
            $appointment->save();
            return $this->finish($appointment, $appointment->draft_answers ?? [], in_array(true, array_column($history, 'timed_out'), true));
        }
        $appointment->update(['section_index' => $appointment->section_index + 1, 'expires_at' => ($timedOut ? $appointment->expires_at->copy() : now())->addSeconds(self::DURATION)]);
        return $this->state($appointment);
    }

    private function finish(GuidanceAppointment $appointment, array $answers, bool $timedOut): array
    {
        $summaries = [];
        foreach ($appointment->testTypes() as $test) {
            $definition = $this->scoring->definition($test);
            $values = $answers[$test] ?? [];
            $complete = count($values) === $definition['items'];
            $summaries[$test] = ['completion' => $complete ? 'Completed' : 'Incomplete', 'answered_items' => count($values), 'total_items' => $definition['items']];
            if ($complete) $summaries[$test] += $this->scoring->score($test, $values);
            else $summaries[$test]['interpretation'] = ['review' => 'Incomplete; no score calculated. Unanswered items were not treated as zero.'];
        }
        $summary = ['tests' => $summaries, 'sections' => $appointment->section_history, 'strike_count' => $appointment->strike_count,
            'terminated_at' => $appointment->terminated_at?->toIso8601String(), 'terminated_by' => $appointment->terminated_by, 'termination_reason' => $appointment->termination_reason,
            'timed_out' => $timedOut, 'started_at' => $appointment->started_at?->toIso8601String(),
            'deadline' => $appointment->expires_at?->toIso8601String(), 'submitted_at' => now()->toIso8601String()];
        $storedAnswers = $answers;
        if (!$appointment->test_types) {
            $test = $appointment->test_type;
            $summary += $summaries[$test];
            $storedAnswers = $answers[$test] ?? [];
        }
        $appointment->response()->create(['applicant_id' => $appointment->applicant_id, 'answers' => $storedAnswers, 'score_summary' => $summary]);
        $appointment->update(['status' => 'Completed', 'attendance_status' => 'Completed', 'draft_answers' => null, 'is_archived' => true, 'archived_at' => now()]);
        if ($appointment->batch_id) app(GuidanceBatchService::class)->completeIfDone($appointment->batch);
        $appointment->qrCode()->update(['is_active' => false]);
        if ($appointment->service_request_id) {
            $entry = ServiceRequest::findOrFail($appointment->service_request_id);
            $remaining = $entry->guidanceAppointments()->where('status', '!=', 'Completed')->exists();
            $hasUnarchived = $entry->guidanceAppointments()->where('is_archived', false)->exists();
            $entry->update(['status' => $remaining ? 'processing' : 'completed', 'archived_at' => $hasUnarchived ? null : now()]);
        }
        return ['status' => 'Completed', 'timed_out' => $timedOut, 'message' => 'Your saved answers have been recorded for counselor review.'];
    }

    public function expireDue(): int
    {
        $count = 0;
        GuidanceAppointment::where('status', 'In-Progress')->where('expires_at', '<=', now())->orderBy('guidance_appointment_id')
            ->chunkById(100, function ($appointments) use (&$count) {
                foreach ($appointments as $appointment) {
                    try {
                        $this->mutate($appointment, fn ($locked) => $this->state($locked));
                        $count++;
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                        if ($exception->getStatusCode() !== 410) throw $exception;
                    }
                }
            }, 'guidance_appointment_id');
        return $count;
    }
}
