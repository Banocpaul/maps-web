@extends('layouts.app')

@section('title', 'Dashboard | M.A.P.S.')

@section('page-title', 'Dashboard')

@section(
    'page-description',
    'Overview of disaster risks, incidents, predictions, and response information'
)

@section('content')
   @php
    /*
    |--------------------------------------------------------------------------
    | Fire Dashboard
    |--------------------------------------------------------------------------
    */

    $fireKpis = $fireDashboard['kpis'] ?? [];
    $monthlyTrend = $fireDashboard['monthly_trend'] ?? [];
    $severityDistribution = $fireDashboard['severity_distribution'] ?? [];
    $alarmDistribution = $fireDashboard['alarm_distribution'] ?? [];
    $topBarangays = $fireDashboard['top_barangays'] ?? [];
    $recentIncidents = $fireDashboard['recent_incidents'] ?? collect();

    /*
    |--------------------------------------------------------------------------
    | Flood Dashboard
    |--------------------------------------------------------------------------
    */

    $floodKpis = $floodDashboard['kpis'] ?? [];

    $floodMonthlyTrend = $floodDashboard['monthly_trend'] ?? [];

    $floodRiskDistribution = $floodDashboard['risk_distribution'] ?? [];

    $floodRainfallTrend = $floodDashboard['rainfall_trend'] ?? [];

    $floodTopBarangays = $floodDashboard['top_barangays'] ?? [];

    $recentFloods = $floodDashboard['recent_records'] ?? collect();

    /*
    |--------------------------------------------------------------------------
    | Live Weather
    |--------------------------------------------------------------------------
    */

    $weatherTemperature = data_get($liveWeather, 'avg_temp_mean_c');
    $weatherHumidity = data_get($liveWeather, 'avg_rh_pct');
    $weatherRainfall24h = data_get($liveWeather, 'avg_rainfall_24h_mm');
    $weatherRainfall3d = data_get($liveWeather, 'rainfall_3d_mm');
    $weatherWindSpeed = data_get($liveWeather, 'avg_wind_speed');
    $weatherObservedAt = data_get($liveWeather, 'observed_at');
