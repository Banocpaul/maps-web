<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalRecordController extends Controller
{
    private const FLOOD_TABLE = 'flood_analytics_dataset';

    private const REPORT_DIMENSIONS = [
        'barangay' => 'Barangay',
        'year' => 'Year',
        'month' => 'Month',
        'risk_level' => 'Risk Level',
        'storm_signal' => 'Storm Signal',
        'nearest_waterway' => 'Nearest Waterway',
        'wet_season' => 'Wet Season',
        'day_of_week' => 'Day of Week',
    ];

    private const REPORT_MEASURES = [
        'records' => 'Number of Records',
        'rainfall_24h_mm' => 'Rainfall 24h (mm)',
        'rainfall_3d_mm' => 'Rainfall 3d (mm)',
        'rainfall_7d_mm' => 'Rainfall 7d (mm)',
        'flood_depth_mm' => 'Flood Depth (mm)',
        'duration_hours' => 'Flood Duration (hours)',
        'elevation_m' => 'Elevation (m)',
        'distance_to_waterway_m' => 'Distance to Waterway (m)',
    ];

    public function index(Request $request): View
    {
        $datasets = $this->datasets();
        $datasetKey = $request->string('dataset', 'flood-records')->toString();
        $dataset = $this->resolveDataset($datasets, $datasetKey);
        $filters = $this->validatedFilters($request);

        $records = $this->filteredQuery($dataset, $filters)
            ->paginate(25)
            ->withQueryString();

        $barangays = Schema::hasTable('barangays')
            ? Barangay::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $datasetCounts = collect($datasets)->mapWithKeys(
            fn (array $definition, string $key): array => [
                $key => Schema::hasTable($definition['table'])
                    ? $this->baseTableCount($definition['table'])
                    : null,
            ]
        );

        return view('operational-records.index', compact(
            'datasets',
            'datasetKey',
            'dataset',
            'records',
            'filters',
            'barangays',
            'datasetCounts'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $datasets = $this->datasets();
        $datasetKey = $request->string('dataset', 'flood-records')->toString();
        $dataset = $this->resolveDataset($datasets, $datasetKey);
        $filters = $this->validatedFilters($request);
        $fileName = $datasetKey . '-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($dataset, $filters): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values($dataset['columns']));

            foreach ($this->filteredQuery($dataset, $filters)->limit(100000)->cursor() as $record) {
                $row = [];

                foreach (array_keys($dataset['columns']) as $column) {
                    $row[] = $this->csvValue(data_get($record, $column));
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reportBuilder(Request $request): View
    {
        abort_unless(Schema::hasTable(self::FLOOD_TABLE), 404);

        $availableDimensions = collect(self::REPORT_DIMENSIONS)
            ->filter(fn (string $label, string $column): bool =>
                Schema::hasColumn(self::FLOOD_TABLE, $column)
            )
            ->all();
        $availableMeasures = collect(self::REPORT_MEASURES)
            ->filter(fn (string $label, string $column): bool =>
                $column === 'records' || Schema::hasColumn(self::FLOOD_TABLE, $column)
            )
            ->all();

        $validated = $request->validate([
            'row_primary' => ['nullable', Rule::in(array_keys($availableDimensions))],
            'row_secondary' => ['nullable', Rule::in(array_keys($availableDimensions))],
            'column' => ['nullable', Rule::in(array_keys($availableDimensions))],
            'measure' => ['nullable', Rule::in(array_keys($availableMeasures))],
            'aggregation' => ['nullable', Rule::in(['count', 'avg', 'sum', 'min', 'max'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'risk_level' => ['nullable', Rule::in(['Low', 'Medium', 'High'])],
        ]);

        $configuration = [
            'row_primary' => $validated['row_primary'] ?? 'barangay',
            'row_secondary' => $request->has('row_secondary')
                ? (string) ($validated['row_secondary'] ?? '')
                : 'year',
            'column' => $request->has('column')
                ? (string) ($validated['column'] ?? '')
                : 'risk_level',
            'measure' => $validated['measure'] ?? 'records',
            'aggregation' => $validated['aggregation'] ?? 'count',
            'date_from' => $validated['date_from'] ?? '',
            'date_to' => $validated['date_to'] ?? '',
            'barangay' => trim((string) ($validated['barangay'] ?? '')),
            'risk_level' => $validated['risk_level'] ?? '',
        ];

        if ($configuration['measure'] === 'records') {
            $configuration['aggregation'] = 'count';
        }

        $rowDimensions = array_values(array_unique(array_filter([
            $configuration['row_primary'],
            $configuration['row_secondary'],
        ])));
        $columnDimension = $configuration['column'];

        if (in_array($columnDimension, $rowDimensions, true)) {
            $columnDimension = '';
            $configuration['column'] = '';
        }

        $query = DB::table(self::FLOOD_TABLE);

        if (Schema::hasColumn(self::FLOOD_TABLE, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($configuration['date_from'] !== '' && Schema::hasColumn(self::FLOOD_TABLE, 'event_date')) {
            $query->whereDate('event_date', '>=', $configuration['date_from']);
        }

        if ($configuration['date_to'] !== '' && Schema::hasColumn(self::FLOOD_TABLE, 'event_date')) {
            $query->whereDate('event_date', '<=', $configuration['date_to']);
        }

        if ($configuration['barangay'] !== '') {
            $query->where('barangay', $configuration['barangay']);
        }

        if ($configuration['risk_level'] !== '') {
            $query->where('risk_level', $configuration['risk_level']);
        }

        $groupDimensions = array_values(array_unique(array_filter([
            ...$rowDimensions,
            $columnDimension,
        ])));
        $grammar = DB::connection()->getQueryGrammar();

        foreach ($groupDimensions as $index => $dimension) {
            $query->addSelect($dimension . ' as dimension_' . $index);
            $query->groupBy($dimension);
            $query->orderBy($dimension);
        }

        $measure = $configuration['measure'];
        $aggregation = strtoupper($configuration['aggregation']);
        $expression = $measure === 'records'
            ? 'COUNT(*)'
            : $aggregation . '(' . $grammar->wrap($measure) . ')';

        $results = $query->selectRaw($expression . ' as metric_value')
            ->limit(2500)
            ->get();

        $pivotRows = [];
        $columnKeys = [];
        $maximumValue = 0.0;

        foreach ($results as $result) {
            $dimensions = [];

            foreach ($groupDimensions as $index => $dimension) {
                $dimensions[$dimension] = data_get($result, 'dimension_' . $index);
            }

            $rowValues = collect($rowDimensions)
                ->mapWithKeys(fn (string $dimension): array => [
                    $dimension => $this->reportLabel($dimension, $dimensions[$dimension] ?? null),
                ])
                ->all();
            $rowKey = json_encode($rowValues, JSON_THROW_ON_ERROR);
            $columnKey = $columnDimension !== ''
                ? $this->reportLabel($columnDimension, $dimensions[$columnDimension] ?? null)
                : 'Value';
            $value = round((float) $result->metric_value, 2);

            $pivotRows[$rowKey] ??= ['dimensions' => $rowValues, 'values' => []];
            $pivotRows[$rowKey]['values'][$columnKey] = $value;
            $columnKeys[$columnKey] = true;
            $maximumValue = max($maximumValue, $value);
        }

        $columnKeys = array_keys($columnKeys);
        $barangays = DB::table(self::FLOOD_TABLE)
            ->whereNotNull('barangay')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        return view('operational-records.report-builder', [
            'availableDimensions' => $availableDimensions,
            'availableMeasures' => $availableMeasures,
            'configuration' => $configuration,
            'rowDimensions' => $rowDimensions,
            'columnDimension' => $columnDimension,
            'pivotRows' => array_values($pivotRows),
            'columnKeys' => $columnKeys,
            'maximumValue' => $maximumValue,
            'barangays' => $barangays,
        ]);
    }

    private function reportLabel(string $dimension, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not specified';
        }

        if ($dimension === 'month' && is_numeric($value)) {
            return Carbon::create(null, (int) $value, 1)->format('F');
        }

        if ($dimension === 'wet_season') {
            return (bool) $value ? 'Wet season' : 'Dry season';
        }

        return (string) $value;
    }

    public function createFlood(): View
    {
        abort_unless(Schema::hasTable(self::FLOOD_TABLE), 404);

        return view('operational-records.flood-form', [
            'record' => null,
            'barangays' => Barangay::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeFlood(Request $request): RedirectResponse
    {
        $data = $this->validatedFloodRecord($request);
        $data['created_at'] = now();
        DB::table(self::FLOOD_TABLE)->insert($data);

        return redirect()->route('operational-records.index', ['dataset' => 'flood-records'])
            ->with('success', 'Flood analytics record created successfully.');
    }

    public function editFlood(int $id): View
    {
        $record = DB::table(self::FLOOD_TABLE)->where('id', $id)->whereNull('deleted_at')->first();
        abort_if($record === null, 404);

        return view('operational-records.flood-form', [
            'record' => $record,
            'barangays' => Barangay::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateFlood(Request $request, int $id): RedirectResponse
    {
        abort_unless(DB::table(self::FLOOD_TABLE)->where('id', $id)->whereNull('deleted_at')->exists(), 404);
        DB::table(self::FLOOD_TABLE)->where('id', $id)->update($this->validatedFloodRecord($request, $id));

        return redirect()->route('operational-records.index', ['dataset' => 'flood-records'])
            ->with('success', 'Flood analytics record updated successfully.');
    }

    public function destroyFlood(int $id): RedirectResponse
    {
        $updated = DB::table(self::FLOOD_TABLE)->where('id', $id)->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
        abort_if($updated === 0, 404);

        return redirect()->route('operational-records.index', ['dataset' => 'flood-records'])
            ->with('success', 'Flood analytics record removed.');
    }

    private function validatedFloodRecord(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:50', 'unique:' . self::FLOOD_TABLE . ',event_id' . ($id ? ',' . $id : '')],
            'event_date' => ['required', 'date'],
            'barangay' => ['required', 'string', 'max:100'],
            'nearest_waterway' => ['required', 'string', 'max:150'],
            'storm_signal' => ['required', 'integer', 'min:0', 'max:5'],
            'elevation_m' => ['required', 'numeric', 'min:-20', 'max:500'],
            'distance_to_waterway_m' => ['required', 'numeric', 'min:0'],
            'drainage_index' => ['required', 'numeric', 'min:0', 'max:1'],
            'impervious_surface_ratio' => ['required', 'numeric', 'min:0', 'max:1'],
            'population_density_per_km2' => ['required', 'numeric', 'min:0'],
            'historical_flood_count_5y' => ['required', 'integer', 'min:0'],
            'rainfall_24h_mm' => ['required', 'numeric', 'min:0'],
            'rainfall_3d_mm' => ['required', 'numeric', 'min:0'],
            'rainfall_7d_mm' => ['required', 'numeric', 'min:0'],
            'temperature_c' => ['required', 'numeric', 'min:-10', 'max:60'],
            'humidity_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'wind_speed_kph' => ['required', 'numeric', 'min:0'],
            'tide_level_m' => ['required', 'numeric', 'min:-5', 'max:10'],
            'flood_depth_mm' => ['required', 'numeric', 'min:0'],
            'duration_hours' => ['required', 'numeric', 'min:0'],
            'risk_level' => ['required', 'in:Low,Medium,High'],
        ]);

        $date = Carbon::parse($validated['event_date']);
        $validated['year'] = $date->year;
        $validated['month'] = $date->month;
        $validated['day_of_week'] = $date->format('l');
        $validated['is_weekend'] = $date->isWeekend();
        $validated['wet_season'] = $date->month >= 5 && $date->month <= 11;

        return $validated;
    }

    private function filteredQuery(array $dataset, array $filters): Builder
    {
        $table = $dataset['table'];
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }

        if (($dataset['joins_barangays'] ?? false) && Schema::hasTable('barangays')) {
            $query->leftJoin('barangays', "{$table}.barangay_id", '=', 'barangays.id');
        }

        $selects = [];

        foreach (array_keys($dataset['columns']) as $column) {
            $selects[] = $column === 'barangay_name'
                ? 'barangays.name as barangay_name'
                : "{$table}.{$column}";
        }

        $query->select($selects);

        if ($filters['search'] !== '' && $dataset['search_columns'] !== []) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($dataset, $table, $search): void {
                foreach ($dataset['search_columns'] as $index => $column) {
                    $qualified = $column === 'barangay_name'
                        ? 'barangays.name'
                        : "{$table}.{$column}";

                    $index === 0
                        ? $builder->where($qualified, 'like', "%{$search}%")
                        : $builder->orWhere($qualified, 'like', "%{$search}%");
                }
            });
        }

        if ($filters['date_from'] !== '' && $dataset['date_column'] !== null) {
            $query->whereDate("{$table}.{$dataset['date_column']}", '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '' && $dataset['date_column'] !== null) {
            $query->whereDate("{$table}.{$dataset['date_column']}", '<=', $filters['date_to']);
        }

        if ($filters['status'] !== '' && $dataset['status_column'] !== null) {
            $query->where("{$table}.{$dataset['status_column']}", $filters['status']);
        }

        if ($filters['barangay_id'] !== null) {
            if (($dataset['joins_barangays'] ?? false)) {
                $query->where("{$table}.barangay_id", $filters['barangay_id']);
            } elseif (($dataset['barangay_text_column'] ?? null) !== null) {
                $barangayName = Barangay::query()->whereKey($filters['barangay_id'])->value('name');

                if ($barangayName !== null) {
                    $query->where("{$table}.{$dataset['barangay_text_column']}", $barangayName);
                }
            }
        }

        return $query->orderByDesc("{$table}.{$dataset['order_column']}");
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:50'],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'date_from' => (string) ($validated['date_from'] ?? ''),
            'date_to' => (string) ($validated['date_to'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
            'barangay_id' => isset($validated['barangay_id'])
                ? (int) $validated['barangay_id']
                : null,
        ];
    }

    private function resolveDataset(array $datasets, string $datasetKey): array
    {
        abort_unless(array_key_exists($datasetKey, $datasets), 404, 'Unknown operational dataset.');
        $dataset = $datasets[$datasetKey];
        abort_unless(Schema::hasTable($dataset['table']), 404, 'This operational dataset is not available.');

        $table = $dataset['table'];
        $canJoinBarangays = ($dataset['joins_barangays'] ?? false)
            && Schema::hasTable('barangays')
            && Schema::hasColumn($table, 'barangay_id');

        $dataset['joins_barangays'] = $canJoinBarangays;
        $dataset['columns'] = collect($dataset['columns'])
            ->filter(fn (string $heading, string $column): bool =>
                $column === 'barangay_name'
                    ? $canJoinBarangays
                    : Schema::hasColumn($table, $column)
            )
            ->all();
        $dataset['search_columns'] = collect($dataset['search_columns'])
            ->filter(fn (string $column): bool =>
                $column === 'barangay_name'
                    ? $canJoinBarangays
                    : Schema::hasColumn($table, $column)
            )
            ->values()
            ->all();
        $dataset['date_column'] = $dataset['date_column'] !== null
            && Schema::hasColumn($table, $dataset['date_column'])
                ? $dataset['date_column']
                : null;
        $dataset['status_column'] = $dataset['status_column'] !== null
            && Schema::hasColumn($table, $dataset['status_column'])
                ? $dataset['status_column']
                : null;
        $dataset['order_column'] = Schema::hasColumn($table, $dataset['order_column'])
            ? $dataset['order_column']
            : 'id';

        return $dataset;
    }

    private function csvValue(mixed $value): string|int|float|null
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_string($value) && preg_match('/^[=+\-@]/', $value) === 1) {
            return "'{$value}";
        }

        return $value;
    }

    private function baseTableCount(string $table): int
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }

    private function datasets(): array
    {
        return [
            'flood-records' => [
                'label' => 'Flood Analytics Dataset', 'table' => self::FLOOD_TABLE,
                'date_column' => 'event_date', 'status_column' => 'risk_level',
                'barangay_text_column' => 'barangay', 'order_column' => 'event_date',
                'search_columns' => ['event_id', 'barangay', 'nearest_waterway'],
                'statuses' => ['Low', 'Medium', 'High'], 'crud_route' => null,
                'columns' => ['id' => 'ID', 'event_id' => 'Event ID', 'event_date' => 'Event Date', 'barangay' => 'Barangay', 'risk_level' => 'Risk Level', 'rainfall_24h_mm' => 'Rainfall 24h (mm)', 'rainfall_3d_mm' => 'Rainfall 3d (mm)', 'rainfall_7d_mm' => 'Rainfall 7d (mm)', 'flood_depth_mm' => 'Flood Depth (mm)', 'duration_hours' => 'Duration (hours)', 'nearest_waterway' => 'Nearest Waterway', 'storm_signal' => 'Storm Signal'],
            ],
            'fire-incidents' => [
                'label' => 'Fire Incidents', 'table' => 'fire_incidents',
                'date_column' => 'reported_at', 'status_column' => 'status',
                'joins_barangays' => true, 'order_column' => 'reported_at',
                'search_columns' => ['incident_number', 'incident_type', 'location', 'barangay_name'],
                'statuses' => ['Reported', 'Responding', 'Controlled', 'Resolved'], 'crud_route' => 'fire-incidents.index',
                'columns' => ['id' => 'ID', 'incident_number' => 'Incident Number', 'reported_at' => 'Reported At', 'barangay_name' => 'Barangay', 'incident_type' => 'Type', 'location' => 'Location', 'severity' => 'Severity', 'status' => 'Status'],
            ],
            'fire-hydrants' => [
                'label' => 'Fire Hydrants', 'table' => 'fire_hydrants',
                'date_column' => 'created_at', 'status_column' => 'status',
                'joins_barangays' => true, 'order_column' => 'id',
                'search_columns' => ['hydrant_code', 'location', 'barangay_name'],
                'statuses' => ['Active', 'Inactive', 'Maintenance'], 'crud_route' => 'fire-hydrants.index',
                'columns' => ['id' => 'ID', 'hydrant_code' => 'Hydrant Code', 'barangay_name' => 'Barangay', 'location' => 'Location', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'status' => 'Status', 'last_inspection_date' => 'Last Inspection'],
            ],
            'prediction-runs' => [
                'label' => 'Prediction Runs', 'table' => 'prediction_runs',
                'date_column' => 'requested_at', 'status_column' => 'status',
                'joins_barangays' => true, 'order_column' => 'requested_at',
                'search_columns' => ['source', 'status', 'barangay_name'],
                'statuses' => ['pending', 'completed', 'failed'], 'crud_route' => 'prediction.index',
                'columns' => ['id' => 'ID', 'requested_at' => 'Requested At', 'barangay_name' => 'Barangay', 'source' => 'Source', 'status' => 'Status', 'requested_by_user_id' => 'Requested By User ID', 'error_message' => 'Error'],
            ],
            'flood-predictions' => [
                'label' => 'Prediction Results', 'table' => 'flood_predictions',
                'date_column' => 'predicted_at', 'status_column' => 'predicted_risk_level',
                'order_column' => 'predicted_at', 'search_columns' => ['predicted_risk_level'],
                'statuses' => ['Low', 'Medium', 'High'], 'crud_route' => 'prediction.index',
                'columns' => ['id' => 'ID', 'prediction_run_id' => 'Run ID', 'predicted_at' => 'Predicted At', 'predicted_risk_level' => 'Risk Level', 'high_probability' => 'High Probability', 'predicted_depth_mm' => 'Depth (mm)', 'predicted_duration_hours' => 'Duration (hours)', 'is_alert_triggered' => 'Alert Triggered'],
            ],
            'weather-observations' => [
                'label' => 'Weather Observations', 'table' => 'weather_observations',
                'date_column' => 'observed_at', 'status_column' => null,
                'joins_barangays' => true, 'order_column' => 'observed_at',
                'search_columns' => ['station_name', 'source', 'weather_condition', 'barangay_name'],
                'statuses' => [], 'crud_route' => 'prediction.index',
                'columns' => ['id' => 'ID', 'observed_at' => 'Observed At', 'barangay_name' => 'Barangay', 'station_name' => 'Station', 'source' => 'Source', 'rainfall_24h_mm' => 'Rainfall 24h (mm)', 'temperature_c' => 'Temperature (C)', 'relative_humidity_pct' => 'Humidity (%)', 'weather_condition' => 'Condition'],
            ],
            'sms-logs' => [
                'label' => 'SMS Delivery Logs', 'table' => 'sms_logs',
                'date_column' => 'sent_at', 'status_column' => 'status',
                'order_column' => 'id', 'search_columns' => ['recipient_name', 'phone_number', 'source', 'failure_reason'],
                'statuses' => ['pending', 'sent', 'failed'], 'crud_route' => 'sms.index',
                'columns' => ['id' => 'ID', 'sent_at' => 'Sent At', 'recipient_name' => 'Recipient', 'phone_number' => 'Phone Number', 'source' => 'Source', 'status' => 'Status', 'http_status' => 'HTTP Status', 'failure_reason' => 'Failure Reason'],
            ],
            'sms-recipients' => [
                'label' => 'SMS Recipients', 'table' => 'sms_recipients',
                'date_column' => 'created_at', 'status_column' => null,
                'order_column' => 'id', 'search_columns' => ['full_name', 'phone_number', 'position', 'office_or_barangay'],
                'statuses' => [], 'crud_route' => 'sms.index',
                'columns' => ['id' => 'ID', 'full_name' => 'Name', 'phone_number' => 'Phone Number', 'position' => 'Position', 'office_or_barangay' => 'Office/Barangay', 'receive_flood_alerts' => 'Flood Alerts', 'receive_fire_alerts' => 'Fire Alerts', 'is_active' => 'Active'],
            ],
            'barangays' => [
                'label' => 'Barangay Profiles', 'table' => 'barangays',
                'date_column' => 'created_at', 'status_column' => null,
                'order_column' => 'name', 'search_columns' => ['name'],
                'statuses' => [], 'crud_route' => 'gis.index',
                'columns' => ['id' => 'ID', 'name' => 'Barangay', 'district' => 'District', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'historical_flood_count_5y' => 'Historical Flood Count (5y)', 'is_active' => 'Active'],
            ],
        ];
    }
}
