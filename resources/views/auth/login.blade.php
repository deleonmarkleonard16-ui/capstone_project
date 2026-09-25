@extends('layouts.app')

@section('body_class', 'login-body')

@section('content')
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-xl-11">
            <div class="card page-card overflow-hidden border-0" style="background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(10px);">
                <div class="row g-0">
                    <div class="col-lg-7 position-relative">
                        <div class="h-100 p-5 d-flex flex-column justify-content-end text-white" style="background: linear-gradient(rgba(8, 25, 61, 0.28), rgba(8, 25, 61, 0.68)), url('{{ asset('images/psu-building.png') }}') center/cover no-repeat; min-height: 640px;">
                            <div class="mb-auto">
                                <span class="badge rounded-pill text-bg-warning text-dark px-3 py-2">PSU San Carlos Campus</span>
                            </div>
                            <div class="col-lg-10">
                                <h1 class="display-5 fw-bold">Guidance Testing and Admission made easier for staff and applicants.</h1>
                                <p class="lead mb-0 mt-3" style="color: rgba(255,255,255,0.88);">Use one session QR code, verify examinees quickly, monitor attendance live, and manage answer encoding with a cleaner workflow.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 p-5 bg-white d-flex flex-column justify-content-center">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" style="width: 72px; height: 72px; object-fit: contain; background: transparent;">
                            <div>
                                <p class="text-uppercase text-primary fw-semibold mb-1">Staff Access</p>
                                <h2 class="h3 mb-0">Sign in to continue</h2>
                            </div>
                        </div>

                        <div class="rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, #f8fbff, #eef4ff); border: 1px solid rgba(15, 63, 151, 0.08);">
                            <div class="fw-semibold mb-1">Welcome back</div>
                            <div class="text-muted small">Manage applicants, session QR check-in, answer keys, attendance logs, and admission results from one place.</div>
                        </div>

                        <form method="POST" action="{{ route('login.store') }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required>
                            </div>
                            <div class="col-12">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                                <label class="form-check-label" for="remember">Remember this device</label>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary w-100" type="submit">Login</button>
                            </div>
                        </form>
                        <div class="alert alert-info mt-4 mb-0">
                            Admin: <strong>admin@psu-scc.test</strong><br>
                            Staff: <strong>staff@psu-scc.test</strong><br>
                            Password: <strong>password</strong>
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
