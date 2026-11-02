<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLogActivityDescriptionTest extends TestCase
{
    #[DataProvider('activityDescriptions')]
    public function test_it_returns_a_user_friendly_activity_description(
        string $route,
        string $method,
        string $expected
    ): void {
        $log = new AuditLog([
            'route' => $route,
            'action' => str_replace('.', ' ', ucfirst($route)),
            'method' => $method,
        ]);

        $this->assertSame($expected, $log->activityDescription());
    }

    public static function activityDescriptions(): array
    {
        return [
            'login' => ['login.store', 'POST', 'Logged in to the system'],
            'applicant check-in' => ['admin.admission.sessions.checkin-applicant', 'POST', 'Checked in an applicant'],
            'user deletion' => ['admin.settings.user.destroy', 'DELETE', 'Deleted a user account'],
            'generic update' => ['admin.example-record.update', 'PATCH', 'Updated example record'],
            'generic submit' => ['guidance.submit', 'POST', 'Submitted guidance'],
        ];
    }
}
