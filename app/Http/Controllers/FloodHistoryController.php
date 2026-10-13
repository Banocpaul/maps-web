<?php

namespace App\Http\Controllers;

use App\Models\FloodIncidentRecord;
use App\Models\FloodObservationChange;
use App\Models\FloodTrainingRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FloodHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tab' => ['nullable', Rule::in(['subsided', 'historical', 'all'])],
            'search' => ['nullable', 'string', 'max:150'],
            'level' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Check the record filters.', 'errors' => $validator->errors()], 422);
        $filters = $validator->validated();
        $tab = $filters['tab'] ?? 'subsided';
        $observations = DB::table('flood_training_records')->whereNull('deleted_at');
        $records = DB::table('flood_incident_records')->whereNull('deleted_at');
        if ($tab === 'historical') {
            $observations->whereRaw('1 = 0');
            $records->whereNotNull('source_file_hash');
        } elseif ($tab === 'subsided') {
            $observations->where('flood_status', 'Subsided');
            $records->whereNull('source_file_hash')->where('status', 'Subsided');
        }
        foreach ([[$observations, 'flood_level_code', 'observed_at', true], [$records, 'flood_code', 'flood_start_datetime', false]] as [$query, $code, $date, $utc]) {
            if (! empty($filters['search'])) {
                $query->where('barangay', 'like', '%'.$filters['search'].'%');
            }
            if (! empty($filters['level'])) $query->where($code, $filters['level']);
            $from = $filters['date_from'] ?? null;
            $to = $filters['date_to'] ?? null;
            if (! empty($filters['year'])) {
                $yearFrom = $filters['year'].'-01-01';
                $yearTo = $filters['year'].'-12-31';
                $from = $from ? max($from, $yearFrom) : $yearFrom;
                $to = $to ? min($to, $yearTo) : $yearTo;
            }
            if ($from) {
                $start = Carbon::parse($from, 'Asia/Manila')->startOfDay();
                $query->where($date, '>=', ($utc ? $start->utc() : $start)->format('Y-m-d H:i:s'));
            }
            if ($to) {
                $end = Carbon::parse($to, 'Asia/Manila')->addDay()->startOfDay();
                $query->where($date, '<', ($utc ? $end->utc() : $end)->format('Y-m-d H:i:s'));
            }
        }
        $observations->select('id', 'barangay', 'location_name as location', 'observed_at as start_at',
            'subsided_at as end_at', 'duration_hours', 'flood_level_code as code', 'flood_status as status',
            DB::raw("'observation' AS origin"), DB::raw("'Verified observation' AS source"),
            DB::raw("NULL AS reference"), DB::raw('observed_at AS sort_at'));
        $sort = DB::connection()->getDriverName() === 'sqlite'
            ? "datetime(flood_start_datetime, '-8 hours')" : 'DATE_SUB(flood_start_datetime, INTERVAL 8 HOUR)';
        $records->select('id', 'barangay', DB::raw('NULL AS location'), 'flood_start_datetime as start_at',
            'flood_subsided_datetime as end_at', 'duration_hours', 'flood_code as code', 'status',
            DB::raw("'record' AS origin"),
            DB::raw("CASE WHEN source_file_hash IS NULL THEN 'Operational record' ELSE 'Historical dataset' END AS source"),
            'event_id as reference', DB::raw($sort.' AS sort_at'));
        $page = DB::query()->fromSub($observations->unionAll($records), 'flood_history')
            ->orderByDesc('sort_at')->orderBy('origin')->orderByDesc('id')->paginate(25);
        $page->getCollection()->transform(function ($row) {
            $zone = $row->origin === 'observation' ? 'UTC' : 'Asia/Manila';
            $row->start_at = $this->date($row->start_at, $zone);
            $row->end_at = $this->date($row->end_at, $zone);
            $row->reference = $row->origin === 'observation' ? 'OBS-'.$row->id : $row->reference;
            unset($row->sort_at);
            return $row;
        });
        return response()->json([
            'records' => $page,
            'counts' => [
                'historical' => FloodIncidentRecord::whereNotNull('source_file_hash')->count(),
                'subsided' => FloodTrainingRecord::where('flood_status', 'Subsided')->count()
                    + FloodIncidentRecord::whereNull('source_file_hash')->where('status', 'Subsided')->count(),
                'all' => FloodTrainingRecord::count() + FloodIncidentRecord::count(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(string $origin, int $id): JsonResponse
    {
        abort_unless(in_array($origin, ['observation', 'record'], true), 404);
        $observation = $origin === 'observation';
        $record = $observation ? FloodTrainingRecord::findOrFail($id) : FloodIncidentRecord::findOrFail($id);
        $fields = $record->only(['barangay', 'duration_hours', 'latitude', 'longitude', 'rainfall_24h_mm',
            'rainfall_3d_mm', 'rainfall_7d_mm', 'temperature_c', 'humidity_pct', 'nearest_waterway',
            'elevation_m', 'distance_to_waterway_m', 'enrichment_status']);
        $fields['source'] = $observation ? 'Verified observation' : ($record->source_file_hash ? 'Historical dataset' : 'Operational record');
        $fields['reference'] = $observation ? 'OBS-'.$id : $record->event_id;
        $fields['location'] = $observation ? $record->location_name : null;
        $fields['code'] = $observation ? $record->flood_level_code : $record->flood_code;
        $fields['status'] = $observation ? $record->flood_status : $record->status;
        $zone = $observation ? 'UTC' : 'Asia/Manila';
        $fields['start_at'] = $this->date($observation ? $record->observed_at : $record->flood_start_datetime, $zone);
        $fields['end_at'] = $this->date($observation ? $record->subsided_at : $record->flood_subsided_datetime, $zone);
        if ($observation) {
            $history = FloodObservationChange::where('flood_training_record_id', $id)->latest('id')->get()
                ->map(fn ($change) => ['action' => $change->action, 'actor' => $change->changed_by_name ?? 'Former user',
                    'at' => $this->date($change->observed_at, 'UTC'), 'recorded_at' => $this->date($change->created_at, 'UTC'),
                    'code' => $change->previous_code.' → '.$change->new_code, 'note' => $change->note]);
        } else {
            $history = DB::table('flood_incident_updates')->leftJoin('users', 'users.id', '=', 'flood_incident_updates.user_id')
                ->where('flood_incident_record_id', $id)->orderByDesc('flood_incident_updates.id')
                ->select('action', 'previous_code', 'flood_code', 'flood_incident_updates.created_at', 'users.first_name', 'users.last_name')
                ->get()->map(fn ($change) => ['action' => $change->action,
                    'actor' => trim(($change->first_name ?? '').' '.($change->last_name ?? '')) ?: 'Former user',
                    'at' => $this->date($change->created_at, 'UTC'),
                    'code' => $change->previous_code.' → '.$change->flood_code, 'note' => null]);
        }
        return response()->json(['record' => $fields, 'history' => $history])->header('Cache-Control', 'private, no-store');
    }

    private function date($value, string $zone): ?string
    {
        return $value ? Carbon::parse($value, $zone)->timezone('Asia/Manila')->toIso8601String() : null;
    }
}
