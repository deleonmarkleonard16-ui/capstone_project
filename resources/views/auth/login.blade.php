@extends('layouts.app')

@section('body_class', 'login-body')

@section('content')
<style>
    /* ── Login Hero & Container Styles ── */
    .login-container-wrapper {
        min-height: calc(100vh - 120px);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card-main {
        border-radius: 1.5rem;
        box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.15) inset;
        overflow: hidden;
        background: #ffffff;
        border: none;
    }

    .login-hero-panel {
        background: linear-gradient(to right, rgba(15, 23, 42, 0.88), rgba(30, 58, 138, 0.68)), 
                    url('{{ asset("images/psu-building.jpg") }}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 620px;
        position: relative;
    }

    /* Modern Glassmorphic Badge */
    .hero-campus-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #fbbf24; /* amber-400 */
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.45rem 1rem;
        border-radius: 9999px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        letter-spacing: 0.02em;
    }

    .hero-feature-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #e2e8f0;
        font-size: 0.75rem;
        padding: 0.3rem 0.75rem;
        border-radius: 0.5rem;
    }

    /* ── Form & Inputs Styling ── */
    .login-form-panel {
        background: #ffffff;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .login-welcome-box {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 1rem 1.25rem;
    }

    .custom-input-group {
        position: relative;
        display: flex;
        align-items: stretch;
        width: 100%;
    }

    .input-icon-prefix {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.05rem;
        z-index: 4;
        pointer-events: none;
        transition: color 0.2s ease;
    }

    .custom-input-group .form-control {
        border-radius: 0.75rem !important;
        padding: 0.7rem 1rem 0.7rem 2.75rem;
        border: 1.5px solid #e2e8f0;
        background-color: #f8fafc;
        color: #1e293b;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
    }

    .custom-input-group.has-trailing .form-control {
        padding-right: 3rem;
    }

    .custom-input-group .form-control:focus {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        outline: none;
    }

    .custom-input-group:focus-within .input-icon-prefix {
        color: #2563eb;
    }

    .btn-password-toggle {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #94a3b8;
        padding: 0.4rem 0.6rem;
        border-radius: 0.5rem;
        z-index: 5;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s, background-color 0.2s;
    }

    .btn-password-toggle:hover {
        color: #334155;
        background-color: #f1f5f9;
    }

    /* ── Primary Action Button ── */
    .btn-login-submit {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff;
        font-weight: 600;
        font-size: 0.95rem;
        letter-spacing: 0.01em;
        padding: 0.75rem 1.25rem;
        border-radius: 0.75rem;
        border: none;
        box-shadow: 0 10px 20px -3px rgba(37, 99, 235, 0.32);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-login-submit:hover {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        color: #ffffff;
        box-shadow: 0 12px 24px -2px rgba(37, 99, 235, 0.42);
        transform: translateY(-1px);
    }

    .btn-login-submit:active {
        transform: translateY(0);
        box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.3);
    }

    /* ── Credentials Callout Card ── */
    .credentials-callout-card {
        background-color: #f8fafc;
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 0.875rem;
        padding: 0.875rem 1rem;
        box-shadow: 0 2px 8px -2px rgba(0, 0, 0, 0.04);
    }

    .role-badge-mini {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 0.2rem 0.5rem;
        border-radius: 0.375rem;
        text-transform: uppercase;
    }

    .role-badge-admin {
        background-color: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .role-badge-staff {
        background-color: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }

    .cred-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.82rem;
        color: #334155;
        font-weight: 600;
    }

    @media (max-width: 991.98px) {
        .login-hero-panel {
            min-height: 380px;
        }
    }

    @media (max-width: 575.98px) {
        .login-hero-panel {
            min-height: 290px;
            padding: 1.5rem !important;
        }
        .login-form-panel {
            padding: 1.75rem 1.25rem !important;
        }
    }
</style>

