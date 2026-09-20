# Dual guidance queues and section timers

This document supersedes the earlier single/global-timer workflow notes.

## Setup

Run `php artisan migrate`, then clear any previously cached configuration with
`php artisan optimize:clear`. Run the Laravel scheduler every minute
(`php artisan schedule:run` from the host scheduler). The existing
`guidance:expire` command advances overdue sections and finalizes the last section
even when the student has closed the browser. Deploy between assessment sessions;
previously running sessions have deadlines from the former timer implementation.

Use HTTPS for camera capture on student devices. Localhost also supports camera
capture. Denied or unavailable camera access leaves gallery upload available.
Images are limited to JPEG, PNG, or WebP, 5 MB, and 8000 pixels per dimension, and
are stored on the private `local` disk. The staff receipt endpoint requires an
admin or staff login. Do not expose that disk through a public storage link.

## Staff workflow

Open Guidance Testing. **Individual Request Queue** retains receipt verification,
short `G-XXXX` references, receipt previews, and the contact-free review frame.
**Bundled / Batch Queue** opens `/staff/guidance-batches`. Upload a CSV with
`student_id,first_name,middle_name,last_name`; the middle-name column is optional.
Student IDs remain strings, including leading zeros. Import rejects duplicate IDs,
missing required values, inconsistent CSV columns, or more than 500 rows before
creating any records. Batch name/reference, course, reason, and category apply to
every roster member.

Students scan the locally generated, printable batch QR and enter their ID and
full name. Matching is case-sensitive after trimming surrounding whitespace,
including the middle name when the roster contains one. A successful match grants
a four-hour session-bound receipt/polling capability for that roster entry. An
unverified browser cannot retrieve a student's assessment pass. Batch references
cannot be used in public individual tracking to bypass verification.

Receipt upload or webcam capture makes a student **Ready in Session**. Starting
the batch verifies the available receipts and starts every ready appointment in
one transaction, with the same start timestamp and first-section deadline.
Only ready students start; registration then closes. Waiting rooms poll every two
seconds and redirect to the assessment. Students enter fullscreen with a click
(browser APIs require a user gesture); this does not reset the shared deadline.
Staff should inspect receipts before starting the batch. Repeated start requests
do not create additional passes or reset deadlines.

Mark unstarted students **Absent**, or convert an absent appointment to an
individual request. Conversion keeps its reference, applicant, receipt, category,
and original batch link (`source_batch_id`), removes it from the active roster,
and lets it use the existing tracking, payment, and staff-verification workflow.
Staff can give the student the reference shown in the roster before conversion.
Its original course and reason remain visible in staff review.

When all remaining roster appointments are Completed or Absent, the batch becomes
Completed and appears in Archived Batches. Manual archive marks all remaining
unstarted roster entries Absent; a running assessment prevents manual archive.
Archived batches retain metadata, attendance, receipts, used inactive QR passes,
scores, and raw answers. Archiving never deletes responses or reactivates passes.

## Assessment engine

- Psychological Assessment: DASS-21, PHQ-9, GAD-7, each with 600 seconds.
- Personality Test: the configured BFPI battery, with 600 seconds.
- Career Test: one 600-second section per configured RIASEC interest scale.

The server owns `section_index`, `expires_at`, drafts, and section history.
Submitting the current section validates every item in that section, freezes it,
and starts the next section with 600 seconds. Expiration keeps saved answers and
advances from the previous deadline, not the next browser visit. A long disconnect
therefore cannot extend the assessment. Refreshes restore the current section.
Past and future sections cannot be modified. Browser writes include the section
index; stale writes are rejected or ignored when the deadline has just elapsed.

Finalization stores raw answers and computed summaries as JSON exactly once,
deactivates the pass, and updates attendance and batch completion. Incomplete
instruments are explicitly marked incomplete and are not assigned fabricated
scores for unanswered items. Fullscreen/focus restrictions pause answer entry,
not the server clock; ordinary browser code cannot block operating-system capture
or provide kiosk-level lockdown.

**BFPI configuration remains required.** The repository has no approved BFPI item
count, subscale key, or reverse-scored item list. Populate `config/guidance.php`
from the institution's approved instrument. The service refuses to launch an
unconfigured battery. Tests use a small synthetic key solely to verify the timer
and scoring integration; it is not a deployable questionnaire. The career test
retains the existing six-item questionnaire, with one section per interest scale.

## Broadcasts and polling

Polling is the default transport and needs no websocket dependency or worker.
`GuidanceBatchStarted` also emits an after-commit event on the private
`guidance.batch.{id}` channel. Channel authorization is restricted to staff/admin.
For an optional Reverb deployment, install/configure Reverb and its client,
configure the `REVERB_*` variables, set `BROADCAST_CONNECTION=reverb`, and run the
queue worker. Do not publish student identity, receipts, or pass tokens in events.

## Validation

`php artisan test --compact` exercises roster validation, authorization, strict
identity checks, private receipts, batch launch, section boundaries, replay,
conversion, archives, scoring, and existing admissions/portal regressions.
`node tests/browser/guidance-workflow.cjs` exercises browser polling, previews,
review, assessment behavior, fake-camera capture, and batch launch redirects.

The additive migration is
`2026_09_18_000008_add_guidance_batches_and_sections.php`. Existing submissions and
long references remain available. Legacy single-instrument API requests remain
compatible; visible selection controls use the three standardized categories.
