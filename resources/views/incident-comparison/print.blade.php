<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>M.A.P.S. Incident Comparison Report</title>@include('incident-comparison.styles')</head><body style="background:#fff;padding:18px;font-family:Arial,sans-serif">
<nav class="ytd-no-print" style="margin-bottom:20px"><button class="ytd-link" onclick="window.print()">Print / Save as PDF</button> <a class="ytd-link" href="{{ route('incident-comparison.index', $filters) }}">Back to report</a></nav>
@include('incident-comparison.content')
</body></html>
