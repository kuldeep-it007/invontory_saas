@component('mail::message')
# Order placed

Hi {{ $buyerName }},

Your order #{{ $orderId }} has been placed for **{{ $amount }}** and is awaiting
the seller's confirmation.

We'll let you know as soon as it is confirmed.

Thanks,<br>
{{ $brandName ?? config('app.name') }}
@endcomponent
