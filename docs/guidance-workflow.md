# Guidance testing workflow

## Implementation

The existing Laravel application now connects these steps:

1. Portal category dropdown (Psychological Assessment, Personality Test or Career Test; no contact fields) → appointment → short `G-XXXX` reference and automatic `#track` display. Psychological appointments contain DASS-21, PHQ-9 and GAD-7. Older tracking references remain valid.
2. Tracking shows a cashier stub that can be printed or saved using the browser print dialog. Receipt upload sets `Receipt Uploaded` and records the current appointment time.
3. Admin/staff open **Review Details**, a two-column Bootstrap modal. The left side shows the profile, reference, status and receipt without contact information; the right side shows the active QR/direct link or completed scores and scrollable raw JSON. **Verify & Generate QR** updates the modal through AJAX. No paper-instrument selection, manual appointment time or venue is required. The database permits one QR per appointment; repeat approval does not issue another pass.
4. Staff queues refresh every five seconds. Student tracking polls every ten seconds and renders the QR image and direct exam link after approval. Unchanged tracking results preserve the receipt file selection. Polling pauses when hidden, and tracking backs off on rate limiting.
5. The assessment proceeds through DASS-21 → PHQ-9 → GAD-7. Answer changes are saved in order to the server. Reloading restores saved answers and the original remaining time. Fullscreen/focus loss locks answer entry without stopping the deadline.
6. Submission or expiry writes raw answers and severity summaries once, invalidates the QR, and archives the completed appointment. Incomplete instruments are marked incomplete and are not scored as zero.
7. Staff can review individual raw item choices, scores and severity labels; archive/restore completed appointments in bulk; and export filtered archived records as CSV or PDF. Restoring does not reopen an exam or reactivate a QR.

## Main files

| Component | Files |
|---|---|
| Schema and model | `database/migrations/2026_09_18_000006_add_guidance_archive_flags.php`, `app/Models/GuidanceAppointment.php` |
| Short references | `database/migrations/2026_09_18_000007_create_guidance_reference_codes.php`, `app/Services/GuidanceReferenceService.php` |
| Queue and archive | `app/Http/Controllers/AdminGuidanceController.php`, `app/Services/GuidanceQueueService.php`, `resources/views/guidance/dashboard.blade.php`, `resources/views/guidance/queue.blade.php` |
| PDF export | `app/Services/GuidanceArchivePdfService.php`, `resources/views/guidance/archive-print.blade.php`, Composer dependency `dompdf/dompdf` |
| Analytics | `app/Http/Controllers/AnalyticsController.php`, `app/Services/GuidanceAnalyticsService.php`, `resources/views/guidance/analytics.blade.php`, `resources/views/guidance/analytics-data.blade.php` |
| Assessment | `app/Services/GuidanceAssessmentSessionService.php`, `app/Services/GuidanceTestScoringService.php`, `resources/views/guidance/take.blade.php`, `public/js/guidance-assessment.js` |
| Polling | `public/js/guidance-queue.js`, `public/js/guidance-tracking.js` |
| Review | `resources/views/guidance/results.blade.php`, `app/Models/GuidanceTestResponse.php` |
| Live review frame | `resources/views/guidance/review-modal.blade.php`, `resources/views/guidance/review-frame.blade.php`, `public/js/guidance-review.js` |

Staff navigation exposes the queue, analytics, and archive. Their canonical routes are `/staff/guidance-testing`, `/staff/guidance-appointments/analytics`, and `/staff/guidance-appointments/archive`. All archive, export, receipt and analytics actions require an authenticated admin/staff account. Sensitive responses disable caching.

New references reserve a unique `G-` plus four uppercase letters/digits in a shared database registry. Collisions retry using transaction savepoints; a failed request rolls back the reservation. The registry prevents collisions between parent request references and secondary appointment references. References are not recycled by archive/restore. Existing reference columns retain their lengths to avoid truncating historical codes.

