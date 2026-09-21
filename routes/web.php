<?php

use App\Http\Controllers\AnswerSheetController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamMonitoringController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\QrCheckinController;
use App\Http\Controllers\SessionApplicantController;
use App\Http\Controllers\TestSessionController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\PsychologicalRequestController;
use App\Http\Controllers\GuidanceAssessmentController;
use App\Http\Controllers\AdmissionEvaluationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portal');
Route::middleware(['auth', 'role:admin,staff'])->prefix('api/notifications')->name('api.notifications.')->group(function (): void {
    Route::post('/{notification}/read', [\App\Http\Controllers\GuidanceNotificationController::class, 'read'])->name('read');
    Route::post('/clear-module', [\App\Http\Controllers\GuidanceNotificationController::class, 'clearModule'])->name('clear-module');
});
Route::middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->group(function () {
    $batch = \App\Http\Controllers\GuidanceBatchController::class;
    Route::get('/test/batch-join/{token}', [$batch, 'join'])->name('guidance.batch.join');
    Route::post('/test/batch-join/{token}/verify', [$batch, 'verify'])->middleware('throttle:guidance-identity')->name('guidance.batch.verify');
    Route::post('/test/batch-join/{token}/receipt', [$batch, 'receipt'])->middleware('throttle:guidance-exam')->name('guidance.batch.receipt');
    Route::get('/test/batch-join/{token}/state', [$batch, 'state'])->middleware('throttle:guidance-exam')->name('guidance.batch.state');
    foreach (['staff', 'admin'] as $role) {
        Route::middleware(['auth', 'role:'.$role])->prefix($role.'/guidance-batches')->name($role.'.guidance-batches.')->group(function () use ($batch) {
            Route::get('/', [$batch, 'index'])->name('index');
            Route::post('/', [$batch, 'store'])->name('store');
            Route::post('/{batch}/action', [$batch, 'action'])->name('action');
            Route::get('/{batch}/qr', [$batch, 'qr'])->name('qr');
        });
    }
});
Route::get('/portal', [StudentPortalController::class, 'index'])->name('portal.index');
Route::middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->prefix('request/batch-join/{token}')->name('document.batch.')->group(function () {
    $controller = \App\Http\Controllers\DocumentRequestController::class;
    Route::get('/', [$controller, 'join'])->name('join');
    Route::post('/verify', [$controller, 'verify'])->middleware('throttle:guidance-identity')->name('verify');
    Route::post('/receipt', [$controller, 'receipt'])->middleware('throttle:guidance-exam')->name('receipt');
});
Route::post('/portal/requests', [StudentPortalController::class, 'store'])->middleware('throttle:10,1')->name('portal.store');
Route::get('/portal/requests', fn () => redirect('/portal'));
Route::post('/portal/track', [StudentPortalController::class, 'track'])->middleware('throttle:10,1')->name('portal.track');
Route::get('/portal/track', fn () => redirect('/portal#track'));
Route::post('/portal/psychological/receipt', [PsychologicalRequestController::class, 'upload'])->middleware('throttle:10,1')->name('portal.receipt');
Route::get('/portal/psychological/receipt', fn () => redirect('/portal#track'));

// Guidance online assessments (student-facing, no auth required — gated by reference)
Route::get('/portal/assessment/{reference}/{test}', fn () => redirect('/portal?service=good-moral#track')->with('portal_notice', 'Use your verified guidance QR pass to take an assessment.'))->name('guidance.test.show');
Route::post('/portal/assessment/{reference}/{test}', fn () => abort(410, 'Use a verified guidance QR pass.'))->name('guidance.test.submit');

