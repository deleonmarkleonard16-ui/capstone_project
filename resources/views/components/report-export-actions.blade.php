@props(['parameters'])

@once
    @push('styles')
        <style>
            .report-export-actions {
                display: flex;
                align-items: center;
                gap: .5rem;
                flex-wrap: nowrap;
                max-width: 100%;
                overflow-x: auto;
                padding: .25rem;
            }
            .report-export-actions__button {
                display: inline-flex;
                align-items: center;
                gap: .5rem;
                flex: 0 0 auto;
                padding: .5rem .75rem;
                border: 1px solid transparent;
                border-radius: .5rem;
                background-color: var(--export-bg);
                color: #fff;
                font-size: .75rem;
                font-weight: 600;
                line-height: 1.5;
                text-decoration: none;
                white-space: nowrap;
                transition: background-color .2s ease, box-shadow .2s ease;
            }
            .report-export-actions__button:hover {
                background-color: var(--export-hover);
                color: #fff;
            }
            .report-export-actions__button:focus-visible {
                outline: 2px solid var(--export-hover);
                outline-offset: 2px;
            }
            .report-export-actions__button svg {
                width: 1rem;
                height: 1rem;
                flex-shrink: 0;
            }
            .report-export-actions__button--html { --export-bg: #1e293b; --export-hover: #0f172a; }
            .report-export-actions__button--pdf { --export-bg: #dc2626; --export-hover: #b91c1c; }
            .report-export-actions__button--docx { --export-bg: #2563eb; --export-hover: #1d4ed8; }
            .report-export-actions__button--csv { --export-bg: #059669; --export-hover: #047857; }
            @media (min-width: 768px) {
                .report-export-actions__button { font-size: .875rem; }
            }
            @media (prefers-reduced-motion: reduce) {
                .report-export-actions__button { transition: none; }
            }
        </style>
    @endpush
@endonce

@php
    $actions = [
        'html' => ['label' => 'Print official report', 'colors' => 'bg-slate-800 hover:bg-slate-900'],
        'pdf' => ['label' => 'Export PDF', 'colors' => 'bg-red-600 hover:bg-red-700'],
        'docx' => ['label' => 'Export DOCX', 'colors' => 'bg-blue-600 hover:bg-blue-700'],
        'csv' => ['label' => 'Export CSV', 'colors' => 'bg-emerald-600 hover:bg-emerald-700'],
    ];
@endphp

<div class="report-export-actions no-print mb-3 flex items-center gap-2" role="group" aria-label="Report print and export actions">
    @foreach($actions as $format => $action)
        <a class="report-export-actions__button report-export-actions__button--{{ $format }} {{ $action['colors'] }} flex items-center gap-2 rounded-lg px-3 py-2 text-xs md:text-sm font-semibold text-white transition-colors"
           href="{{ route(auth()->user()->role.'.guidance-testing.export', array_merge($parameters, ['type' => 'individual', 'format' => $format, 'auto_print' => $format === 'html' ? 1 : 0])) }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                @if($format === 'html')
                    <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <path d="M6 14h12v7H6zM18 12h.01" />
                @elseif($format === 'pdf')
                    <path d="M14 2H5v20h14V7zM14 2v5h5" />
                    <text x="12" y="16" text-anchor="middle" fill="currentColor" stroke="none" font-family="Arial, sans-serif" font-size="6" font-weight="700">PDF</text>
                @elseif($format === 'docx')
                    <path d="M14 2H5v20h14V7zM14 2v5h5M8 11l2 6 2-4 2 4 2-6" />
                @else
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <path d="M3 9h18M3 15h18M9 3v18M15 9v12" />
                @endif
            </svg>
            <span>{{ $action['label'] }}</span>
        </a>
    @endforeach
</div>
