<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class GuidanceTestingController extends Controller
{
    public function __invoke(): View
    {
        return view('staff.guidance.index', [
            'psychologicalTests' => [
                [
                    'name' => 'PHQ-9',
                    'subtitle' => 'Depression screening',
                    'description' => 'Planned for guidance screening and follow-up support for students who may need early intervention.',
                    'status' => 'Planned UI',
                    'color' => 'primary',
                ],
                [
                    'name' => 'GAD-7',
                    'subtitle' => 'Generalized Anxiety Disorder assessment',
                    'description' => 'Prepared as part of the psychological testing group for anxiety screening and related guidance office recommendations.',
                    'status' => 'Planned UI',
                    'color' => 'info',
                ],
                [
                    'name' => 'DASS-21',
                    'subtitle' => 'Depression, Anxiety, and Stress Scale',
                    'description' => 'Designed for emotional assessment reporting with recommendation outcomes such as fit for deployment, fit for deployment with reservation, and for counseling.',
                    'status' => 'Planned UI',
                    'color' => 'warning',
                ],
            ],
            'specializedTests' => [
                [
                    'name' => 'Personality Test',
                    'subtitle' => 'For field study and request-based testing',
                    'description' => 'UI placeholder for students or clients who request a personality assessment through the guidance office.',
                    'status' => 'Planned UI',
                    'color' => 'info',
                ],
                [
                    'name' => 'Career Test',
                    'subtitle' => 'For shifters and career guidance',
                    'description' => 'Prepared for students who plan to shift programs and need a career-focused recommendation flow.',
                    'status' => 'Planned UI',
                    'color' => 'success',
                ],
            ],
            'recommendations' => [
                'Fit for Deployment',
                'Fit for Deployment with Reservation',
                'For Counseling',
            ],
            'recentSubmissions' => \Illuminate\Support\Facades\Schema::hasTable('guidance_test_submissions')
                ? \App\Models\GuidanceTestSubmission::with('serviceRequest')->latest('completed_at')->take(20)->get()
                : collect(),
        ]);
    }
}
