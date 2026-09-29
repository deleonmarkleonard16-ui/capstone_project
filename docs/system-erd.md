# System ERD guide

This guide maps the business tables in `cap_db (1).sql` (dump generated September 27, 2026). Each relationship line in the diagrams represents a **declared foreign key** in that dump. `||` means one, `o|` means zero or one, and `o{` means zero or many. The fields shown are the keys and a few attributes useful for understanding the workflow; consult the SQL dump for every column and index.

## 1. Admission

The current cycle-based admission flow creates a cycle, answer key and course quotas, then places applicants in sessions. Each applicant's answers, exam score, GWA, interview score and qualification status are stored in `admission_applicants`. The scoring process uses the cycle's weights and quotas. A cycle can be completed and archived.

```mermaid
erDiagram
    admission_cycles {
        bigint id PK
        string cycle_name
        string status
        int total_items
    }
    admission_sessions {
        bigint id PK
        bigint admission_cycle_id FK
        string session_name
        string status
    }
    admission_applicants {
        bigint id PK
        bigint admission_cycle_id FK
        bigint admission_session_id FK
        string application_number
        string course_choice
        json answers
        decimal total_score
        string qualification_status
    }
    admission_answer_keys {
        bigint id PK
        bigint admission_cycle_id FK
        int item_number
        string correct_answer
    }
    admission_course_quotas {
        bigint id PK
        bigint admission_cycle_id FK
        string course_code
        int seats
    }
    admission_security_logs {
        bigint log_id PK
        bigint applicant_id FK
        string incident_type
    }
    guidance_test_security_logs {
        bigint log_id PK
        bigint applicant_id FK
        bigint guidance_appointment_id FK
    }

    admission_cycles ||--o{ admission_sessions : has
    admission_cycles ||--o{ admission_applicants : has
    admission_cycles ||--o{ admission_answer_keys : defines
    admission_cycles ||--o{ admission_course_quotas : sets
    admission_sessions o|--o{ admission_applicants : assigned_to
    admission_applicants ||--o{ admission_security_logs : has
    admission_applicants o|--o{ guidance_test_security_logs : logged_in
```

`guidance_test_security_logs` is a shared security table: its `applicant_id` foreign key points to **`admission_applicants`**, while its optional `guidance_appointment_id` points to a guidance appointment. The diagram repeats it in the guidance section to show its other foreign key.

### Earlier session-based admission tables

These remain in the dump and are a separate set of relationships. Do not connect `test_sessions` to `admission_sessions`, or `applicants` to `admission_applicants`, unless the database actually gains such a foreign key.

```mermaid
erDiagram
    applicants {
        bigint id PK
        bigint gender_id FK
        bigint applicant_status_id FK
        string application_number
    }
    test_sessions {
        bigint id PK
        bigint test_session_status_id FK
        string qr_token
    }
    session_applicants {
        bigint id PK
        bigint applicant_id FK
        bigint test_session_id FK
    }
    attendance_logs {
        bigint id PK
        bigint applicant_id FK
        bigint test_session_id FK
    }
    answer_keys {
        bigint id PK
        bigint test_session_id FK
    }
    answer_key_answers {
        bigint id PK
        bigint answer_key_id FK
        int question_number
    }
    answer_sheets {
        bigint id PK
        bigint applicant_id FK
        bigint test_session_id FK
    }
    answer_sheet_answers {
        bigint id PK
        bigint answer_sheet_id FK
        int question_number
    }
    admission_evaluations {
        bigint id PK
        bigint applicant_id FK
        bigint test_session_id FK
        decimal total_marks
    }

    applicants ||--o{ session_applicants : assigned
    test_sessions ||--o{ session_applicants : contains
    applicants ||--o{ attendance_logs : attendance
    test_sessions ||--o{ attendance_logs : attendance
    test_sessions ||--o{ answer_keys : key
    answer_keys ||--o{ answer_key_answers : items
    applicants ||--o{ answer_sheets : submits
    test_sessions ||--o{ answer_sheets : receives
    answer_sheets ||--o{ answer_sheet_answers : items
    applicants ||--o{ admission_evaluations : evaluated
    test_sessions o|--o{ admission_evaluations : evaluation_session
```

## 2. Guidance assessments

An individual portal submission creates one `service_requests` row, one `applicants` row and one `guidance_appointments` row for **each selected assessment category**. The request and appointment are linked by `service_request_id`; the person is linked through `applicant_id`. Receipt upload, staff verification and QR issuance precede the timed assessment. Completion saves answers and score summaries in `guidance_test_responses`.

Staff can instead import a roster into `guidance_test_batches`. Those appointments refer to the batch through `batch_id` and may have no `service_request_id`. If a batch appointment is converted to an individual request, `source_batch_id` preserves its original batch. Optional and historical records explain the zero cardinalities below.

