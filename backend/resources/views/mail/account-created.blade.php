@component('mail::message')
# Welcome{{ $brandName ? ' to '.$brandName : '' }}, {{ $accountName }}

Your {{ $buyerType }} account has been created and approved. You can now sign in
and start placing orders.

If you have any questions, simply reply to this email.

Thanks,<br>
{{ $brandName ?? config('app.name') }}
@endcomponent