@endphp

    

    <section class="dashboard-heading">
        <div>
            <h1>Welcome back, {{ $user->first_name }}.</h1>
            <p>Here is the current M.A.P.S. system overview.</p>
        </div>
        <div class="dashboard-date">{{ now()->format('F j, Y') }}</div>
    </section>

    <section class="maps-filter-panel">
        <form method="GET" action="{{ route('dashboard') }}" class="maps-filter-form">
            <div class="maps-field">
                <label for="year">Fire data year</label>
                <select id="year" name="year">
                    <option value="">All years</option>
                    @foreach ($availableYears as $year)
                        <option value="{{ $year }}" @selected((string) $selectedYear === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="maps-field">
                <label for="barangay_id">Barangay</label>
                <select id="barangay_id" name="barangay_id">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected((string) $selectedBarangayId === (string) $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="maps-filter-actions">
                <button type="submit" class="maps-button maps-button-primary">Apply Filters</button>
                <a href="{{ route('dashboard') }}" class="maps-button maps-button-secondary">Reset</a>
            </div>
        </form>
    </section>

    <section class="stat-grid">
       <article class="stat-card">
    <p class="stat-label">Flood Records</p>

    <h2>{{ number_format($floodKpis['total_records'] ?? 0) }}</h2>

    <span class="maps-stat-note">
        {{ number_format($floodKpis['high_risk_records'] ?? 0) }}
        High Risk Records
    </span>
</article>

        <article class="stat-card">
            <p class="stat-label">Fire Incidents</p>
            <h2>{{ number_format($fireKpis['total_incidents'] ?? 0) }}</h2>
            <span class="maps-stat-note">{{ number_format($fireKpis['major_incidents'] ?? 0) }} major incidents</span>
        </article>

        <article class="stat-card">
            <p class="stat-label">Individuals Affected</p>
            <h2>{{ number_format($fireKpis['individuals_affected'] ?? 0) }}</h2>
            <span class="maps-stat-note">Historical fire incident records</span>
        </article>

        <article class="stat-card">
            <p class="stat-label">Houses Destroyed</p>
            <h2>{{ number_format($fireKpis['houses_destroyed'] ?? 0) }}</h2>
            <span class="maps-stat-note">Average duration: {{ $fireKpis['average_duration_label'] ?? '0 min' }}</span>
        </article>
    </section>
    <section class="maps-section-heading">
    <div>
        <h2>Live Weather</h2>
        <p>Current Mandaluyong City weather measurements from Open-Meteo</p>
    </div>
</section>

@if ($liveWeatherError)
    <section class="dashboard-panel">
        <div class="maps-empty">
            {{ $liveWeatherError }}
        </div>
    </section>
@else
    <section class="stat-grid">
        <article class="stat-card">
            <p class="stat-label">Temperature</p>

            <h2>
                {{ $weatherTemperature !== null
                    ? number_format((float) $weatherTemperature, 1) . ' °C'
                    : 'N/A' }}
            </h2>

            <span class="maps-stat-note">
                Current mean temperature
            </span>
        </article>

        <article class="stat-card">
            <p class="stat-label">Humidity</p>

            <h2>
                {{ $weatherHumidity !== null
                    ? number_format((float) $weatherHumidity, 1) . '%'
                    : 'N/A' }}
            </h2>

            <span class="maps-stat-note">
                Relative humidity
            </span>
        </article>

        <article class="stat-card">
            <p class="stat-label">Rainfall — 24 Hours</p>

            <h2>
                {{ $weatherRainfall24h !== null
                    ? number_format((float) $weatherRainfall24h, 2) . ' mm'
                    : 'N/A' }}
            </h2>

            <span class="maps-stat-note">
                3-day total:
                {{ $weatherRainfall3d !== null
                    ? number_format((float) $weatherRainfall3d, 2) . ' mm'
                    : 'N/A' }}
            </span>
        </article>

        <article class="stat-card">
            <p class="stat-label">Wind Speed</p>

            <h2>
                {{ $weatherWindSpeed !== null
                    ? number_format((float) $weatherWindSpeed, 2) . ' m/s'
                    : 'N/A' }}
            </h2>

            <span class="maps-stat-note">
                Observed:
                {{ $weatherObservedAt
                    ? \Carbon\Carbon::parse($weatherObservedAt)
                        ->timezone('Asia/Manila')
                        ->format('M j, Y g:i A')
                    : 'Not available' }}
            </span>
        </article>
    </section>
@endif

<section class="dashboard-grid">
        <article class="dashboard-panel dashboard-map-panel">
            <div class="panel-heading">
                <div>
                    <h2>Mandaluyong Risk Map</h2>
                    <p>Flood risks, fire incidents, hydrants, and barangay boundaries will be displayed here.</p>
                </div>
            </div>
            <div class="map-placeholder">GIS Map Placeholder</div>
        </article>

        <article class="dashboard-panel">
            <div class="panel-heading">
                <div>
                    <h2>Current Risk Summary</h2>
                    <p>Flood and fire operational status</p>
                </div>
            </div>
            <div class="risk-list">
                <div class="risk-item"><span>Flood Risk</span><strong>Not Available</strong></div>
                <div class="risk-item"><span>Fire Incidents</span><strong>{{ number_format($fireKpis['total_incidents'] ?? 0) }} recorded</strong></div>
                <div class="risk-item"><span>Highest Incident Barangay</span><strong>{{ data_get($fireKpis, 'highest_incident_barangay.barangay_name', 'No data') }}</strong></div>
                <div class="risk-item"><span>Resolution Rate</span><strong>{{ $fireKpis['resolution_rate'] ?? 0 }}%</strong></div>
                <div class="risk-item"><span>System Status</span><strong>Operational</strong></div>
            </div>
        </article>
    </section>

    {{-- ================= FLOOD ANALYTICS ================= --}}

<section class="maps-section-heading">
    <div>
        <h2>Flood Analytics</h2>
        <p>Historical flood analysis and risk intelligence</p>
    </div>
</section>

<section class="maps-chart-grid">

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Monthly Flood Trend</h2>
                <p>Historical flood records by month</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="monthlyFloodTrendChart"></canvas>
        </div>
    </article>

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Flood Risk Distribution</h2>
                <p>Low, Medium and High Risk</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="floodRiskDistributionChart"></canvas>
        </div>
    </article>

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Rainfall Trend</h2>
                <p>Historical average rainfall</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="floodRainfallTrendChart"></canvas>
        </div>
    </article>

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Top Flood-Prone Barangays</h2>
                <p>Barangays with the most flood records</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="floodTopBarangaysChart"></canvas>
        </div>
    </article>

</section>

{{-- ================= FIRE ANALYTICS ================= --}}

<section class="maps-section-heading maps-fire-heading">
    <div>
        <h2>Fire Analytics</h2>
        <p>Historical fire incident analytics</p>
    </div>
</section>

<section class="maps-chart-grid">

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Monthly Fire Trend</h2>
                <p>Number of recorded fire incidents per month</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="monthlyFireTrendChart"></canvas>
        </div>
    </article>

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Severity Distribution</h2>
                <p>Minor, Moderate and Major</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="severityDistributionChart"></canvas>
        </div>
    </article>

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Top Barangays</h2>
                <p>Fire incidents by barangay</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="topBarangaysChart"></canvas>
        </div>
    </article>

    <article class="dashboard-panel maps-chart-panel">
        <div class="panel-heading">
            <div>
                <h2>Alarm Distribution</h2>
                <p>Fire alarm levels</p>
            </div>
        </div>

        <div class="maps-chart-wrap">
            <canvas id="alarmDistributionChart"></canvas>
        </div>
    </article>

</section>


    <section class="dashboard-panel maps-wide-panel">
        <div class="panel-heading"><div><h2>Recent Fire Incidents</h2><p>Most recent records from the historical fire incident dataset</p></div></div>
        <div class="maps-table-wrap">
            @if ($recentIncidents->isNotEmpty())
                <table class="maps-table">
                    <thead>
                        <tr>
                            <th>Incident No.</th><th>Date and Time</th><th>Barangay</th><th>Location</th><th>Severity</th><th>Alarm</th><th>Affected</th><th>Destroyed</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentIncidents as $incident)
                            <tr>
                                <td>{{ $incident->incident_number }}</td>
                                <td>{{ $incident->occurred_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</td>
                                <td>{{ $incident->barangay?->name ?? 'Unknown' }}</td>
                                <td>{{ $incident->location }}</td>
                                <td><span class="maps-badge maps-badge-{{ strtolower($incident->severity) }}">{{ $incident->severity }}</span></td>
                                <td>{{ $incident->alarm_level ?? 'Unspecified' }}</td>
                                <td>{{ number_format($incident->individuals_affected ?? 0) }}</td>
                                <td>{{ number_format($incident->houses_destroyed ?? 0) }}</td>
                                <td>{{ $incident->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="maps-empty">No fire incidents match the selected filters.</div>
            @endif
        </div>
    </section>

    <section class="dashboard-panel maps-wide-panel">
        <div class="panel-heading"><div><h2>Assigned Access</h2><p>Current account role and authorization</p></div></div>
        <div class="permission-summary">
            <div><span>Account</span><strong>{{ $user->full_name }}</strong></div>
            <div><span>Role</span><strong>{{ $assignedRole?->name ?? 'Unassigned' }}</strong></div>
            <div><span>Last Login</span><strong>{{ $user->last_login_at?->format('F j, Y g:i A') ?? 'Not available' }}</strong></div>
            <div><span>Access Level</span><strong>{{ $user->isAdministrator() ? 'Full System Access' : 'Role-Based Access' }}</strong></div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const floodMonthlyTrend = @json($floodMonthlyTrend);
const floodRiskDistribution = @json($floodRiskDistribution);
const floodRainfallTrend = @json($floodRainfallTrend);
const floodTopBarangays = @json($floodTopBarangays);
            const monthlyTrend = @json($monthlyTrend);
            const severityDistribution = @json($severityDistribution);
            const alarmDistribution = @json($alarmDistribution);
            const topBarangays = @json($topBarangays);

            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            };
const monthlyFloodTrendCanvas =
    document.getElementById('monthlyFloodTrendChart');

if (monthlyFloodTrendCanvas) {
    new Chart(monthlyFloodTrendCanvas, {
        type: 'line',

        data: {
            labels: floodMonthlyTrend.labels ?? [],

            datasets: [
                {
                    label: 'Flood Records',

                    data:
                        floodMonthlyTrend.records ??
                        floodMonthlyTrend.values ??
                        floodMonthlyTrend.incidents ??
                        floodMonthlyTrend.counts ??
                        [],

                    borderWidth: 2,
                    tension: .25,
                    fill: false
                }
            ]
        },

        options: {
            ...commonOptions,

            scales: {
                y: {
                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

const floodRiskDistributionCanvas =
    document.getElementById('floodRiskDistributionChart');

if (floodRiskDistributionCanvas) {
    new Chart(floodRiskDistributionCanvas, {
        type: 'doughnut',

        data: {
            labels: floodRiskDistribution.labels ?? [],

            datasets: [
                {
                    label: 'Flood Records',

                    data:
                        floodRiskDistribution.values ??
                        floodRiskDistribution.records ??
                        floodRiskDistribution.counts ??
                        []
                }
            ]
        },

        options: commonOptions
    });
}

const floodRainfallTrendCanvas =
    document.getElementById('floodRainfallTrendChart');

if (floodRainfallTrendCanvas) {
    new Chart(floodRainfallTrendCanvas, {
        type: 'line',

        data: {
            labels: floodRainfallTrend.labels ?? [],

            datasets: [
                {
                    label: 'Average 24-Hour Rainfall',

                    data:
                        floodRainfallTrend.rainfall ??
                        floodRainfallTrend.values ??
                        floodRainfallTrend.average_rainfall ??
                        floodRainfallTrend.average_rainfall_mm ??
                        floodRainfallTrend.rainfall_24h_mm ??
                        [],

                    borderWidth: 2,
                    tension: .25,
                    fill: false
                }
            ]
        },

        options: {
            ...commonOptions,

            scales: {
                y: {
                    beginAtZero: true,

                    title: {
                        display: true,
                        text: 'Rainfall (mm)'
                    }
                }
            }
        }
    });
}

const floodTopBarangaysCanvas =
    document.getElementById('floodTopBarangaysChart');

if (floodTopBarangaysCanvas) {
    new Chart(floodTopBarangaysCanvas, {
        type: 'bar',

        data: {
            labels: floodTopBarangays.labels ?? [],

            datasets: [
                {
                    label: 'Flood Records',

                    data:
                        floodTopBarangays.records ??
                        floodTopBarangays.values ??
                        floodTopBarangays.incidents ??
                        floodTopBarangays.counts ??
                        []
                }
            ]
        },

        options: {
            ...commonOptions,

            indexAxis: 'y',

            scales: {
                x: {
                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}
            new Chart(document.getElementById('monthlyFireTrendChart'), {
                type: 'line',
                data: { labels: monthlyTrend.labels ?? [], datasets: [{ label: 'Fire Incidents', data: monthlyTrend.incidents ?? [], borderWidth: 2, tension: .25, fill: false }] },
                options: { ...commonOptions, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });

            new Chart(document.getElementById('severityDistributionChart'), {
                type: 'doughnut',
                data: { labels: severityDistribution.labels ?? [], datasets: [{ label: 'Incidents', data: severityDistribution.values ?? [] }] },
                options: commonOptions
            });

            new Chart(document.getElementById('topBarangaysChart'), {
                type: 'bar',
                data: { labels: topBarangays.labels ?? [], datasets: [{ label: 'Fire Incidents', data: topBarangays.incidents ?? [] }] },
                options: { ...commonOptions, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
            });

            new Chart(document.getElementById('alarmDistributionChart'), {
                type: 'bar',
                data: { labels: alarmDistribution.labels ?? [], datasets: [{ label: 'Incidents', data: alarmDistribution.values ?? [] }] },
                options: { ...commonOptions, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        });
    </script>
@endsection