<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'action', 'method', 'route',
        'subject', 'response_status', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return ['subject' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityDescription(): string
    {
        $descriptions = [
            'login.store' => 'Logged in to the system',
            'logout' => 'Logged out of the system',
            'password.update' => 'Reset an account password',
            'api.notifications.read' => 'Marked a notification as read',
            'admin.notifications.read' => 'Marked a notification as read',
            'staff.notifications.read' => 'Marked a notification as read',
            'admin.requests.update' => 'Updated a service request',
            'staff.requests.update' => 'Updated a service request',
            'admin.analytics.intervention' => 'Updated an intervention record',
            'staff.analytics.intervention' => 'Updated an intervention record',
            'admin.analytics.proctor-action' => 'Performed a proctoring action',
            'staff.analytics.proctor-action' => 'Performed a proctoring action',
            'admin.analytics.toggle-proctor-override' => 'Changed the proctor override setting',
            'staff.analytics.toggle-proctor-override' => 'Changed the proctor override setting',
            'admin.settings.course' => 'Saved course information',
            'admin.settings.course.toggle' => 'Changed a course status',
            'admin.settings.user' => 'Saved a user account',
            'admin.settings.user.destroy' => 'Deleted a user account',
            'admin.admission.cycles.save' => 'Saved an admission cycle',
            'admin.admission.cycles.activate' => 'Activated an admission cycle',
            'admin.admission.cycles.archive' => 'Archived an admission cycle',
            'admin.admission.cycles.complete' => 'Completed an admission cycle',
            'admin.admission.quotas.save' => 'Saved admission quotas',
            'admin.admission.answer-key.save' => 'Saved the admission test answer key',
            'admin.admission.batches.store' => 'Created an admission test batch',
            'admin.admission.batches.update' => 'Updated an admission test batch',
            'admin.admission.batches.destroy' => 'Deleted an admission test batch',
            'admin.admission.sessions.checkin-applicant' => 'Checked in an applicant',
            'admin.admission.sessions.mark-absent-applicant' => 'Marked an applicant as absent',
            'admin.admission.sessions.reassign-applicant' => 'Reassigned an applicant to another session',
            'admin.admission.sessions.reassign-absent-bulk' => 'Reassigned absent applicants to another session',
            'admin.admission.sessions.unterminate' => 'Restored a terminated applicant exam',
            'admin.admission.sessions.status' => 'Updated an admission test session status',
            'admin.admission.sessions.complete' => 'Completed an admission test session',
            'admin.admission.sessions.assign-range' => 'Assigned applicants to test sessions',
            'admin.admission.applicants.import' => 'Imported applicant records',
            'admin.admission.applicants.save' => 'Saved applicant information',
            'admin.admission.applicants.interview-score' => 'Saved an applicant interview score',
            'admin.admission.applicants.token' => 'Issued an applicant test token',
            'admin.admission.encode.submit' => 'Submitted an applicant paper answer sheet',
            'admin.admission.program-settings.save' => 'Saved admission program settings',
            'admin.admission-evaluation.store' => 'Saved an admission evaluation',
        ];

        if ($this->route && isset($descriptions[$this->route])) {
            return $descriptions[$this->route];
        }

        $activity = str((string) ($this->route ?: $this->action))
            ->replace(['.', '-', '_'], ' ')
            ->replaceMatches('/^(admin|staff)\s+/i', '')
            ->squish()
            ->lower();

        $routeAction = str((string) $this->route)->afterLast('.')->lower()->toString();
        $verb = match ($routeAction) {
            'store', 'save' => 'Saved',
            'update' => 'Updated',
            'destroy', 'delete' => 'Deleted',
            'submit' => 'Submitted',
            'import' => 'Imported',
            'archive', 'archive-selected' => 'Archived',
            'complete' => 'Completed',
            default => match (strtoupper((string) $this->method)) {
                'POST' => 'Submitted',
                'PUT', 'PATCH' => 'Updated',
                'DELETE' => 'Deleted',
                default => 'Accessed',
            },
        };

        if (in_array($routeAction, ['store', 'save', 'update', 'destroy', 'delete', 'submit', 'import', 'archive', 'archive-selected', 'complete'], true)) {
            $activity = $activity->beforeLast(' ');
        }

        return $verb.' '.$activity;
    }
}
