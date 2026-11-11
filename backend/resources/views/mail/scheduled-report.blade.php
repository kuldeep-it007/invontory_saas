<x-mail::message>
# {{ $reportLabel }}

Your scheduled report **{{ $reportType }}** is ready and attached to this email.

@if ($periodFrom || $periodTo)
**Period:** {{ $periodFrom ?? '…' }} to {{ $periodTo ?? '…' }}
@endif

**Rows:** {{ $rowCount }}

The full report is attached. This report defaults to the previous calendar month unless a range was configured.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
