<?php

namespace Database\Seeders;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Support\CourseCatalog;
use Illuminate\Database\Seeder;

class AdmissionApplicantSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = AdmissionCycle::active() ?? AdmissionCycle::first();
        if (!$cycle) {
            $cycle = AdmissionCycle::create([
                'name' => 'AY ' . date('Y') . '-' . (date('Y') + 1),
                'cycle_name' => 'AY ' . date('Y') . '-' . (date('Y') + 1),
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'is_active' => true,
                'status' => 'Active',
                'passing_stanine' => 4,
                'total_items' => 80,
                'exam_weight' => 60,
                'gwa_weight' => 20,
                'interview_weight' => 20,
            ]);
        }

        $specialGroups = ['N/A', '4Ps', 'OSY', 'IP', 'PWD', 'SP'];
        $cmflBrackets = ['N/A', '10,000 below', '10,001 to 20,000', '20,001 to 30,000', '30,001 to 50,000', '50,001 and above'];
        $courses = array_keys(CourseCatalog::activeOptions());
        $firstNames = ['Pedro', 'Ana', 'Jose', 'Clara', 'Gabriel', 'Sofia', 'Mark', 'Angela', 'Joshua', 'Maria', 'John', 'Bea'];
        $lastNames = ['Gonzales', 'Ramos', 'Mendoza', 'Flores', 'Castillo', 'Villanueva', 'Castro', 'Bautista', 'Aquino', 'Santos', 'Dela Cruz', 'Torres'];
        $middleNames = ['Reyes', 'Garcia', 'Cruz', 'Torres', 'Flores', 'Castillo', 'Villanueva', 'Ramos', 'Castro', 'Santos', 'Lopez', 'Navarro'];

        for ($i = 0; $i < count($firstNames); $i++) {
            $appNum = 'CAT-' . date('y') . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            AdmissionApplicant::updateOrCreate(
                ['application_number' => $appNum],
                [
                    'admission_cycle_id' => $cycle->id,
                    'batch_group' => 'Batch ' . (($i % 3) + 1),
                    'first_name' => $firstNames[$i],
                    'middle_name' => $middleNames[$i],
                    'last_name' => $lastNames[$i],
                    'course_choice' => $courses[$i % count($courses)] ?? 'BSIT',
                    'sex' => $i % 2 === 0 ? 'Male' : 'Female',
                    'special_group' => $specialGroups[$i % count($specialGroups)],
                    'cmfl' => $cmflBrackets[$i % count($cmflBrackets)],
                    'gwa' => round(82.00 + (mt_rand(0, 1500) / 100), 2),
                ]
            );
        }
    }
}
