@component('mail::message')
# Order confirmed

Hi {{ $buyerName }},

Your order #{{ $orderId }} has been confirmed.

Invoice **{{ $invoiceSeries }}-{{ $invoiceNumber }}** has been issued for **{{ $amount }}**.

If you have any questions, simply reply to this email.

Thanks,<br>
{{ $brandName ?? config('app.name') }}
@endcomponent