```mermaid
erDiagram
    service_requests {
        bigint id PK
        bigint batch_id FK
        string reference
        string service
        string status
    }
    applicants {
        bigint id PK
        string application_number
    }
    guidance_test_batches {
        bigint batch_id PK
        string batch_name
        string module_type
        string status
    }
    guidance_appointments {
        bigint guidance_appointment_id PK
        bigint applicant_id FK
        bigint service_request_id FK
        bigint batch_id FK
        bigint source_batch_id FK
        bigint verified_by FK
        bigint terminated_by FK
        string request_code
        string test_category
        string status
    }
    guidance_test_qr_codes {
        bigint guidance_test_qr_code_id PK
        bigint guidance_appointment_id FK
        string token
    }
    guidance_test_responses {
        bigint guidance_test_response_id PK
        bigint guidance_appointment_id FK
        bigint applicant_id FK
        json answers
        json score_summary
    }
    guidance_security_incidents {
        bigint id PK
        bigint guidance_appointment_id FK
    }
    guidance_strike_resets {
        bigint id PK
        bigint guidance_appointment_id FK
        bigint user_id FK
    }
    guidance_test_security_logs {
        bigint log_id PK
        bigint guidance_appointment_id FK
        bigint applicant_id FK
    }
    guidance_test_submissions {
        bigint id PK
        bigint service_request_id FK
        string test_type
    }
    users {
        bigint id PK
        bigint role_id FK
    }

    service_requests o|--o{ guidance_appointments : requests
    applicants ||--o{ guidance_appointments : takes
    guidance_test_batches o|--o{ guidance_appointments : current_batch
    guidance_test_batches o|--o{ guidance_appointments : source_batch
    guidance_appointments ||--o| guidance_test_qr_codes : pass
    guidance_appointments ||--o| guidance_test_responses : result
    applicants ||--o{ guidance_test_responses : responses
    guidance_appointments ||--o{ guidance_security_incidents : incidents
    guidance_appointments ||--o{ guidance_strike_resets : resets
    guidance_appointments o|--o{ guidance_test_security_logs : security_logs
    users ||--o{ guidance_strike_resets : performs
    users o|--o{ guidance_appointments : verifies
    users o|--o{ guidance_appointments : terminates
    service_requests ||--o{ guidance_test_submissions : legacy_submissions
```

The dump has unique indexes on `guidance_test_qr_codes.guidance_appointment_id` and `guidance_test_responses.guidance_appointment_id`, so each appointment has **at most one** row in each of those tables. `guidance_test_submissions` stores an earlier request-based result format; it is not the same table as `guidance_test_responses`.

## 3. Good Moral and Exit Form documents

The portal creates a `service_requests` row with `service = good-moral` or `service = exit-form`. Staff may instead import a document roster into `guidance_test_batches`; each member then has a `service_requests.batch_id`. The request moves through receipt review, ready for pickup and completed/claimed states. Document requests do not require a guidance appointment.

```mermaid
erDiagram
    guidance_test_batches {
        bigint batch_id PK
        string batch_name
        string module_type
        string status
    }
    service_requests {
        bigint id PK
        bigint batch_id FK
        string reference
        string service
        string student_number
        string status
        string or_number
        datetime claimed_at
    }
    guidance_request_notifications {
        bigint id PK
        bigint service_request_id FK
        string module
    }
    guidance_notification_reads {
        bigint notification_id PK,FK
        bigint user_id PK,FK
    }
    users {
        bigint id PK
        bigint role_id FK
    }

    guidance_test_batches o|--o{ service_requests : contains
    service_requests ||--o{ guidance_request_notifications : notifications
    guidance_request_notifications ||--o{ guidance_notification_reads : read_by
    users ||--o{ guidance_notification_reads : reads
```

The `service_requests` and `guidance_test_batches` tables are **shared** by document and guidance workflows. Filter by `service` and `module_type` when reading each cluster. Notifications can apply to either kind of request.

## Shared lookup and supporting tables

```mermaid
erDiagram
    roles {
        bigint id PK
        string slug
    }
    users {
        bigint id PK
        bigint role_id FK
    }
    applicants {
        bigint id PK
        bigint gender_id FK
        bigint applicant_status_id FK
    }
    genders {
        bigint id PK
        string name
    }
    applicant_statuses {
        bigint id PK
        string slug
    }
    test_sessions {
        bigint id PK
        bigint test_session_status_id FK
    }
    test_session_statuses {
        bigint id PK
        string slug
    }

    roles o|--o{ users : grants_role
    genders o|--o{ applicants : gender
    applicant_statuses o|--o{ applicants : status
    test_session_statuses o|--o{ test_sessions : status
```

Other business/support tables in the dump have no declared foreign keys: `courses`, `guidance_reference_codes`, `guidance_request_reasons` and `guidance_settings`. `cache`, `cache_locks`, `failed_jobs`, `jobs`, `job_batches`, `migrations`, `password_reset_tokens` and `sessions` are framework infrastructure. The `sessions.user_id` column is indexed, but the dump does **not** declare it as a foreign key to `users`.

**ERD drawing rule:** draw a solid relationship only where the SQL defines a foreign key. `course_choice`, `course`, `course_code`, student numbers and tracking references are attributes or application-level matches in this dump; none is a declared foreign key to `courses` or another person table. The same name or student number appearing in two tables does not establish a database relationship.
