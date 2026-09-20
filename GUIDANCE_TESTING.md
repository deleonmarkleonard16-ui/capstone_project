# Guidance testing workflow

For the current dual-queue architecture, batch registration, and per-section
10-minute timers, see [guidance-batches.md](docs/guidance-batches.md). Its setup and
timer rules supersede the historical workflow notes below.

The new pipeline uses `guidance_appointments`, `guidance_test_qr_codes`, and
`guidance_test_responses`. It preserves existing admissions and service-request
records. Each appointment represents one paper instrument. Applicant foreign keys
reference the existing `applicants.id` column.

## Access

- Request submission and printable stub: the existing `/portal?service=testing#request` form.
- Receipt upload: look up the reference in **Track Existing Request**, then upload the receipt issued by the payment office.
- After submission, the portal redirects to `#track`, prefills the new reference,
  and automatically loads tracking. Details, the printable stub, next steps, and
  upload controls are rendered inside that section immediately.
- Tracking: the existing Tracking Reference field at `/portal?service=good-moral#track`.
- AJAX endpoint: `POST /api/track-request`, with `reference` and the normal session CSRF token.
- Staff: **Guidance Testing** in the sidebar, `/staff/guidance-testing`.
- Digital answer sheet: `/test/take/{token}`, available after receipt verification.

The existing form retains Student ID, course, reason, and the Psychological,
Personality, and Career selections. It does not require or store a receipt during
request submission. The student prints the generated request stub and presents it
to the payment office as proof of their Guidance request. After payment, the student
uploads the issued receipt in the tracking section. `POST /portal/guidance/receipt`
accepts `reference`, `proof` (JPG/PNG/WebP, max 5 MB), and the session CSRF token.
Each category creates one appointment linked by `service_request_id`;
one 128-bit `TR-` tracking reference retrieves all of its passes. New requests
start **Pending Payment** with a nullable `payment_slip_path`. Uploading the receipt updates
all linked appointments with the private receipt path and sets the parent request
to `proof_review`; appointments become **Receipt Uploaded**, and `appointment_at`
is set to the current timestamp. Staff cannot issue
a pass without the uploaded receipt. Duplicate uploads cannot replace receipts.
Staff explicitly select DASS-21, PHQ-9, or GAD-7 for Psychological
before verifying. Personality and Career requests can be received, but passes
cannot be issued until their instruments are configured. Multiple passes complete
independently; the parent request completes only after all of them do.

JSON clients can use `POST /api/submit-request` (the existing form fields),
`POST /api/track-request` (`request_code` or `reference`), and
`POST /api/upload-receipt` (`request_code` or `reference`, plus `proof`). These
session-based endpoints require CSRF tokens and are throttled. Submission returns
HTTP 201 with `request_code`, `status`, and `redirect_url`. For new requests, the
first appointment shares the parent tracking reference; additional categories
remain grouped under that reference. Existing references remain valid.

Staff use **Show receipt** to open a Bootstrap image-preview modal, then
**Verify & Generate QR**. No manual appointment/venue inputs are used by this
workflow; verification preserves the receipt-upload timestamp. The status
migration converts old Pending records according to whether they have a receipt.
For historical receipts without a stored appointment time, it uses the record's
last update time as a fallback rather than claiming an exact past upload time.

The tracking API returns `status`, `passes` (each with `test`, `category`, `status`,
`appointment_at`, `qr_image`, and `direct_test_link`), plus top-level `qr_image`
and `direct_test_link` for a single-pass request. QR images are Base64 SVG data
URIs with a four-module quiet zone. Pending/completed passes return null image
and link fields. `receipt_uploaded` distinguishes awaiting payment from awaiting
verification. Requests return escaped server-rendered stub and receipt controls
in `details_html`. After uploading, AJAX refreshes this same tracking section.
The lookup is throttled and marked no-store.

The duplicate request/tracking UI has been removed. The old request GET redirects
to the existing form. Previously issued standalone GT codes and tokens remain
usable; the old standalone POST endpoint remains for compatibility. Historical
service requests are not automatically migrated or re-approved. New linked
requests cannot be approved or have their receipts replaced through legacy routes.

