<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>7-Day Weather Forecast | M.A.P.S.</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .page-shell {
            width: min(1180px, calc(100% - 32px));
            margin: 32px auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 700;
        }

        .location {
            font-size: 14px;
            color: #6b7280;
        }

        .forecast-panel {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .forecast-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .forecast-header h1 {
            margin: 0 0 6px;
            font-size: clamp(24px, 3vw, 34px);
        }

        .forecast-header p {
            margin: 0;
            color: #6b7280;
        }

        .error-box {
            margin: 24px;
            padding: 16px 18px;
            border-radius: 12px;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .days-strip {
            display: grid;
            grid-template-columns: repeat(7, minmax(130px, 1fr));
            gap: 10px;
            padding: 18px;
            overflow-x: auto;
        }

        .day-card {
            min-width: 130px;
            border: 1px solid #dbe3ee;
            background: #ffffff;
            border-radius: 14px;
            padding: 16px 12px;
            cursor: pointer;
            text-align: center;
            transition: 0.2s ease;
        }

        .day-card:hover {
            transform: translateY(-2px);
            border-color: #93c5fd;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.10);
        }

        .day-card.active {
            border-color: #2563eb;
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #2563eb;
        }

        .day-name {
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .day-date {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .weather-icon {
            font-size: 34px;
            line-height: 1;
            margin: 8px 0 12px;
        }

        .condition {
            min-height: 34px;
            font-size: 12px;
            color: #475569;
            margin-bottom: 10px;
        }

        .temp {
            font-size: 18px;
            font-weight: 800;
        }

        .temp span {
            color: #64748b;
            font-weight: 600;
        }

        .rain {
            margin-top: 8px;
            font-size: 12px;
            color: #2563eb;
            font-weight: 700;
        }

        .details {
            padding: 26px;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
        }

        .details-title {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 20px;
        }

        .details-title h2 {
            margin: 0;
            font-size: 24px;
        }

        .details-title span {
            color: #64748b;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr));
            gap: 14px;
        }

        .detail-item {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
        }

        .detail-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .detail-value {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }

        .source-note {
            padding: 0 26px 24px;
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 900px) {
            .days-strip {
                grid-template-columns: repeat(7, 145px);
            }

            .details-grid {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }

        @media (max-width: 560px) {
            .page-shell {
                width: min(100% - 20px, 1180px);
                margin: 18px auto;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .forecast-header,
            .details {
                padding: 18px;
            }

            .days-strip {
                padding: 14px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .source-note {
                padding: 0 18px 18px;
            }
        }
    </style>
</head>
<body>
@php
    $iconForCode = static function ($code) {
        return match (true) {
            $code === 0 => '☀️',
            in_array($code, [1, 2], true) => '🌤️',
            $code === 3 => '☁️',
            in_array($code, [45, 48], true) => '🌫️',
            in_array($code, [51, 53, 55, 56, 57], true) => '🌦️',
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => '🌧️',
            in_array($code, [71, 73, 75, 77, 85, 86], true) => '❄️',
            in_array($code, [95, 96, 99], true) => '⛈️',
            default => '🌡️',
        };
    };
@endphp

<div class="page-shell">
    <div class="topbar">
        <a class="back-link" href="{{ route('public.portal') }}">
            ← Back to Public Portal
        </a>

        <div class="location">
            Mandaluyong City · 7-Day Forecast
        </div>
    </div>

    <section class="forecast-panel">
        <header class="forecast-header">
            <h1>7-Day Weather Forecast</h1>
            <p>Select a day to view its forecast details.</p>
            @if ($weatherFetchedAt ?? null)
                <p>Weather snapshot updated {{ \Carbon\CarbonImmutable::parse($weatherFetchedAt)->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Manila time).</p>
            @endif
            @if ($weatherIsStale ?? false)
                <p role="status">Showing an older weather snapshot while the latest data is unavailable.</p>
            @endif
        </header>

        @if ($weatherError)
            <div class="error-box">
                {{ $weatherError }}
            </div>
        @elseif (empty($forecast))
            <div class="error-box">
                No forecast data is available right now.
            </div>
        @else
            <div class="days-strip" id="forecastDays">
                @foreach ($forecast as $index => $day)
                    @php
                        $date = \Carbon\Carbon::parse($day['date']);
                    @endphp

                    <button
                        type="button"
                        class="day-card {{ $index === 0 ? 'active' : '' }}"
                        aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                        data-index="{{ $index }}"
                        data-date="{{ $date->format('l, F j, Y') }}"
                        data-condition="{{ $day['condition'] }}"
                        data-icon="{{ $iconForCode($day['weather_code']) }}"
                        data-max="{{ $day['temperature_max'] ?? 'N/A' }}"
                        data-min="{{ $day['temperature_min'] ?? 'N/A' }}"
                        data-rain="{{ $day['rain_probability'] ?? 'N/A' }}"
                        data-rainfall="{{ $day['rainfall_mm'] ?? 'N/A' }}"
                        data-wind="{{ $day['wind_speed_kph'] ?? 'N/A' }}"
                    >
                        <div class="day-name">
                            {{ $index === 0 ? 'Today' : $date->format('D') }}
                        </div>

                        <div class="day-date">
                            {{ $date->format('M j') }}
                        </div>

                        <div class="weather-icon">
                            {{ $iconForCode($day['weather_code']) }}
                        </div>

                        <div class="condition">
                            {{ $day['condition'] }}
                        </div>

                        <div class="temp">
                            {{ $day['temperature_max'] ?? '—' }}°
                            <span>/ {{ $day['temperature_min'] ?? '—' }}°</span>
                        </div>

                        <div class="rain">
                            Rain {{ $day['rain_probability'] ?? '—' }}%
                        </div>
                    </button>
                @endforeach
            </div>

            @php
                $firstDay = $forecast[0];
                $firstDate = \Carbon\Carbon::parse($firstDay['date']);
            @endphp

            <div class="details">
                <div class="details-title">
                    <h2 id="detailDate">{{ $firstDate->format('l, F j, Y') }}</h2>
                    <span id="detailCondition">
                        {{ $iconForCode($firstDay['weather_code']) }}
                        {{ $firstDay['condition'] }}
                    </span>
                </div>

                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Maximum Temperature</div>
                        <div class="detail-value" id="detailMax">
                            {{ $firstDay['temperature_max'] ?? 'N/A' }}°C
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Minimum Temperature</div>
                        <div class="detail-value" id="detailMin">
                            {{ $firstDay['temperature_min'] ?? 'N/A' }}°C
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Rain Probability</div>
                        <div class="detail-value" id="detailRain">
                            {{ $firstDay['rain_probability'] ?? 'N/A' }}%
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Forecast Rainfall</div>
                        <div class="detail-value" id="detailRainfall">
                            {{ $firstDay['rainfall_mm'] ?? 'N/A' }} mm
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Maximum Wind Speed</div>
                        <div class="detail-value" id="detailWind">
                            {{ $firstDay['wind_speed_kph'] ?? 'N/A' }} km/h
                        </div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Weather Condition</div>
                        <div class="detail-value" id="detailWeather">
                            {{ $firstDay['condition'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="source-note">
                Forecast source: Open-Meteo. Forecast values may change as new weather data becomes available.
            </div>
        @endif
    </section>
</div>

@if (!$weatherError && !empty($forecast))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const cards = document.querySelectorAll('.day-card');

        const setText = (id, value) => {
            const element = document.getElementById(id);

            if (element) {
                element.textContent = value;
            }
        };

        cards.forEach((card) => {
            card.addEventListener('click', function () {
                cards.forEach((item) => {
                    item.classList.remove('active');
                    item.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-pressed', 'true');

                setText('detailDate', this.dataset.date);
                setText(
                    'detailCondition',
                    `${this.dataset.icon} ${this.dataset.condition}`
                );
                setText('detailMax', `${this.dataset.max}°C`);
                setText('detailMin', `${this.dataset.min}°C`);
                setText('detailRain', `${this.dataset.rain}%`);
                setText('detailRainfall', `${this.dataset.rainfall} mm`);
                setText('detailWind', `${this.dataset.wind} km/h`);
                setText('detailWeather', this.dataset.condition);
            });
        });
    });
</script>
@endif
</body>
</html>
