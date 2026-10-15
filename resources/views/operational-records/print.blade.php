<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $snapshot['title'] }}</title>
<style>body{font:13px Arial,sans-serif;color:#182435;margin:24px}h1{font-size:23px}table{border-collapse:collapse;width:100%;font-size:10px}th,td{border:1px solid #bbb;padding:5px;text-align:left;overflow-wrap:anywhere}th{background:#eef2f7}thead{display:table-header-group}.tools{display:flex;gap:16px;margin-bottom:24px}button,a{color:#164bc2}button{cursor:pointer;padding:8px} @media print{.tools{display:none}body{margin:0}tr{break-inside:avoid}th{background:#eee}}@page{size:A3 landscape;margin:12mm}</style>
</head><body><div class="tools"><button type="button" onclick="window.print()">Print / Save as PDF</button><a href="{{ route('operational-records.index') }}">Operational records</a><a href="{{ route('operational-records.archives') }}">Archived reports</a></div>
<h1>{{ $snapshot['title'] }}</h1>
<p>{{ isset($archive) ? 'Archived snapshot #'.$archive->id : 'Live report' }} · Generated: {{ $snapshot['generated_at'] }} · Prepared by: {{ $snapshot['prepared_by'] }}</p>
<p>Filters: @foreach(array_filter($snapshot['filters'], fn ($v) => $v !== null && $v !== '') as $key => $value) {{ str_replace('_', ' ', $key) }}: {{ $value }}; @endforeach</p>
<p>{{ count($snapshot['rows']) }} records. Blank impact counts mean not yet assessed.</p>
<table><thead><tr>@foreach($snapshot['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
@forelse($snapshot['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($snapshot['columns']) }}">No records match these filters.</td></tr>@endforelse
</tbody></table></body></html>
