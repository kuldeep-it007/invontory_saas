@component('mail::message')
# Payment recorded

Hi {{ $buyerName }},

We've recorded your payment of **{{ $amount }}** ({{ $method }}){{ $reference ? ' — reference '.$reference : '' }}
dated {{ $paidAt }}.

Your outstanding balance is now **{{ $outstandingBalance }}**.

If you have any questions, simply reply to this email.

Thanks,<br>
{{ $brandName ?? config('app.name') }}
@endcomponent
