@extends('layouts.app')

@section('title', 'Edit Fire Incident | M.A.P.S.')
@section('page-title', 'Edit Fire Incident')
@section('page-description', 'Update an existing fire incident record')

@section('content')
    <style>
        .incident-form-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .incident-form-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .incident-form-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #111827;
        }

        .incident-form-header p {
            margin: 0.35rem 0 0;
            color: #6b7280;
        }

        .incident-panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.8rem;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .incident-section-title {
            margin: 0 0 1rem;
            font-size: 1rem;
            color: #111827;
        }

        .incident-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .incident-field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .incident-field-full {
            grid-column: 1 / -1;
        }

        .incident-field label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #374151;
        }

        .incident-required {
            color: #b91c1c;
        }

        .incident-field input,
        .incident-field select,
        .incident-field textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.55rem;
            padding: 0.7rem 0.8rem;
            background: #ffffff;
            color: #111827;
            box-sizing: border-box;
        }

        .incident-field input,
        .incident-field select {
            min-height: 43px;
        }

        .incident-field textarea {
            min-height: 130px;
            resize: vertical;
        }

        .incident-field input:focus,
        .incident-field select:focus,
        .incident-field textarea:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.1);
        }

        .incident-help {
            color: #6b7280;
            font-size: 0.78rem;
        }

        .incident-error {
            color: #b91c1c;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .incident-alert {
            border-radius: 0.6rem;
            padding: 1rem;
        }

        .incident-alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .incident-alert-error ul {
            margin: 0.5rem 0 0;
            padding-left: 1.25rem;
        }

        .incident-actions {
            display: flex;
            justify-content: flex-end;
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

        @media (max-width: 760px) {
            .incident-grid {
                grid-template-columns: 1fr;
            }

            .incident-field-full {
                grid-column: auto;
            }

            .incident-actions {
                justify-content: stretch;
            }

            .incident-button {
                flex: 1;
            }
        }
    </style>

    <div class="incident-form-page">
        <section class="incident-form-header">
            <div>
                <h1>Edit {{ $fireIncident->incident_number }}</h1>
                <p>Update the verified fire incident information below.</p>
            </div>

            <a href="{{ route('fire-incidents.show', $fireIncident) }}" class="incident-button incident-button-secondary">
                Back to Incident
            </a>
        </section>

        @if ($errors->any())
            <div class="incident-alert incident-alert-error">
                <strong>Please correct the following errors:</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('fire-incidents.update', $fireIncident) }}">
            @csrf
            @method('PUT')

            <div class="incident-form-page">
                <section class="incident-panel">
                    <h2 class="incident-section-title">Incident Information</h2>

                    <div class="incident-grid">
                        <div class="incident-field">
                            <label for="barangay_id">
                                Barangay <span class="incident-required">*</span>
                            </label>

                            <select id="barangay_id" name="barangay_id" required>
                                <option value="">Select a barangay</option>

                                @foreach ($barangays as $barangay)
                                    <option
                                        value="{{ $barangay->id }}"
                                        @selected((string) old('barangay_id', $fireIncident->barangay_id) === (string) $barangay->id)
                                    >
                                        {{ $barangay->name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('barangay_id')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="incident_type">
                                Incident Type <span class="incident-required">*</span>
                            </label>

                            <input
                                id="incident_type"
                                name="incident_type"
                                type="text"
                                maxlength="100"
                                value="{{ old('incident_type', $fireIncident->incident_type) }}"
                                placeholder="Example: Residential fire"
                                required
                            >

                            @error('incident_type')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="incident-field incident-field-full">
                            <label for="location">
                                Exact Location <span class="incident-required">*</span>
                            </label>

                            <input
                                id="location"
                                name="location"
                                type="text"
                                maxlength="255"
                                value="{{ old('location', $fireIncident->location) }}"
                                placeholder="Street, building, landmark, or complete incident location"
                                required
                            >

                            @error('location')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">Classification and Status</h2>

                    <div class="incident-grid">
                        <div class="incident-field">
                            <label for="severity">
                                Severity <span class="incident-required">*</span>
                            </label>

                            <select id="severity" name="severity" required>
                                <option value="">Select severity</option>
                                <option value="Minor" @selected(old('severity', $fireIncident->severity) === 'Minor')>Minor</option>
                                <option value="Moderate" @selected(old('severity', $fireIncident->severity) === 'Moderate')>Moderate</option>
                                <option value="Major" @selected(old('severity', $fireIncident->severity) === 'Major')>Major</option>
                            </select>

                            @error('severity')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="status">
                                Status <span class="incident-required">*</span>
                            </label>

                            <select id="status" name="status" required>
                                <option value="">Select status</option>
                                <option value="Reported" @selected(old('status', $fireIncident->status) === 'Reported')>Reported</option>
                                <option value="Responding" @selected(old('status', $fireIncident->status) === 'Responding')>Responding</option>
                                <option value="Controlled" @selected(old('status', $fireIncident->status) === 'Controlled')>Controlled</option>
                                <option value="Resolved" @selected(old('status', $fireIncident->status) === 'Resolved')>Resolved</option>
                            </select>

                            @error('status')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">Incident Timeline</h2>

                    <div class="incident-grid">
                        <div class="incident-field">
                            <label for="reported_at">
                                Reported At <span class="incident-required">*</span>
                            </label>

                            <input
                                id="reported_at"
                                name="reported_at"
                                type="datetime-local"
                                value="{{ old('reported_at', optional($fireIncident->reported_at)->format('Y-m-d\TH:i')) }}"
                                required
                            >

                            @error('reported_at')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="responded_at">Responded At</label>

                            <input
                                id="responded_at"
                                name="responded_at"
                                type="datetime-local"
                                value="{{ old('responded_at', optional($fireIncident->responded_at)->format('Y-m-d\TH:i')) }}"
                            >

                            @error('responded_at')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="resolved_at">Resolved At</label>

                            <input
                                id="resolved_at"
                                name="resolved_at"
                                type="datetime-local"
                                value="{{ old('resolved_at', optional($fireIncident->resolved_at)->format('Y-m-d\TH:i')) }}"
                            >

                            @error('resolved_at')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">Map Coordinates</h2>

                    <div class="incident-grid">
                        <div class="incident-field">
                            <label for="latitude">Latitude</label>

                            <input
                                id="latitude"
                                name="latitude"
                                type="number"
                                step="any"
                                min="-90"
                                max="90"
                                value="{{ old('latitude', $fireIncident->latitude) }}"
                                placeholder="Example: 14.5794"
                            >

                            <span class="incident-help">Allowed range: -90 to 90.</span>

                            @error('latitude')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="longitude">Longitude</label>

                            <input
                                id="longitude"
                                name="longitude"
                                type="number"
                                step="any"
                                min="-180"
                                max="180"
                                value="{{ old('longitude', $fireIncident->longitude) }}"
                                placeholder="Example: 121.0359"
                            >

                            <span class="incident-help">Allowed range: -180 to 180.</span>

                            @error('longitude')
                                <span class="incident-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">Additional Remarks</h2>

                    <div class="incident-field">
                        <label for="remarks">Remarks</label>

                        <textarea
                            id="remarks"
                            name="remarks"
                            maxlength="2000"
                            placeholder="Add operational notes, reported damage, response details, or other relevant information."
                        >{{ old('remarks', $fireIncident->remarks) }}</textarea>

                        <span class="incident-help">Maximum of 2,000 characters.</span>

                        @error('remarks')
                            <span class="incident-error">{{ $message }}</span>
                        @enderror
                    </div>
                </section>

                <section class="incident-panel">
                    <div class="incident-actions">
                        <a href="{{ route('fire-incidents.show', $fireIncident) }}" class="incident-button incident-button-secondary">
                            Cancel
                        </a>

                        <button type="submit" class="incident-button incident-button-primary">
                            Update Fire Incident
                        </button>
                    </div>
                </section>
            </div>
        </form>
    </div>
@endsection