<x-mail::message>
# Your account is ready

Hello {{ $account->name }},

An administrator approved your **{{ ucfirst($account->role) }}** account for {{ config('app.name') }}.

**Email:** {{ $account->email }}<br>
**Temporary password:** `{{ $temporaryPassword }}`

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

After your first sign-in, you can choose whether to keep this password or change it. You can also change it later from Settings. Keep these credentials private.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