<div class="login-container-wrapper py-3 py-lg-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-11">
                <div class="card login-card-main">
                    <div class="row g-0">
                        {{-- ══ LEFT HERO PANEL ══ --}}
                        <div class="col-12 col-lg-7 position-relative">
                            <div class="login-hero-panel h-100 p-4 p-md-5 d-flex flex-column justify-content-between text-white">
                                {{-- Top Badge --}}
                                <div class="mb-4">
                                    <span class="hero-campus-badge">
                                        <i class="bi bi-geo-alt-fill text-warning"></i>
                                        <span>PSU San Carlos Campus</span>
                                    </span>
                                </div>

                                {{-- Hero Bottom Content --}}
                                <div class="col-12 col-xl-11 mt-auto">
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="hero-feature-chip">
                                            <i class="bi bi-qr-code text-warning"></i> QR Session Check-in
                                        </span>
                                        <span class="hero-feature-chip">
                                            <i class="bi bi-shield-check text-info"></i> Instant Verification
                                        </span>
                                        <span class="hero-feature-chip">
                                            <i class="bi bi-graph-up-arrow text-success"></i> Real-time Analytics
                                        </span>
                                    </div>
                                    <h1 class="display-6 fw-bold text-white mb-3" style="text-shadow: 0 2px 8px rgba(0, 0, 0, 0.45); line-height: 1.2;">
                                        Guidance Testing and Admission made easier for staff and applicants.
                                    </h1>
                                    <p class="lead mb-0 text-white-50" style="color: rgba(255, 255, 255, 0.92) !important; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35); font-size: 1rem; line-height: 1.55;">
                                        Use one session QR code, verify examinees quickly, monitor attendance live, and manage answer encoding with a cleaner workflow.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- ══ RIGHT FORM PANEL ══ --}}
                        <div class="col-12 col-lg-5 p-4 p-md-5 login-form-panel">
                            {{-- Header / Brand --}}
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" style="width: 64px; height: 64px; object-fit: contain;">
                                <div>
                                    <p class="text-uppercase text-primary fw-bold mb-0" style="font-size: 0.78rem; letter-spacing: 0.08em;">Staff Access</p>
                                    <h2 class="h4 fw-bold text-slate-800 mb-0" style="color: #0f172a;">Sign in to continue</h2>
                                </div>
                            </div>

                            {{-- Welcome Box --}}
                            <div class="login-welcome-box mb-4">
                                <div class="d-flex align-items-center gap-2 fw-semibold text-slate-800 mb-1" style="color: #1e293b; font-size: 0.92rem;">
                                    <i class="bi bi-stars text-primary"></i>
                                    <span>Welcome back</span>
                                </div>
                                <div class="text-muted" style="font-size: 0.82rem; line-height: 1.45;">
                                    Manage applicants, session QR check-in, answer keys, attendance logs, and admission results from one place.
                                </div>
                            </div>

                            {{-- Form --}}
                            <form method="POST" action="{{ route('login.store') }}" class="row g-3">
                                @csrf
                                
                                {{-- Email Field with Left Icon --}}
                                <div class="col-12">
                                    <label for="email" class="form-label fw-semibold text-slate-700 mb-1" style="font-size: 0.85rem; color: #334155;">
                                        Email Address
                                    </label>
                                    <div class="custom-input-group">
                                        <i class="bi bi-envelope input-icon-prefix"></i>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="{{ old('email') }}" 
                                               placeholder="name@psu-scc.test"
                                               required 
                                               autocomplete="email"
                                               autofocus>
                                    </div>
                                </div>

                                {{-- Password Field with Left Icon & Eye Toggle --}}
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label for="password" class="form-label fw-semibold text-slate-700 mb-0" style="font-size: 0.85rem; color: #334155;">
                                            Password
                                        </label>
                                    </div>
                                    <div class="custom-input-group has-trailing">
                                        <i class="bi bi-lock input-icon-prefix"></i>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="••••••••"
                                               required 
                                               autocomplete="current-password">
                                        <button class="btn-password-toggle" 
                                                type="button" 
                                                id="togglePassword" 
                                                onclick="togglePasswordVisibility()" 
                                                title="Toggle password visibility"
                                                aria-label="Toggle password visibility">
                                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- Remember checkbox --}}
                                <div class="col-12 d-flex align-items-center justify-content-between pt-1">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input shadow-none" id="remember" name="remember" value="1" style="cursor: pointer;">
                                        <label class="form-check-label text-slate-600" for="remember" style="font-size: 0.85rem; color: #475569; cursor: pointer;">
                                            Remember this device
                                        </label>
                                    </div>
                                </div>

                                {{-- Submit Button --}}
                                <div class="col-12 pt-1">
                                    <button class="btn btn-login-submit w-100" type="submit">
                                        <span>Sign In</span>
                                        <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </form>

                            {{-- Demo Credentials Callout Card --}}
                            <div class="credentials-callout-card mt-4">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom border-slate-200">
                                    <div class="d-flex align-items-center gap-1.5 text-slate-700 fw-semibold" style="font-size: 0.78rem; color: #334155;">
                                        <i class="bi bi-key-fill text-primary"></i>
                                        <span>Demo Credentials</span>
                                    </div>
                                    <span class="text-muted" style="font-size: 0.72rem;">Quick reference</span>
                                </div>
                                <div class="d-flex flex-column gap-1.5">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="role-badge-mini role-badge-admin">Admin</span>
                                        <span class="cred-code">admin@psu-scc.test</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="role-badge-mini role-badge-staff">Staff</span>
                                        <span class="cred-code">staff@psu-scc.test</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between pt-1 border-top border-slate-100">
                                        <span class="text-muted fw-semibold" style="font-size: 0.72rem; text-transform: uppercase;">Password</span>
                                        <span class="cred-code text-primary">password</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon') || document.querySelector('#togglePassword i');
    if (!passwordInput) return;

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        if (toggleIcon) {
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        }
    } else {
        passwordInput.type = 'password';
        if (toggleIcon) {
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    }
}
</script>
@endsection