Route::middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->group(function () {
    $controller = \App\Http\Controllers\GuidanceAppointmentController::class;
    Route::post('/api/test/log-strike', [\App\Http\Controllers\GuidanceSecurityController::class, 'store'])->middleware('throttle:guidance-strikes')->name('guidance.log-strike');
    Route::get('/test/take/{token}/complete', [$controller, 'complete'])->name('guidance.complete');
    Route::post('/api/submit-request', [StudentPortalController::class, 'store'])->middleware('throttle:10,1')->name('api.submit-request');
    Route::post('/api/upload-receipt', [\App\Http\Controllers\GuidanceTrackingController::class, 'uploadReceipt'])->middleware('throttle:10,1')->name('api.upload-receipt');
    Route::post('/api/track-request', \App\Http\Controllers\GuidanceTrackingController::class)->middleware('throttle:10,1')->name('api.track-request');
    Route::post('/portal/guidance/receipt', [\App\Http\Controllers\GuidanceTrackingController::class, 'uploadReceipt'])->middleware('throttle:10,1')->name('guidance.receipt');
    Route::get('/portal/guidance/request', [$controller, 'create'])->name('guidance.request');
    Route::post('/portal/guidance/request', [$controller, 'store'])->middleware('throttle:10,1')->name('guidance.request.store');
    Route::post('/portal/guidance/track', [$controller, 'track'])->middleware('throttle:10,1')->name('guidance.track');
    Route::get('/test/take/{token}', [$controller, 'take'])->middleware('throttle:guidance-exam')->name('guidance.take');
    Route::post('/test/take/{token}/start', [$controller, 'start'])->middleware('throttle:guidance-exam')->name('guidance.start');
    Route::post('/test/take/{token}/progress', [$controller, 'saveProgress'])->middleware('throttle:guidance-exam')->name('guidance.progress');
    Route::get('/test/take/{token}/state', [$controller, 'state'])->middleware('throttle:guidance-exam')->name('guidance.state');
    Route::post('/test/take/{token}', [$controller, 'submit'])->middleware('throttle:guidance-exam')->name('guidance.submit');
    foreach (['staff', 'admin'] as $role) Route::middleware(['auth', 'role:'.$role])->prefix($role.'/guidance-appointments')->name($role.'.guidance-appointments.')->group(function () {
        $controller = \App\Http\Controllers\AdminGuidanceController::class;
        Route::get('/', [$controller, 'index'])->name('index');
        Route::get('/archive', [$controller, 'index'])->name('archive');
        Route::get('/archive/export', [$controller, 'export'])->name('export');
        Route::post('/archive', [$controller, 'archiveSelected'])->name('archive-selected');
        Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/results', [$controller, 'results'])->name('results');
        Route::get('/completed', [$controller, 'completed'])->name('completed');
        Route::get('/security-incidents', [\App\Http\Controllers\GuidanceSecurityController::class, 'feed'])->name('security-incidents');
        Route::post('/{appointment}/terminate', [\App\Http\Controllers\GuidanceSecurityController::class, 'terminate'])->name('terminate');
        Route::get('/{appointment}/results', [$controller, 'showResults'])->name('show-results');
        Route::get('/{appointment}/career-report', \App\Http\Controllers\CareerReportController::class)->name('career-report');
        Route::get('/{appointment}/review', [$controller, 'review'])->name('review');
        Route::post('/{appointment}/verify', [$controller, 'verify'])->name('verify');
        Route::get('/{appointment}/receipt', [$controller, 'receipt'])->name('receipt');
    });
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/check-in/{token}', [QrCheckinController::class, 'show'])->name('checkin.show');
Route::post('/check-in/{token}', [QrCheckinController::class, 'verify'])->name('checkin.verify');
Route::get('/waiting', [QrCheckinController::class, 'waiting'])->name('checkin.waiting');
Route::get('/answer-sheet', [AnswerSheetController::class, 'show'])->name('answers.show');
Route::post('/answer-sheet/save-progress', [AnswerSheetController::class, 'saveProgress'])->name('answers.save-progress');
Route::post('/answer-sheet', [AnswerSheetController::class, 'submit'])->name('answers.submit');
Route::get('/answer-sheet/completed', [AnswerSheetController::class, 'complete'])->name('answers.complete');

Route::middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->prefix('admission/take/{token}')->name('admission.')->group(function () {
    $exam = \App\Http\Controllers\AdmissionExamController::class;
    Route::get('/', [$exam, 'take'])->name('take');
    Route::post('/', [$exam, 'submit'])->middleware('throttle:guidance-exam')->name('submit');
    Route::post('/strike', [$exam, 'strike'])->middleware('throttle:guidance-strikes')->name('strike');
});
Route::get('/admission/complete', fn () => view('admin.admission.complete'))->name('admission.complete');

foreach (['staff', 'admin'] as $role) Route::middleware(['auth', 'role:'.$role])->prefix($role)->name($role.'.')->group(function (): void {
    Route::get('/notifications', [\App\Http\Controllers\GuidanceNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\GuidanceNotificationController::class, 'read'])->name('notifications.read');
    Route::get('/notifications/{notification}/review', [\App\Http\Controllers\GuidanceNotificationController::class, 'review'])->name('notifications.review');
    Route::get('/requests/{serviceRequest}/details', [\App\Http\Controllers\GuidanceNotificationController::class, 'details'])->name('requests.details');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/psychological', [PsychologicalRequestController::class, 'index'])->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('psychological.index');
    Route::get('/psychological/batches', [\App\Http\Controllers\GuidanceBatchController::class, 'index'])->defaults('module', 'psychological')->name('psychological.batches');
    Route::post('/psychological/batches', [\App\Http\Controllers\GuidanceBatchController::class, 'store'])->defaults('module', 'psychological')->name('psychological.batches.store');
    Route::get('/psychological/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('psychological.analytics');
    Route::get('/psychological/archive', [PsychologicalRequestController::class, 'index'])->defaults('mode', 'archive')->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('psychological.archive');
    Route::patch('/psychological/{serviceRequest}', [PsychologicalRequestController::class, 'update'])->name('psychological.update');
    Route::get('/psychological/{serviceRequest}/receipt', [PsychologicalRequestController::class, 'proof'])->name('psychological.proof');

    Route::get('/guidance-testing', [\App\Http\Controllers\AdminGuidanceController::class, 'index'])->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('guidance.index');
    Route::get('/guidance-testing/submissions/{submission}', [GuidanceAssessmentController::class, 'staffReview'])->name('guidance.submission.show');
    Route::patch('/guidance-testing/submissions/{submission}/notes', [GuidanceAssessmentController::class, 'staffNotes'])->name('guidance.submission.notes');
    foreach (['good-moral', 'exit-form'] as $documentModule) {
        $document = \App\Http\Controllers\DocumentRequestController::class;
        Route::get('/'.$documentModule, [$document, 'index'])->defaults('module', $documentModule)->name($documentModule);
        Route::get('/'.$documentModule.'/batches', [$document, 'batches'])->defaults('module', $documentModule)->name($documentModule.'.batches');
        Route::post('/'.$documentModule.'/batches', [$document, 'store'])->defaults('module', $documentModule)->name($documentModule.'.batches.store');
        Route::get('/'.$documentModule.'/archive', [$document, 'index'])->defaults('module', $documentModule)->defaults('mode', 'archive')->name($documentModule.'.archive');
        Route::get('/'.$documentModule.'/analytics', [$document, 'analytics'])->defaults('module', $documentModule)->name($documentModule.'.analytics');
        Route::patch('/'.$documentModule.'/{serviceRequest}', [$document, 'update'])->defaults('module', $documentModule)->name($documentModule.'.update');
    }
    Route::get('/document-requests/{serviceRequest}/receipt', [\App\Http\Controllers\DocumentRequestController::class, 'proof'])->name('documents.proof');
    // Personality Module (Process & UI Parity)
    Route::get('/personality', [PsychologicalRequestController::class, 'index'])->defaults('module', 'personality')->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('personality.index');
    Route::get('/personality/batches', [\App\Http\Controllers\GuidanceBatchController::class, 'index'])->defaults('module', 'personality')->name('personality.batches');
    Route::post('/personality/batches', [\App\Http\Controllers\GuidanceBatchController::class, 'store'])->defaults('module', 'personality')->name('personality.batches.store');
    Route::get('/personality/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->defaults('module', 'personality')->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('personality.analytics');
    Route::get('/personality/archive', [PsychologicalRequestController::class, 'index'])->defaults('mode', 'archive')->defaults('module', 'personality')->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('personality.archive');
    Route::patch('/personality/{serviceRequest}', [PsychologicalRequestController::class, 'update'])->name('personality.update');
    Route::get('/personality/{serviceRequest}/receipt', [PsychologicalRequestController::class, 'proof'])->name('personality.proof');

    // Career Module (Process & UI Parity)
    Route::get('/career', [PsychologicalRequestController::class, 'index'])->defaults('module', 'career')->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('career.index');
    Route::get('/career/batches', [\App\Http\Controllers\GuidanceBatchController::class, 'index'])->defaults('module', 'career')->name('career.batches');
    Route::post('/career/batches', [\App\Http\Controllers\GuidanceBatchController::class, 'store'])->defaults('module', 'career')->name('career.batches.store');
    Route::get('/career/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->defaults('module', 'career')->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('career.analytics');
    Route::get('/career/archive', [PsychologicalRequestController::class, 'index'])->defaults('mode', 'archive')->defaults('module', 'career')->middleware(\App\Http\Middleware\PrivateGuidanceResponse::class)->name('career.archive');
    Route::patch('/career/{serviceRequest}', [PsychologicalRequestController::class, 'update'])->name('career.update');
    Route::get('/career/{serviceRequest}/receipt', [PsychologicalRequestController::class, 'proof'])->name('career.proof');

    // Backward compatibility aliases
    Route::get('/personality-queue', [PsychologicalRequestController::class, 'index'])->defaults('module', 'personality')->name('personality');
    Route::get('/career-queue', [PsychologicalRequestController::class, 'index'])->defaults('module', 'career')->name('career');
    Route::get('/testing-requests', [StudentPortalController::class, 'inbox'])->defaults('service', 'testing')->name('testing-requests');
    Route::patch('/requests/{serviceRequest}', [StudentPortalController::class, 'update'])->name('requests.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::prefix('admission')->name('admission.')->group(function () {
        $c = \App\Http\Controllers\AdmissionPipelineController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::post('/cycles/{cycle?}', [$c, 'cycle'])->name('cycles.save');
        Route::post('/cycles/{cycle}/activate', [$c, 'activate'])->name('cycles.activate');
        Route::post('/cycles/{cycle}/archive', [$c, 'archive'])->name('cycles.archive');
        Route::post('/cycles/{cycle}/complete', [$c, 'complete'])->name('cycles.complete');
        Route::post('/quotas', [$c, 'quota'])->name('quotas.save');
        Route::post('/answer-key', [$c, 'key'])->name('answer-key.save');
        Route::get('/masterlist', [$c, 'masterlist'])->name('masterlist');
        Route::get('/encoding-sheet', [$c, 'encodingSheet'])->name('encoding-sheet');
        Route::post('/encoding-sheet', [$c, 'saveEncodingSheet'])->name('encoding-sheet.save');
        Route::post('/applicants/import', [$c, 'import'])->name('applicants.import');
        Route::post('/applicants/{applicant?}', [$c, 'saveApplicant'])->name('applicants.save');
        Route::post('/applicants/{applicant}/token', [$c, 'issueToken'])->name('applicants.token');
        Route::get('/applicants/{applicant}/paper', [$c, 'paper'])->name('paper');
        Route::get('/scan-paper', [$c, 'scanner'])->name('scan-paper');
        Route::post('/scan-paper', [$c, 'scan'])->name('scan-paper.lookup');
        Route::get('/applicants/{applicant}/encode', [$c, 'encode'])->name('encode');
        Route::post('/applicants/{applicant}/encode', [$c, 'submitPaper'])->name('encode.submit');
        Route::get('/report', [$c, 'report'])->name('report');
        Route::get('/proctoring', fn () => view('admin.admission.incidents'))->name('proctoring');
        Route::get('/security-incidents', [\App\Http\Controllers\AdmissionExamController::class, 'incidents'])->name('incidents');
    });
    $settings = \App\Http\Controllers\GuidanceSettingsController::class;
    Route::get('/system-settings', [$settings, 'index'])->name('settings.index');
    Route::post('/system-settings/courses/{course?}', [$settings, 'course'])->name('settings.course');
    Route::post('/system-settings/courses/{course}/toggle', [$settings, 'toggleCourse'])->name('settings.course.toggle');
    Route::post('/system-settings/users/{user?}', [$settings, 'user'])->name('settings.user');
    Route::get('/archive', [\App\Http\Controllers\AdminArchiveController::class, 'index'])->name('archive');
    Route::view('/admission-cycle', 'staff.admission-cycle')->name('admission-cycle');
    Route::get('/admission-evaluation', [AdmissionEvaluationController::class, 'index'])->name('admission-evaluation.index');
    Route::post('/admission-evaluation', [AdmissionEvaluationController::class, 'store'])->name('admission-evaluation.store');
    Route::get('/admission-evaluation/passers', [AdmissionEvaluationController::class, 'passers'])->name('admission-evaluation.passers');

    Route::resource('applicants', ApplicantController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('/applicants/import', [ApplicantController::class, 'import'])->name('applicants.import');

    Route::resource('sessions', TestSessionController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);


    Route::get('/sessions/{session}/assignments', [SessionApplicantController::class, 'index'])->name('sessions.assignments.index');
    Route::post('/sessions/{session}/assignments', [SessionApplicantController::class, 'store'])->name('sessions.assignments.store');
    Route::delete('/sessions/{session}/assignments/{assignment}', [SessionApplicantController::class, 'destroy'])->name('sessions.assignments.destroy');
    Route::post('/sessions/{session}/regenerate-qr', [SessionApplicantController::class, 'regenerateQr'])->name('sessions.regenerate-qr');

    Route::get('/sessions/{session}/monitoring', [ExamMonitoringController::class, 'show'])->name('sessions.monitoring.show');
    Route::post('/sessions/{session}/start', [ExamMonitoringController::class, 'start'])->name('sessions.monitoring.start');
    Route::get('/sessions/{session}/stats', [ExamMonitoringController::class, 'stats'])->name('sessions.monitoring.stats');
    Route::get('/sessions/{session}/attendance', [AttendanceController::class, 'index'])->name('sessions.attendance.index');
    Route::get('/sessions/{session}/answer-key', [ExamResultController::class, 'editAnswerKey'])->name('sessions.answer-key.edit');
    Route::post('/sessions/{session}/answer-key', [ExamResultController::class, 'updateAnswerKey'])->name('sessions.answer-key.update');
    Route::get('/sessions/{session}/results', [ExamResultController::class, 'index'])->name('sessions.results.index');
    Route::get('/sessions/{session}/results/{answerSheet}', [ExamResultController::class, 'show'])->name('sessions.results.show');
});

Route::middleware(['auth', 'role:admin,staff'])->get('/dashboard', function () {
    return redirect()->route(auth()->user()->role.'.dashboard');
})->name('dashboard');
