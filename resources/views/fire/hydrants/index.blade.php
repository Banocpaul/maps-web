@extends('layouts.app')

@section('title', 'Fire Hydrants | M.A.P.S.')
@section('page-title', 'Fire Hydrant Management')
@section('page-description', 'Manage hydrant records, operational status, and GIS coordinates')

@section('content')
    <style>
        .hydrant-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .hydrant-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hydrant-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #111827;
        }

        .hydrant-header p {
            margin: 0.35rem 0 0;
            color: #6b7280;
        }

        .hydrant-button {
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

        .hydrant-button-primary {
            background: #1d4ed8;
            color: #ffffff;
        }

        .hydrant-button-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .hydrant-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .hydrant-stat-card,
        .hydrant-panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.8rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .hydrant-stat-card {
            padding: 1rem;
        }

        .hydrant-stat-card span {
            display: block;
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
        }

        .hydrant-stat-card strong {
            color: #111827;
            font-size: 1.65rem;
        }

        .hydrant-panel {
            padding: 1rem;
        }

        .hydrant-filter-grid {
            display: grid;
            grid-template-columns: 2fr repeat(2, minmax(0, 1fr)) auto;
            gap: 0.75rem;
            align-items: end;
        }

        .hydrant-field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .hydrant-field label {
            color: #374151;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .hydrant-field input,
        .hydrant-field select {
            width: 100%;
            min-height: 42px;
            padding: 0.6rem 0.7rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #ffffff;
            box-sizing: border-box;
        }

        .hydrant-filter-actions {
            display: flex;
            gap: 0.5rem;
        }

        .hydrant-alert {
            padding: 0.85rem 1rem;
            border-radius: 0.6rem;
            font-weight: 600;
        }

        .hydrant-alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .hydrant-table-wrap {
            overflow-x: auto;
        }

        .hydrant-table {
            width: 100%;
            border-collapse: collapse;
        }

        .hydrant-table th,
        .hydrant-table td {
            padding: 0.8rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .hydrant-table th {
            background: #f9fafb;
            color: #4b5563;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .hydrant-table td.location-cell {
            min-width: 220px;
            white-space: normal;
        }

        .hydrant-badge {
            display: inline-flex;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .hydrant-status-active {
            background: #dcfce7;
            color: #166534;
        }

        .hydrant-status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .hydrant-status-maintenance {
            background: #fef3c7;
            color: #92400e;
        }

        .hydrant-actions {
            display: flex;
            gap: 0.45rem;
            align-items: center;
        }

        .hydrant-action-link,
        .hydrant-delete-button {
            border: 0;
            background: transparent;
            padding: 0;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .hydrant-action-view {
            color: #1d4ed8;
        }

        .hydrant-action-edit {
            color: #92400e;
        }

        .hydrant-delete-button {
            color: #b91c1c;
        }

        .hydrant-empty {
            padding: 2.5rem;
            text-align: center;
            color: #6b7280;
        }

        .hydrant-pagination {
            margin-top: 1rem;
        }

        @media (max-width: 1050px) {
            .hydrant-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hydrant-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .hydrant-stat-grid,
            .hydrant-filter-grid {
                grid-template-columns: 1fr;
            }

            .hydrant-filter-actions {
                flex-wrap: wrap;
            }
        }
    </style>

    <div class="hydrant-page">
        <section class="hydrant-header">
            <div>
                <h1>Fire Hydrant Management</h1>
                <p>Manage hydrant availability, location, inspection dates, and GIS coordinates.</p>
            </div>

            <a href="{{ route('fire-hydrants.create') }}" class="hydrant-button hydrant-button-primary">
                Add Fire Hydrant
            </a>
        </section>

        @if (session('success'))
            <div class="hydrant-alert hydrant-alert-success">
                {{ session('success') }}
            </div>
        @endif

        <section class="hydrant-stat-grid">
            <article class="hydrant-stat-card">
                <span>Total Hydrants</span>
                <strong>{{ number_format($statistics['total'] ?? 0) }}</strong>
            </article>

            <article class="hydrant-stat-card">
                <span>Active</span>
                <strong>{{ number_format($statistics['active'] ?? 0) }}</strong>
            </article>

            <article class="hydrant-stat-card">
                <span>Inactive</span>
                <strong>{{ number_format($statistics['inactive'] ?? 0) }}</strong>
            </article>

            <article class="hydrant-stat-card">
                <span>Under Maintenance</span>
                <strong>{{ number_format($statistics['maintenance'] ?? 0) }}</strong>
            </article>
        </section>

        <section class="hydrant-panel">
            <form method="GET" action="{{ route('fire-hydrants.index') }}" class="hydrant-filter-grid">
                <div class="hydrant-field">
                    <label for="search">Search</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ request('search') }}"
                        placeholder="Hydrant code, location, or barangay"
                    >
                </div>

                <div class="hydrant-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['Active', 'Inactive', 'Maintenance'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="hydrant-field">
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

                <div class="hydrant-filter-actions">
                    <button type="submit" class="hydrant-button hydrant-button-primary">
                        Apply
                    </button>

                    <a href="{{ route('fire-hydrants.index') }}" class="hydrant-button hydrant-button-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="hydrant-panel">
            <div class="hydrant-table-wrap">
                @if ($hydrants->count() > 0)
                    <table class="hydrant-table">
                        <thead>
                            <tr>
                                <th>Hydrant Code</th>
                                <th>Barangay</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Last Inspection</th>
                                <th>Coordinates</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($hydrants as $hydrant)
                                <tr>
                                    <td>{{ $hydrant->hydrant_code }}</td>
                                    <td>{{ $hydrant->barangay?->name ?? 'Unknown' }}</td>
                                    <td class="location-cell">{{ $hydrant->location }}</td>

                                    <td>
                                        <span class="hydrant-badge hydrant-status-{{ strtolower($hydrant->status) }}">
                                            {{ $hydrant->status }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $hydrant->last_inspection_date?->format('M j, Y') ?? 'Not recorded' }}
                                    </td>

                                    <td>
                                        @if (!is_null($hydrant->latitude) && !is_null($hydrant->longitude))
                                            {{ $hydrant->latitude }}, {{ $hydrant->longitude }}
                                        @else
                                            Not recorded
                                        @endif
                                    </td>

                                    <td>
                                        <div class="hydrant-actions">
                                            <a
                                                href="{{ route('fire-hydrants.show', $hydrant) }}"
                                                class="hydrant-action-link hydrant-action-view"
                                            >
                                                View
                                            </a>

                                            <a
                                                href="{{ route('fire-hydrants.edit', $hydrant) }}"
                                                class="hydrant-action-link hydrant-action-edit"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="{{ route('fire-hydrants.destroy', $hydrant) }}"
                                                onsubmit="return confirm('Delete this fire hydrant? This action cannot be undone.');"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="hydrant-delete-button">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="hydrant-pagination">
                        {{ $hydrants->links() }}
                    </div>
                @else
                    <div class="hydrant-empty">
                        No fire hydrants matched the selected filters.
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection