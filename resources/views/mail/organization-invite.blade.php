<x-mail::message>
# You've Been Invited

You've been invited to join **{{ $organization->name }}** on {{ config('wave.settings.site_title', config('app.name')) }}.

<x-mail::button :url="$acceptUrl">
    Accept Invitation
</x-mail::button>

This invitation link will expire in 7 days. If you did not expect this invitation, you can ignore this email.

Thanks,<br>
{{ config('wave.settings.site_title', config('app.name')) }}
</x-mail::message>
