<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\FireIncident;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FireIncidentRecordImporter
{
    public function import(?string $path = null): array
    {
        $source = json_decode(file_get_contents($path ?? database_path('data/fire-incident-records.json')), true, 512, JSON_THROW_ON_ERROR);
        if (($source['source'] ?? null) !== 'Fire Records(1).xlsx' || ! is_array($source['records'] ?? null)) {
            throw new RuntimeException('Invalid fire incident source.');
        }

        // Validate every row before creating barangays or touching existing records.
        $rows = [];
        foreach ($source['records'] as $row) {
            $id = $row['Incident ID'] ?? '';
            if (! preg_match('/^FIR-(?:EX-)?\d{4}$/', $id) || isset($rows[$id])) {
                throw new RuntimeException('Invalid or duplicate fire incident ID.');
            }
            $start = Carbon::createFromFormat('!Y-m-d g:i A', $row['Date'].' '.$row['Time Occurred'], 'Asia/Manila');
            $end = Carbon::createFromFormat('!Y-m-d g:i A', $row['Date'].' '.$row['Fire Out'], 'Asia/Manila');
            if ($end->lt($start)) $end->addDay();
            foreach (['Individuals Affected', 'Houses Destroyed', 'Duration (minutes)'] as $field) {
                if (! is_int($row[$field] ?? null) || $row[$field] < 0) {
                    throw new RuntimeException('Invalid fire impact or duration: '.$id);
                }
            }
            if (! is_string($row['Barangay'] ?? null) || trim($row['Barangay']) === '') {
                throw new RuntimeException('Missing source barangay: '.$id);
            }
            foreach (['Latitude (approx.)' => 90, 'Longitude (approx.)' => 180] as $field => $limit) {
                if (($row[$field] ?? null) !== null && (! is_numeric($row[$field]) || abs($row[$field]) > $limit)) {
                    throw new RuntimeException('Invalid source coordinate: '.$id);
                }
            }
            $rows[$id] = [$row, $start, $end];
        }

        return DB::transaction(function () use ($source, $rows): array {
            // Only create missing canonical barangays; never alter existing assignments/profiles.
            $canonical = ['Addition Hills' => 1, 'Bagong Silang' => 1, 'Barangka Drive' => 2,
                'Barangka Ibaba' => 2, 'Barangka Ilaya' => 2, 'Barangka Itaas' => 2,
                'Buayang Bato' => 2, 'Burol' => 1, 'Daang Bakal' => 1, 'Hagdang Bato Itaas' => 1,
                'Hagdang Bato Libis' => 1, 'Highway Hills' => 1, 'Hulo' => 2, 'Mabini-J. Rizal' => 2,
                'Mauway' => 1, 'Namayan' => 2, 'New Zaniga' => 1, 'Old Zaniga' => 2,
                'Plainview' => 2, 'Pleasant Hills' => 1, 'Poblacion' => 1, 'San Jose' => 2, 'Vergara' => 2];
            $barangays = Barangay::all()->keyBy(fn ($barangay) => $this->key($barangay->name));
            foreach ($canonical as $name => $district) {
                if (! $barangays->has($this->key($name))) {
                    $barangays->put($this->key($name), Barangay::firstOrCreate(['name' => $name],
                        ['district' => $district, 'is_active' => true]));
                }
            }
            $summary = ['imported' => 0, 'preserved' => 0, 'reported' => 0, 'examples' => 0];
            foreach ($rows as $id => [$row, $start, $end]) {
                $example = str_starts_with($id, 'FIR-EX-');
                $summary[$example ? 'examples' : 'reported']++;
                $barangay = $barangays->get($this->key($row['Barangay']));
                $incident = FireIncident::withoutGlobalScope('operational_records')->withTrashed()
                    ->firstOrCreate(['incident_number' => $id], [
                        'barangay_id' => $barangay?->id, 'source_barangay' => $row['Barangay'],
                        'incident_type' => 'Unspecified', 'location' => $row['Street / Location'] ?: 'Street not recorded — '.$row['Barangay'],
                        'street' => $row['Street / Location'], 'severity' => null, 'status' => 'Resolved',
                        'latitude' => $row['Latitude (approx.)'], 'longitude' => $row['Longitude (approx.)'],
                        'coordinate_accuracy' => $row['Latitude (approx.)'] !== null ? 'Approximate' : 'Unspecified',
                        'occurred_at' => $start->copy()->utc(), 'fire_out_at' => $end->copy()->utc(),
                        // Report time is absent; the fallback is explicitly marked in the source record.
                        'reported_at' => $start->copy()->utc(), 'responded_at' => null, 'resolved_at' => $end->copy()->utc(),
                        'individuals_affected' => $row['Individuals Affected'], 'houses_destroyed' => $row['Houses Destroyed'],
                        'alarm_level' => $row['Alarm (reported)'], 'alarm_reference' => $row['Alarm'],
                        'cause' => null, 'cause_reference' => $row['Cause '],
                        'record_classification' => $example ? 'Example' : 'Reported',
                        'data_source' => $source['source'],
                        'source_record' => ['sha256' => $source['sha256'], 'values' => $row,
                            'reported_at_is_fallback' => true, 'crosses_midnight' => $start->toDateString() !== $end->toDateString()],
                        'remarks' => $example ? 'Modeled example. Excluded from operational totals and alerts.'
                            : 'Transcribed source record; not independently validated. Coordinates are approximate; cause and reference alarm are unconfirmed.',
                    ]);
                $summary[$incident->wasRecentlyCreated ? 'imported' : 'preserved']++;
            }
            // Preserve the previous spreadsheet, but avoid double-counting two historical sources.
            FireIncident::withoutGlobalScope('operational_records')
                ->where('data_source', 'Historical FireData.xlsx')
                ->where('record_classification', 'Reported')->update(['record_classification' => 'Superseded']);
            return $summary;
        });
    }

    private function key(string $name): string
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
        return str_replace('hagdangbato', 'hagdanbato', $key);
    }
}
