<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>PSU-CAT Report – {{ $cycle->name ?? $cycle->cycle_name }}</title>
    <style>
        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; }
        html { font-size: 11pt; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; background: #fff; margin: 0; padding: 0; }

        /* ── Print Controls (screen only) ── */
        .no-print { background: #f4f7fb; border-bottom: 1px solid #dde; padding: 12px 24px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .no-print button { padding: 7px 20px; border: 0; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-print   { background: #0d1b3e; color: #fff; }
        .btn-docx    { background: #2b579a; color: #fff; }
        .btn-summary { background: #6c757d; color: #fff; }
        .btn-qual    { background: #16a34a; color: #fff; }
        .btn-notqual { background: #dc2626; color: #fff; }
        @media print { .no-print { display: none; } }

        /* ── Report Shell ── */
        .report-page { max-width: 1040px; margin: 0 auto; padding: 24px 32px; }

        /* ── Header ── */
        .report-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0d1b3e; padding-bottom: 12px; }
        .report-header .psu-logo { font-size: 10pt; text-transform: uppercase; letter-spacing: 1.5px; color: #555; margin-bottom: 4px; }
        .report-header h1 { font-size: 15pt; font-weight: 800; color: #0d1b3e; margin: 0 0 3px; }
        .report-header .sub { font-size: 10pt; color: #555; }
        .report-header .meta { margin-top: 8px; font-size: 9pt; color: #777; }

        /* ── Report Type Badge ── */
        .report-type-badge { display: inline-block; margin: 0 auto 14px; padding: 3px 14px; border-radius: 999px; font-size: 9pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .type-summary  { background: #e8eaf6; color: #1a237e; border: 1px solid #c5cae9; }
        .type-qualified    { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
        .type-not-qualified { background: #fce4ec; color: #880e4f; border: 1px solid #f8bbd9; }

        /* ── Stats Row ── */
        .stats-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 110px; border: 1px solid #dde; border-radius: 8px; padding: 10px 14px; text-align: center; }
        .stat-box .num { font-size: 20pt; font-weight: 800; color: #0d1b3e; line-height: 1; }
        .stat-box .lbl { font-size: 8pt; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        /* ── Table ── */
        .report-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-top: 12px; }
        .report-table th, .report-table td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: middle; }
        .report-table thead th { background: #0d1b3e; color: #fff; font-weight: 700; white-space: nowrap; }
        .report-table tbody tr:nth-child(even) { background: #f8f9fc; }
        .report-table tbody tr:hover { background: #eef2ff; }
        .report-table td.rank { font-weight: 700; text-align: center; color: #555; }
        .report-table td.appno { font-family: monospace; font-size: 9pt; }
        .report-table td.name { font-weight: 600; }
        .badge-q  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; border-radius: 4px; padding: 1px 7px; font-size: 8pt; font-weight: 700; white-space: nowrap; }
        .badge-nq { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 4px; padding: 1px 7px; font-size: 8pt; font-weight: 700; white-space: nowrap; }
        .badge-p  { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; border-radius: 4px; padding: 1px 7px; font-size: 8pt; font-weight: 700; white-space: nowrap; }

        /* ── Course Section ── */
        .course-section { margin-top: 22px; }
        .course-section h2 { font-size: 11pt; font-weight: 700; background: #e8eaf6; color: #1a237e; padding: 5px 10px; border-left: 4px solid #3949ab; margin-bottom: 0; }

        /* ── Footer ── */
        .report-footer { text-align: center; margin-top: 24px; border-top: 1px solid #dde; padding-top: 10px; font-size: 8pt; color: #888; }
        .signatures { display: flex; justify-content: space-around; margin-top: 32px; gap: 20px; flex-wrap: wrap; }
        .sig-block { text-align: center; min-width: 160px; }
        .sig-block .line { border-top: 1px solid #333; margin: 0 auto 4px; width: 200px; }
        .sig-block .name { font-weight: 700; font-size: 10pt; }
        .sig-block .title { font-size: 8pt; color: #555; }
    </style>
</head>
<body>

{{-- ── PRINT / EXPORT CONTROL BAR (screen only) ── --}}
<div class="no-print">
    <strong style="font-size:13px;">PSU-CAT Report:</strong>
    <button class="btn-print" onclick="window.print()">🖨️ Print</button>
    <a href="{{ route('admin.admission.report', ['cycle_id'=>$cycle->id,'type'=>'summary','format'=>'docx']) }}"
       class="btn-docx" style="padding:7px 20px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">📄 DOCX Export</a>
    <a href="{{ route('admin.admission.report', ['cycle_id'=>$cycle->id,'type'=>'summary']) }}"
       class="btn-summary" style="padding:7px 20px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">📋 Summary</a>
    <a href="{{ route('admin.admission.report', ['cycle_id'=>$cycle->id,'type'=>'qualified']) }}"
       class="btn-qual" style="padding:7px 20px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">✅ Qualified</a>
    <a href="{{ route('admin.admission.report', ['cycle_id'=>$cycle->id,'type'=>'not-qualified']) }}"
       class="btn-notqual" style="padding:7px 20px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">❌ Not Qualified</a>
</div>

<div class="report-page">

    {{-- ── HEADER ── --}}
    <div class="report-header">
        <div class="psu-logo">Republic of the Philippines · Pangasinan State University · San Carlos Campus</div>
        <h1>Guidance and Counseling Office</h1>
        <div class="sub">PSU College Admission Test (PSU-CAT) — Evaluation Report</div>
        <div class="meta">
            Admission Cycle: <strong>{{ $cycle->name ?? $cycle->cycle_name }}</strong>
            &nbsp;·&nbsp; Academic Year: <strong>{{ $cycle->academic_year }}</strong>
            &nbsp;·&nbsp; Generated: <strong>{{ now()->format('F j, Y g:i A') }}</strong>
        </div>
    </div>

    {{-- ── REPORT TYPE BADGE ── --}}
    <div style="text-align:center;margin-bottom:12px;">
        @php
            $typeLabel = match($data['type'] ?? 'summary') {
                'qualified'     => 'Qualified Applicants Only',
                'not-qualified' => 'Not Qualified Applicants Only',
                default         => 'Complete Summary Report',
            };
            $typeClass = match($data['type'] ?? 'summary') {
                'qualified'     => 'type-qualified',
                'not-qualified' => 'type-not-qualified',
                default         => 'type-summary',
            };
        @endphp
        <span class="report-type-badge {{ $typeClass }}">{{ $typeLabel }}</span>
    </div>

    {{-- ── AGGREGATE STATS ── --}}
    @php
        $total      = $rows->count();
        $qualified  = $rows->where('qualification_status', 'Qualified')->count();
        $notQual    = $rows->where('qualification_status', 'Not Qualified')->count();
        $pending    = $rows->where('qualification_status', null)->count() + $rows->where('qualification_status', 'Pending')->count();
        $avgScore   = $total ? round($rows->avg('exam_score'), 2) : '—';
    @endphp
    <div class="stats-row">
        <div class="stat-box">
            <div class="num">{{ $total }}</div>
            <div class="lbl">Total Applicants</div>
        </div>
        <div class="stat-box">
            <div class="num" style="color:#16a34a;">{{ $qualified }}</div>
            <div class="lbl">Qualified</div>
        </div>
        <div class="stat-box">
            <div class="num" style="color:#dc2626;">{{ $notQual }}</div>
            <div class="lbl">Not Qualified</div>
        </div>
        <div class="stat-box">
            <div class="num" style="color:#92400e;">{{ $pending }}</div>
            <div class="lbl">Pending Evaluation</div>
        </div>
        <div class="stat-box">
            <div class="num">{{ $avgScore }}</div>
            <div class="lbl">Average Score</div>
        </div>
    </div>

    {{-- ── PER-COURSE SECTIONS ── --}}
    @php
        $byCourse = $rows->groupBy('course_choice');
        $rank = 1;
    @endphp

    @forelse ($byCourse as $course => $applicants)
    <div class="course-section">
        <h2>
            {{ $course ?: 'Unassigned Course' }}
            <span style="font-weight:400;font-size:9pt;">
                — {{ $applicants->count() }} applicant(s)
                · Quota: {{ $data['quotas'][$course] ?? '—' }}
                · Qualified: {{ $applicants->where('qualification_status','Qualified')->count() }}
            </span>
        </h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width:36px;">Rank</th>
                    <th>Application #</th>
                    <th>Applicant Name</th>
                    <th>Sex</th>
                    <th style="text-align:center;">GWA</th>
                    <th style="text-align:center;">Exam</th>
                    <th style="text-align:center;">Stanine</th>
                    <th style="text-align:center;">Interview</th>
                    <th style="text-align:center;">Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @php $rowRank = 1; @endphp
                @foreach ($applicants->sortByDesc('total_score') as $row)
                <tr>
                    <td class="rank">{{ $rowRank++ }}</td>
                    <td class="appno">{{ $row->application_number }}</td>
                    <td class="name">{{ mb_strtoupper($row->last_name) }}, {{ $row->first_name }}{{ $row->middle_name ? ' ' . mb_substr($row->middle_name, 0, 1) . '.' : '' }}</td>
                    <td>{{ $row->sex ?? '—' }}</td>
                    <td style="text-align:center;">{{ $row->gwa ?? '—' }}</td>
                    <td style="text-align:center;">{{ $row->exam_score ?? '—' }}</td>
                    <td style="text-align:center;">{{ $row->stanine_score ?? '—' }}</td>
                    <td style="text-align:center;">{{ $row->interview_score ?? '—' }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $row->total_score !== null ? number_format($row->total_score, 2) : '—' }}</td>
                    <td>
                        @if ($row->qualification_status === 'Qualified')
                            <span class="badge-q">✓ Qualified</span>
                        @elseif ($row->qualification_status === 'Not Qualified')
                            <span class="badge-nq">✗ Not Qualified</span>
                        @else
                            <span class="badge-p">Pending</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @empty
        <p style="text-align:center;color:#888;margin:40px 0;">No applicant data found for this report type.</p>
    @endforelse

    {{-- ── SIGNATURE BLOCK ── --}}
    <div class="signatures">
        <div class="sig-block">
            <div class="line"></div>
            <div class="name">Guidance Counselor</div>
            <div class="title">PSU-CAT Guidance Officer-in-Charge</div>
        </div>
        <div class="sig-block">
            <div class="line"></div>
            <div class="name">Campus Registrar</div>
            <div class="title">Pangasinan State University – San Carlos</div>
        </div>
        <div class="sig-block">
            <div class="line"></div>
            <div class="name">Campus Administrator</div>
            <div class="title">Pangasinan State University – San Carlos</div>
        </div>
    </div>

    <div class="report-footer">
        This report was generated by the Digital Management System for Guidance Testing and Admission (DMSGTA) ·
        Pangasinan State University – San Carlos Campus &nbsp;·&nbsp;
        {{ now()->format('Y') }}
    </div>

</div>
</body>
</html>
