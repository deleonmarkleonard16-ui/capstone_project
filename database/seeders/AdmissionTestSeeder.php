<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\SessionApplicant;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdmissionTestSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@psu-scc.test'],
            [
                'name' => 'Admission Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@psu-scc.test'],
            [
                'name' => 'Admission Staff',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );

        $applicants = collect([
            [
                'application_number' => 'PSUSCC-2026-0001',
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'last_name' => 'Rivera',
                'gender' => 'Female',
                'email' => 'maria.rivera@example.com',
                'contact_number' => '09171234567',
                'status' => 'approved',
            ],
            [
                'application_number' => 'PSUSCC-2026-0002',
                'first_name' => 'Joshua',
                'middle_name' => 'Dela Cruz',
                'last_name' => 'Mendoza',
                'gender' => 'Male',
                'email' => 'joshua.mendoza@example.com',
                'contact_number' => '09181234567',
                'status' => 'approved',
            ],
            [
                'application_number' => 'PSUSCC-2026-0003',
                'first_name' => 'Angela',
                'middle_name' => 'Lopez',
                'last_name' => 'Torres',
                'gender' => 'Female',
                'email' => 'angela.torres@example.com',
                'contact_number' => '09191234567',
                'status' => 'approved',
            ],
        ])->map(fn (array $data) => Applicant::updateOrCreate(
            ['application_number' => $data['application_number']],
            $data
        ));

        $session = TestSession::updateOrCreate(
            ['title' => 'Admission Test Morning Batch'],
            [
                'exam_date' => now()->toDateString(),
                'start_time' => now()->subMinutes(30)->format('H:i'),
                'end_time' => now()->addHours(2)->format('H:i'),
                'qr_token' => Str::uuid()->toString(),
                'qr_code_path' => null,
                'started_at' => null,
                'duration_minutes' => 40,
                'room' => 'Testing Room A',
                'status' => 'scheduled',
            ]
        );

        $session->update([
            'qr_code_path' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='
                .rawurlencode($session->checkinUrl()),
        ]);

        foreach ($applicants as $applicant) {
            SessionApplicant::updateOrCreate(
                [
                    'test_session_id' => $session->id,
                    'applicant_id' => $applicant->id,
                ],
                [
                    'is_present' => false,
                ]
            );

            AnswerSheet::firstOrCreate(
                [
                    'test_session_id' => $session->id,
                    'applicant_id' => $applicant->id,
                ],
                [
                    'is_locked' => false,
                ]
            );
        }

        AnswerKey::updateOrCreate(
            ['test_session_id' => $session->id],
            array_merge(
                ['passing_score' => 60],
                collect(range(1, 80))
                    ->mapWithKeys(fn (int $number) => ["q{$number}" => ['A', 'B', 'C', 'D'][($number - 1) % 4]])
                    ->all()
            )
        );
    }
}