Run `php artisan migrate` when installing this change on another environment.
Set `APP_URL` to the actual externally reachable origin. QR links use Laravel's
route URL generation and must be opened using the reachable application hostname;
`127.0.0.1` on a phone points to the phone itself. Serve a shared deployment using
HTTPS for browser fullscreen and session security. Configure trusted proxies only
for your actual reverse proxy when deploying behind one.

## Instruments

DASS-21 uses the supplied **raw, undoubled** subscale sums and thresholds.
GAD-7 computes its total and the supplied severity categories. PHQ-9 stores the
total only, as requested. Scores are for counselor review, not automated diagnoses.

BFPI, Career Test, and Exit Form remain unavailable until their paper instruments
are configured in `config/guidance.php`. BFPI requires `items`, a named `subscales`
map of one-based item numbers, and `reverse_items` (use an empty array if none).
Reverse-scored choices use `6 - answer`. Every item must belong to a subscale.
Means are rounded to two decimal places. The user selected **Average** for the
overlapping 3.14–3.39 range; High therefore starts at 3.40. The exact configured
BFPI key is included in the saved score summary for auditability.

Career and Exit configuration supports an item count and integer `min`/`max`
choices matching a paper sheet. No psychometric scoring rubric was supplied for
these instruments; when configured, answers are stored for manual review. Do not
enable them until the item structure has been confirmed. A text-based Exit Form
would require an explicit field schema instead of the numeric choice grid.

## Security and storage

Receipts are validated image uploads (JPG/PNG/WebP, maximum 5 MB) stored on the
private `local` disk. Only authenticated admin/staff routes serve them. Configure
PHP `upload_max_filesize` and `post_max_size` above this application limit for
larger uploads. Public requests create a separate applicant record rather than
linking an unverified identity to an existing admissions applicant.

New testing tracking codes contain 128 random bits; assessment tokens contain 256. Both are
bearer secrets and should be kept private. QR images are generated locally from
vendored PHP; tokens are not sent to an external QR service. Assessment
responses and receipts are never exposed by tracking. Avoid recording token URL
paths in reverse-proxy access logs. Use normal database/storage backups and access
controls for these sensitive records.

Staff verification is idempotent. Verification and submission acquire appointment
row locks; unique indexes enforce one QR and one response per appointment.
Submission saves the JSON answers and scores, marks the appointment Completed,
and disables its token in a single transaction. Invalid/missing/extra answers are
rejected. The old reference-only assessment routes no longer accept submissions;
historical counselor review remains available. The existing legacy request flow
remains separate and does not issue passes for the new pipeline.

The dashboard polls the protected results endpoint every 10 seconds while visible.
JSON results remain in the response table rather than general application logs.

## Browser controls and validation

The answer sheet begins behind a modal and requests fullscreen on a user click.
Exiting fullscreen, switching tabs, or losing focus pauses the sheet. Copy, print,
context menu, save shortcuts, and normal Back navigation receive best-effort
restrictions. An explicit return-to-tracking link remains available.

JavaScript cannot guarantee screenshot prevention, block operating-system app
switching, prevent developer-tool access, or establish a secure kiosk. Users can
disable or alter client-side code. Strong proctoring requires a managed device or
kiosk environment. Fullscreen-incompatible browsers must use another supported
device. A refresh loses unsubmitted answers; the active token can reopen the sheet.

Run `php artisan test --compact`. Feature tests cover receipt verification,
authorization, private tracking, required start, answer validation, replay
rejection, score boundaries, and BFPI subscales/reversal. The test suite uses an
isolated in-memory SQLite database; MySQL row-lock concurrency and actual device
fullscreen behavior need environment-specific acceptance checks.

Third-party local assets: Bootstrap 5.3.3 and qrcode-generator, MIT licensed.
The PHP QR generator source is vendored at `app/Support/Qr/qrcode.php` from
`https://github.com/kazuhikoarase/qrcode-generator` (`php/qrcode.php`). It builds
the QR matrix without GD; `GuidanceQrService` renders it as SVG.
