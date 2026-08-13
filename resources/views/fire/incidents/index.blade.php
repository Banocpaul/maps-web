@extends('layouts.app')

@section('title', 'Fire Incidents | M.A.P.S.')
@section('page-title', 'Fire Incident Management')
@section('page-description', 'View, search, filter, and manage fire incident records')

@section('content')
    <style>
        .fire-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .fire-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .fire-header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .fire-header p {
            margin: 0.35rem 0 0;
            color: #6b7280;
        }

        .fire-button {
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

        .fire-button-primary {
            background: #b91c1c;
            color: #fff;
        }

        .fire-button-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .fire-button-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .fire-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .fire-stat-card,
        .fire-panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.8rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .fire-stat-card {
            padding: 1rem;
        }

        .fire-stat-card span {
            display: block;
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
        }

        .fire-stat-card strong {
            font-size: 1.65rem;
            color: #111827;
        }

        .fire-panel {
            padding: 1rem;
        }

        .fire-filter-grid {
            display: grid;
            grid-template-columns: 2fr repeat(3, minmax(0, 1fr)) auto;
            gap: 0.75rem;
            align-items: end;
        }

        .fire-field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .fire-field label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #374151;
        }

        .fire-field input,
        .fire-field select {
            min-height: 42px;
            width: 100%;
            padding: 0.6rem 0.7rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #fff;
        }

        .fire-filter-actions {
            display: flex;
            gap: 0.5rem;
        }

        .fire-alert {
            padding: 0.85rem 1rem;
            border-radius: 0.6rem;
            font-weight: 600;
        }

        .fire-alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .fire-table-wrap {
            overflow-x: auto;
        }

        .fire-table {
            width: 100%;
            border-collapse: collapse;
        }

        .fire-table th,
        .fire-table td {
            padding: 0.8rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .fire-table th {
            font-size: 0.75rem;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #f9fafb;
        }

        .fire-table td.location-cell {
            white-space: normal;
            min-width: 220px;
        }

        .fire-badge {
            display: inline-flex;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .fire-badge-minor {
            background: #dcfce7;
            color: #166534;
        }

        .fire-badge-moderate {
            background: #fef3c7;
            color: #92400e;
        }

        .fire-badge-major {
            background: #fee2e2;
            color: #991b1b;
        }

        .fire-status-reported {
            background: #f3f4f6;
            color: #374151;
        }

        .fire-status-responding {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .fire-status-controlled {
            background: #fef3c7;
            color: #92400e;
        }

        .fire-status-resolved {
            background: #dcfce7;
            color: #166534;
        }

        .fire-actions {
            display: flex;
            gap: 0.4rem;
            align-items: center;
        }

        .fire-action-link,
        .fire-delete-button {
            border: 0;
            background: transparent;
            padding: 0;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .fire-action-view {
            color: #1d4ed8;
        }

        .fire-action-edit {
            color: #92400e;
        }

        .fire-delete-button {
            color: #b91c1c;
        }

        .fire-action-locked {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .fire-empty {
            padding: 2.5rem;
            text-align: center;
            color: #6b7280;
        }

        .fire-pagination {
            margin-top: 1rem;
        }

        @media (max-width: 1100px) {
            .fire-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .fire-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .fire-stat-grid,
            .fire-filter-grid {
                grid-template-columns: 1fr;
            }

            .fire-filter-actions {
                flex-wrap: wrap;
            }
        }
    </style>

    <div class="fire-page">
        <section class="fire-header">
            <div>
                <h1>Fire Incident Management</h1>
                <p>Manage historical and operational fire incident records.</p>
            </div>

            <a href="{{ route('fire-incidents.create') }}" class="fire-button fire-button-primary">
                Add Fire Incident
            </a>
        </section>

        @if (session('success'))
            <div class="fire-alert fire-alert-success">
                {{ session('success') }}
            </div>
        @endif

        <section class="fire-stat-grid">
            <article class="fire-stat-card">
                <span>Total Incidents</span>
                <strong>{{ number_format($statistics['total'] ?? 0) }}</strong>
            </article>

            <article class="fire-stat-card">
                <span>Active Incidents</span>
                <strong>{{ number_format($statistics['active'] ?? 0) }}</strong>
            </article>

            <article class="fire-stat-card">
                <span>Resolved Incidents</span>
                <strong>{{ number_format($statistics['resolved'] ?? 0) }}</strong>
            </article>

            <article class="fire-stat-card">
                <span>Major Incidents</span>
                <strong>{{ number_format($statistics['major'] ?? 0) }}</strong>
            </article>
        </section>

        <section class="fire-panel">
            <form method="GET" action="{{ route('fire-incidents.index') }}" class="fire-filter-grid">
                <div class="fire-field">
                    <label for="search">Search</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ request('search') }}"
                        placeholder="Incident number, type, location, or barangay"
                    >
                </div>

                <div class="fire-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['Reported', 'Responding', 'Controlled', 'Resolved'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fire-field">
                    <label for="severity">Severity</label>
                    <select id="severity" name="severity">
                        <option value="">All severities</option>
                        @foreach (['Minor', 'Moderate', 'Major'] as $severity)
                            <option value="{{ $severity }}" @selected(request('severity') === $severity)>
                                {{ $severity }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fire-field">
                    <label for="barangay_id">Barangay</label>
                    <select id="barangay_id" name="barangay_id">
                        <option value="">All barangays</option>
                        @foreach ($barangays as $barangay)
                            <option
                                value="{{ $barangay->id }}"
                                @selected((string) request('barangay_id') === (string) $barangay->id)
                            >
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fire-filter-actions">
                    <button type="submit" class="fire-button fire-button-primary">
                        Apply
                    </button>

                    <a href="{{ route('fire-incidents.index') }}" class="fire-button fire-button-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="fire-panel">
            <div class="fire-table-wrap">
                @if ($incidents->count() > 0)
                    <table class="fire-table">
                        <thead>
                            <tr>
                                <th>Incident No.</th>
                                <th>Reported At</th>
                                <th>Barangay</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($incidents as $incident)
                                <tr>
                                    <td>{{ $incident->incident_number }}</td>

                                    <td>
                                        {{ $incident->reported_at?->format('M j, Y g:i A') ?? 'Not recorded' }}
                                    </td>

                                    <td>{{ $incident->barangay?->name ?? 'Unknown' }}</td>

                                    <td>{{ $incident->incident_type }}</td>

                                    <td class="location-cell">{{ $incident->location }}</td>

                                    <td>
                                        <span class="fire-badge fire-badge-{{ strtolower($incident->severity) }}">
                                            {{ $incident->severity }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="fire-badge fire-status-{{ strtolower($incident->status) }}">
                                            {{ $incident->status }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="fire-actions">
                                            <a
                                                href="{{ route('fire-incidents.show', $incident) }}"
                                                class="fire-action-link fire-action-view"
                                            >
                                                View
                                            </a>

                                            @if ($incident->status !== 'Resolved')
                                                <a
                                                    href="{{ route('fire-incidents.edit', $incident) }}"
                                                    class="fire-action-link fire-action-edit"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    action="{{ route('fire-incidents.destroy', $incident) }}"
                                                    onsubmit="return confirm('Delete this fire incident? This action cannot be undone.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit" class="fire-delete-button">
                                                        Delete
                                                    </button>
                                                </form>
                                            @else
                                                <span
                                                    class="fire-action-locked"
                                                    title="Resolved incidents are official locked records."
                                                >
                                                    &#128274; Locked
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="fire-pagination">
                        {{ $incidents->links() }}
                    </div>
                @else
                    <div class="fire-empty">
                        No fire incidents matched the selected filters.
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection