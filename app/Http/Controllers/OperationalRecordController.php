<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalRecordController extends Controller
{
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
                'label' => 'Flood Records', 'table' => 'flood_training_records',
                'date_column' => 'observed_at', 'status_column' => 'risk_level',
                'barangay_text_column' => 'barangay', 'order_column' => 'observed_at',
                'search_columns' => ['barangay', 'data_source', 'nearest_waterway', 'remarks'],
                'statuses' => ['Low', 'Medium', 'High'], 'crud_route' => 'flood-operation.index',
                'columns' => ['id' => 'ID', 'observed_at' => 'Observed At', 'barangay' => 'Barangay', 'risk_level' => 'Risk Level', 'rainfall_24h_mm' => 'Rainfall 24h (mm)', 'flood_depth_mm' => 'Flood Depth (mm)', 'duration_hours' => 'Duration (hours)', 'data_source' => 'Data Source'],
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