Career uses the existing six-item RIASEC questionnaire approved by the user: Realistic, Investigative, Artistic, Social, Enterprising and Conventional, rated from 1 (Not interested) through 5 (Extremely interested). Scores are the individual trait ratings; the top three are ranked in descending order, with ties listed in original RIASEC order. All tied scores remain visible. The questionnaire uses the same receipt, QR, deadline, saved-draft and result-review workflow, with one Career section instead of three psychological sections.

Personality scoring remains pending. Requests and receipts can be recorded, but QR generation stays disabled until the approved BFPI scoring key is configured. Career interest levels are not included in psychological severity or red-flag counts.

Analytics aggregate JSON severity values in SQL, with a derived table compatible with MySQL strict grouping and SQLite. DASS-21 distributions are separate for depression, anxiety and stress. Archived assessments remain in the historical population and alert panel. Old PHQ-9 records with totals but no severity are interpreted at read time, preserving stored records.

## Deployment

```sh
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan view:cache
```

The additive archive migration has already been applied to the current local database. It flags historical completed appointments as archived without removing records. Other environments must run migrations normally.

Set `APP_URL` to the externally reachable application URL and serve via HTTPS in deployment. QR/direct links use Laravel's configured request origin; they are not hardcoded to a developer's localhost address.

Configure the operating system to run `php artisan schedule:run` every minute, with this repository as its working directory. On Windows, use Task Scheduler with the PHP executable and the full path to `artisan`. For foreground local development, use:

```sh
php artisan schedule:work
```

The existing scheduled `guidance:expire` command finalizes overdue saved drafts even after a browser closes. Every assessment mutation also checks the server deadline; the browser cannot extend it. The scheduler can record final completion up to one minute after the deadline, but answers are not accepted after the deadline. Unsaved answers during a network outage cannot be recovered server-side.

`storage` and `bootstrap/cache` must be writable by PHP. PDF export uses a private font/temp cache and disables remote resources, PHP and JavaScript. PDF exports are limited to 500 filtered appointments; CSV streams larger exports. Cashier stubs continue to use Print / Save Stub.

## Scoring and browser limitations

PHQ-9 uses Minimal, Mild, Moderate, Moderately Severe and Severe; GAD-7 uses Minimal, Mild, Moderate and Severe. These labels are preserved instead of inventing an Extremely Severe category for those instruments. The PHQ-9 thresholds follow the [original validation paper](https://onlinelibrary.wiley.com/doi/10.1046/j.1525-1497.2001.016009606.x).

DASS-21 retains the application's raw subscale sums (0–21) and equivalent raw-score severity thresholds. To compare with full DASS norms, multiply each raw subscale sum by two, as described in the [UNSW DASS scoring FAQ](https://dass.psy.unsw.edu.au/DASSFAQ.htm). Reports explicitly identify the raw-score convention. Screening results support counselor review and do not constitute diagnoses.

Fullscreen, focus, navigation and keyboard handlers provide browser-level restrictions. A normal webpage cannot prevent operating-system screenshots, force application focus, or guarantee that a student cannot switch applications. The server protects the deadline, sequence validation, scoring and QR replay independently of these browser controls.

## Verification

```sh
php artisan test
node --check public/js/guidance-assessment.js
node --check public/js/guidance-tracking.js
node --check public/js/guidance-queue.js
node --check public/js/guidance-review.js
```

Browser regression checks run the actual client scripts in headless Chrome against controlled API responses:

```sh
npm install --prefix storage/app/testing-browser --no-save --package-lock=false playwright
node tests/browser/guidance-workflow.cjs
```

They cover recurring tracking polls, preservation of selected receipt files, automatic QR display, queue refresh/search, receipt preview after refresh, open review preservation, sequential answer entry, saving locked answers and deadline completion. PHP feature tests cover authentication, filtering, receipt verification, scores, draft restoration, timeout finalization, QR replay, archive restore, JSON analytics and actual CSV/PDF output. Analytics SQL was also checked against the local MySQL database.

## Dependency audit

The runtime dependency audit on this implementation reported existing advisories affecting Laravel, Guzzle, CommonMark and several Symfony packages. No advisories were reported for the newly installed PDF library or its new dependencies. The application should not be considered cleared for production deployment until the existing runtime dependencies are updated and retested. This feature change does not perform a general dependency upgrade.
