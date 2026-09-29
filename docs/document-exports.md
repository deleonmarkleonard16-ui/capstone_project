# DMSGTA document exports

Open **Document Exports** in the sidebar (`/admin/exports` or `/staff/exports`). Existing admission report, session print, guidance report, document report and guidance archive export buttons use the shared engine.

Authenticated administrators can export admission documents. Administrators and staff can export guidance results and official documents through their respective role prefixes. Exports do not modify records. Historical admission cycles are available without requiring an active cycle.

## Endpoints

`GET /admin/{module}/export` (also `/staff/{module}/export` except admission).

| Module | Document types (`type`) |
| --- | --- |
| `admission` | `masterlist` (default), `qualified`, `not-qualified`, `summary`, `session` |
| `guidance-testing`, `psychological`, `personality`, `career` | `summary` (default), `individual`, `batch`, `completion` |
| `good-moral`, `exit-form` | `queue` (default), `certificate` |
| `documents` | `queue` |
| `analytics` | `summary` (default), `records` |

All modules accept `format=html|pdf|docx|csv` (default `html`) and `auto_print=1`. Modules other than institutional analytics also accept `course` and `status`. Status values follow the existing module's stored statuses; admission uses `Pending`, `Qualified`, `Not Qualified`. Guidance accepts appointment and request status names and maps them across both workflows; document requests use lowercase statuses such as `completed`.

Admission accepts `cycle_id` (defaults to active cycle, or the selected session's cycle), `session_id` and `batch_group`. Guidance and document modules accept `batch_id`, exact `batch_name` and `request_id`. Guidance accepts `appointment_id` or `submission_id`; `guidance-testing` also accepts `category=psychological|personality|career`. An `individual` report requires a request, appointment or submission identifier. A `session` report requires `session_id`.

Institutional `analytics` exports accept `cycle_id`, which filters only their admission records and metrics; guidance and document data cover all batches. This scope is stated in the document metadata. They reject course/status filters. The existing analytics PDF and Excel-compatible CSV links also use the shared renderer.

Examples:

```text
/admin/admission/export?cycle_id=1&type=qualified&course=BSIT&format=docx
/admin/admission/export?cycle_id=1&type=summary&format=pdf
/admin/admission/print-masterlist?session_id=1
/admin/psychological/export?type=batch&batch_id=1&format=pdf
/staff/guidance-testing/export?type=individual&appointment_id=1&format=docx
/staff/career/export?type=completion&course=BSIT&format=csv
/staff/good-moral/export?type=certificate&request_id=1&format=pdf
/staff/exit-form/export?type=certificate&request_id=2&format=html&auto_print=1
/admin/documents/export?type=queue&format=docx
```

`GET /{role}/{module}/print-masterlist` forces HTML and auto-print. For admission, it also forces `type=session`. The existing `/admin/admission/sessions/{session}/print-masterlist` remains supported.

## Eligibility and error handling

Good Moral certificates require `approved`, `processing`, `ready` or `completed`. Exit Form certificates require `completed`. Queue exports include all matching statuses. Cross-service request identifiers cannot generate another service's certificate.

Guidance results require scored response summaries or completed legacy submissions. Completion reports additionally require completed appointments or requests. Bundled assessments produce a row per instrument. Mirrored legacy submissions are suppressed when the appointment already supplies that request's instrument result.

Invalid filters use Laravel validation errors. Empty results, missing active cycles, mismatched sessions and generation failures redirect to the export page with an `error` flash message. Generation exceptions are logged server-side without exposing paths or exception details to the browser.

## Implementation and dependencies

- `DocumentExportController`: validates filters, checks access and empty results, and sends responses.
- `ExportReportService`: gathers and normalizes module records without altering stored scores.
- `DocumentExportService`: generates PDF, DOCX, CSV and printable HTML before response headers are sent.
- `resources/views/exports`: print layout, signatures and export selection forms.

Composer already declares `dompdf/dompdf` and `phpoffice/phpword`. DOCX also requires ZipArchive and XMLWriter. Missing dependencies are handled as generation failures. Temporary DOCX files are removed in `finally`. CSV cells neutralize spreadsheet formulas, and HTML/DOCX text is escaped. Responses disable caching.

PDF, DOCX and HTML include the university, office, title, date generated in Asia/Manila, selected cycle/batch and filter criteria, plus Prepared by Guidance Staff / Approved by Guidance Counselor signature lines. Exit certificates describe completion within the Guidance and Counseling Office.

Run `php artisan test --filter=DocumentExportTest` for filter isolation, eligibility, all-format empty results, actual PDF/DOCX generation, XML escaping, print behavior and failure handling.
