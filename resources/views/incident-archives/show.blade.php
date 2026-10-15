<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Archived incident | M.A.P.S.</title>
<style>body{font:14px Arial,sans-serif;max-width:1000px;margin:30px auto;padding:15px;color:#162438}table{width:100%;border-collapse:collapse}th,td{padding:9px;border:1px solid #ccd3dd;text-align:left;overflow-wrap:anywhere}th{width:30%}pre{white-space:pre-wrap;font:inherit}tr{break-inside:avoid}@media print{nav{display:none}}@page{margin:15mm}</style></head><body>
<nav><button onclick="window.print()">Print / Save as PDF</button> <a href="{{ route('incident-archives.index', ['type' => $type]) }}">Back to incident archives</a></nav>
<h1>M.A.P.S. Archived Incident</h1><p>{{ \App\Services\IncidentArchiveService::TYPES[$type] }} · Record #{{ $record->id }}</p>
<p>Printed by {{ auth()->user()->name }} · {{ now('Asia/Manila')->format('M d, Y g:i A') }} PHT</p>
@php
$labels = [
'incident_number' => 'Incident number', 'event_id' => 'Event ID', 'incident_type' => 'Incident type',
'barangay' => 'Barangay', 'source_barangay' => 'Barangay', 'location' => 'Location', 'location_name' => 'Location',
'street' => 'Street', 'corner' => 'Corner', 'status' => 'Status', 'flood_status' => 'Status',
'flood_code' => 'Flood code', 'flood_level_code' => 'Flood code', 'severity' => 'Severity', 'alarm_level' => 'BFP alarm',
'occurred_at' => 'Occurred (PHT)', 'reported_at' => 'Reported (PHT)', 'responded_at' => 'Responded (PHT)',
'fire_out_at' => 'Fire out (PHT)', 'observed_at' => 'Observed (PHT)', 'subsided_at' => 'Subsided (PHT)',
'flood_start_datetime' => 'Flood start (PHT)', 'flood_subsided_datetime' => 'Flood subsided (PHT)',
'duration_minutes' => 'Duration (minutes)', 'duration_hours' => 'Duration (hours)',
'individuals_affected' => 'Individuals affected', 'houses_destroyed' => 'Houses destroyed',
'injured_count' => 'Injured', 'deceased_count' => 'Deceased', 'cause' => 'Cause', 'remarks' => 'Remarks',
'archived_at' => 'Archived (PHT)', 'archive_reason' => 'Reason for archiving',
];
$utcDates = ['occurred_at', 'reported_at', 'responded_at', 'fire_out_at', 'observed_at', 'subsided_at', 'archived_at'];
@endphp
<table>@foreach($labels as $field => $label)
@if(array_key_exists($field, $record->getAttributes()))
@php
$value = $record->getRawOriginal($field);
if ($value !== null && in_array($field, $utcDates)) $value = \Carbon\Carbon::parse($value, 'UTC')->timezone('Asia/Manila')->format('M d, Y g:i A');
@endphp
<tr><th>{{ $label }}</th><td><pre>{{ $value ?? 'Not recorded' }}</pre></td></tr>
@endif
@endforeach</table></body></html>
