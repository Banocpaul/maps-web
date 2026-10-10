@extends('layouts.app')

@section('title', 'View Fire Incident | M.A.P.S')
@section('page-title', 'Fire Incident Details')
@section('page-description', 'Review the complete fire incident record')

@section('content')
    <style>
        .incident-show-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .incident-show-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .incident-show-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #111827;
        }

        .incident-show-header p {
            margin: 0.35rem 0 0;
            color: #6b7280;
        }

        .incident-show-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .incident-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0.65rem 1rem;
            border: 0;
            border-radius: 0.55rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .incident-button-primary {
            background: #b91c1c;
            color: #ffffff;
        }

        .incident-button-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .incident-button-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .incident-alert {
            padding: 0.9rem 1rem;
            border-radius: 0.6rem;
            font-weight: 600;
        }

        .incident-alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .incident-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .incident-summary-card,
        .incident-panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.8rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .incident-summary-card {
            padding: 1rem;
        }

        .incident-summary-card span {
            display: block;
            color: #6b7280;
            font-size: 0.8rem;
            margin-bottom: 0.4rem;
        }

        .incident-summary-card strong {
            color: #111827;
            font-size: 1rem;
            word-break: break-word;
        }

        .incident-panel {
            padding: 1.25rem;
        }

        .incident-panel h2 {
            margin: 0 0 1rem;
            font-size: 1rem;
            color: #111827;
        }

        .incident-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.5rem;
        }

        .incident-detail {
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 0.8rem;
        }

        .incident-detail-full {
            grid-column: 1 / -1;
        }

        .incident-detail span {
            display: block;
            color: #6b7280;
            font-size: 0.78rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .incident-detail strong,
        .incident-detail p {
            margin: 0;
            color: #111827;
            font-size: 0.95rem;
            word-break: break-word;
        }

        .incident-badge {
            display: inline-flex;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .incident-severity-minor {
            background: #dcfce7;
            color: #166534;
        }

        .incident-severity-moderate {
            background: #fef3c7;
            color: #92400e;
        }

        .incident-severity-major {
            background: #fee2e2;
            color: #991b1b;
        }

        .incident-status-reported {
            background: #f3f4f6;
            color: #374151;
        }

        .incident-status-responding {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .incident-status-controlled {
            background: #fef3c7;
            color: #92400e;
        }

        .incident-status-resolved {
            background: #dcfce7;
            color: #166534;
        }

        .incident-coordinate-box {
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 0.65rem;
            padding: 1rem;
        }

        .incident-coordinate-box p {
            margin: 0.35rem 0 0;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .incident-remarks {
            white-space: pre-line;
            line-height: 1.6;
        }

        @media (max-width: 950px) {
            .incident-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .incident-summary,
            .incident-detail-grid {
                grid-template-columns: 1fr;
            }

            .incident-detail-full {
                grid-column: auto;
            }

            .incident-show-actions {
                width: 100%;
            }

            .incident-button {
                flex: 1;
            }
        }
    </style>

    <div class="incident-show-page">
        <section class="incident-show-header">
            <div>
                <h1>{{ $fireIncident->incident_number }}</h1>
                <p>Complete fire incident information and response timeline.</p>
            </div>

            <div class="incident-show-actions">
                <a href="{{ route('fire-incidents.index') }}" class="incident-button incident-button-secondary">
                    Back to Incidents
                </a>

                @if ($fireIncident->status !== 'Resolved' && auth()->user()?->hasPermission('fire.edit'))
                    <a
                        href="{{ route('fire-incidents.edit', $fireIncident) }}"
                        class="incident-button incident-button-primary"
                    >
                        Edit Incident
                    </a>

                @endif

                @if ($fireIncident->status !== 'Resolved' && auth()->user()?->hasPermission('fire.delete'))
                    <form
                        method="POST"
                        action="{{ route('fire-incidents.destroy', $fireIncident) }}"
                        onsubmit="return confirm('Delete this fire incident? This action cannot be undone.');"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="incident-button incident-button-danger">
                            Delete
                        </button>
                    </form>
                @endif
            </div>
        </section>

        @if (session('success'))
            <div class="incident-alert incident-alert-success">
                {{ session('success') }}
            </div>
        @endif

        <section class="incident-summary">
            <article class="incident-summary-card">
                <span>Incident Number</span>
                <strong>{{ $fireIncident->incident_number }}</strong>
            </article>

            <article class="incident-summary-card">
                <span>Barangay</span>
                <strong>{{ $fireIncident->barangay?->name ?? $fireIncident->source_barangay ?? 'Unknown' }}</strong>
            </article>

            <article class="incident-summary-card">
                <span>Severity</span>
                <strong>
                    <span class="incident-badge incident-severity-{{ strtolower((string) $fireIncident->severity) }}">
                        {{ $fireIncident->severity ?? 'Unspecified' }}
                    </span>
                </strong>
            </article>

            <article class="incident-summary-card">
                <span>Status</span>
                <strong>
                    <span class="incident-badge incident-status-{{ strtolower($fireIncident->status) }}">
                        {{ $fireIncident->status }}
                    </span>
                </strong>
            </article>
        </section>

        <section class="incident-panel">
            <h2>Incident Information</h2>

            <div class="incident-detail-grid">
                <div class="incident-detail">
                    <span>Incident Type</span>
                    <strong>{{ $fireIncident->incident_type }}</strong>
                </div>

                <div class="incident-detail">
                    <span>Barangay</span>
                    <strong>{{ $fireIncident->barangay?->name ?? $fireIncident->source_barangay ?? 'Unknown' }}</strong>
                </div>

                <div class="incident-detail incident-detail-full">
                    <span>Exact Location</span>
                    <strong>{{ $fireIncident->location }}</strong>
                </div>
            </div>
        </section>

        <section class="incident-panel">
            <h2>Fire Incident Record</h2>
            <div class="incident-detail-grid">
                @foreach(['record_classification' => 'Record Classification', 'source_origin' => 'Source Origin', 'individuals_affected' => 'Individuals Affected', 'houses_destroyed' => 'Houses Destroyed', 'duration_minutes' => 'Duration (minutes)', 'alarm_level' => 'Alarm (reported)', 'cause' => 'Cause (confirmed)', 'alarm_reference' => 'Alarm (unconfirmed reference)', 'cause_reference' => 'Cause (unconfirmed reference)', 'data_source' => 'Data Source'] as $field => $label)
                    <div class="incident-detail"><span>{{ $label }}</span><strong>{{ $fireIncident->{$field} ?? 'Not recorded' }}</strong></div>
                @endforeach
                @foreach(['occurred_at' => 'Time Occurred (PHT)', 'fire_out_at' => 'Fire Out (PHT)'] as $field => $label)
                    <div class="incident-detail"><span>{{ $label }}</span><strong>{{ $fireIncident->{$field}?->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Not recorded' }}</strong></div>
                @endforeach
            </div>
            @if($fireIncident->source_record)
                <p class="mt-3 text-sm text-slate-500">Imported source: report time was not supplied. Cause and reference alarm are unconfirmed. Coordinates are approximate.</p>
                @if($fireIncident->source_record['crosses_midnight'] ?? false)<p class="mt-2 text-sm text-slate-500">Fire out is assumed to be the next day, following the source dictionary.</p>@endif
                <details class="mt-3"><summary>Original source row</summary><dl class="incident-detail-grid mt-3">
                    @foreach($fireIncident->source_record['values'] as $field => $value)<div class="incident-detail"><dt>{{ $field }}</dt><dd>{{ $value ?? 'Not recorded' }}</dd></div>@endforeach
                </dl></details>
            @endif
            @if($fireIncident->record_classification === 'Dataset')<p class="mt-3 text-sm text-slate-500">Included in the current project dataset and analytics. Source origin: {{ $fireIncident->source_origin }}.</p>@endif
        </section>

        <section class="incident-panel">
            <h2>Response Timeline</h2>

            <div class="incident-detail-grid">
                <div class="incident-detail">
                    <span>Reported At</span>
                    <strong>
                        {{ ($fireIncident->source_record['reported_at_is_fallback'] ?? false) ? 'Not recorded in source' : ($fireIncident->reported_at?->copy()->timezone('Asia/Manila')->format('F j, Y g:i A') ?? 'Not recorded') }}
                    </strong>
                </div>

                <div class="incident-detail">
                    <span>Responded At</span>
                    <strong>
                        {{ $fireIncident->responded_at?->copy()->timezone('Asia/Manila')->format('F j, Y g:i A') ?? 'Not recorded' }}
                    </strong>
                </div>

                <div class="incident-detail">
                    <span>Resolved At</span>
                    <strong>
                        {{ $fireIncident->resolved_at?->copy()->timezone('Asia/Manila')->format('F j, Y g:i A') ?? 'Not recorded' }}
                    </strong>
                </div>

                <div class="incident-detail">
                    <span>Response Duration</span>
                    <strong>
                        @if ($fireIncident->reported_at && $fireIncident->responded_at)
                            {{ $fireIncident->reported_at->diffForHumans($fireIncident->responded_at, true) }}
                        @else
                            Not available
                        @endif
                    </strong>
                </div>
            </div>
        </section>

        <section class="incident-panel">
            <h2>Map Coordinates</h2>

            <div class="incident-coordinate-box">
                @if (!is_null($fireIncident->latitude) && !is_null($fireIncident->longitude))
                    <strong>
                        Latitude: {{ $fireIncident->latitude }},
                        Longitude: {{ $fireIncident->longitude }}
                    </strong>

                    <p>
                        {{ $fireIncident->coordinate_accuracy === 'Approximate' ? 'Approximate barangay reference point; exact fire location is unknown.' : 'Verified incident coordinates.' }}
                    </p>
                @else
                    <strong>No coordinates recorded</strong>

                    <p>
                        @if ($fireIncident->status !== 'Resolved')
                            Edit this incident to add latitude and longitude for GIS visualization.
                        @else
                            No coordinates were recorded for this incident.
                        @endif
                    </p>
                @endif
            </div>
        </section>

        <section class="incident-panel">
            <h2>Additional Remarks</h2>

            <div class="incident-detail">
                <p class="incident-remarks">
                    {{ $fireIncident->remarks ?: 'No additional remarks were recorded.' }}
                </p>
            </div>
        </section>
    </div>
@endsection
