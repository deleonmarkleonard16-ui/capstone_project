<section class="mb-3" data-security-feed="{{ route(auth()->user()->role.'.guidance-appointments.security-incidents', isset($moduleKey) && in_array($moduleKey, ['psychological', 'personality', 'career'], true) ? ['module' => $moduleKey] : []) }}">
    <p class="small text-muted" data-security-feed-status role="status">Security incident monitoring is connecting...</p>
    <details><summary>Recent security incidents</summary><ol class="small mt-2" data-security-history style="max-height:240px;overflow:auto"></ol></details>
</section>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090;max-width:min(420px,100%);pointer-events:none" data-security-toasts aria-live="polite" aria-atomic="false"></div>
@push('scripts')<script src="{{ asset('js/guidance-security-feed.js') }}" defer></script>@endpush
