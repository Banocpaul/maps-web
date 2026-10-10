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

        $expected = array_merge(array_map(fn ($i) => sprintf('FIR-%04d', $i), range(1, 37)),
            array_map(fn ($i) => sprintf('FIR-EX-%04d', $i), range(1, 80)));
        if (count($rows) !== 117 || array_diff($expected, array_keys($rows)) !== []) {
            throw new RuntimeException('Replacement requires the complete 117-row fire dataset.');
        }

        $testData = json_decode(file_get_contents(database_path('data/fire-incident-test-records.json')), true, 512, JSON_THROW_ON_ERROR);
        if (($testData['source_sha256'] ?? null) !== $source['sha256'] || count($testData['records'] ?? []) !== 117) {
            throw new RuntimeException('Invalid complete fire test dataset.');
        }
        foreach (array_keys($rows) as $id) {
            $test = $testData['records'][$id] ?? [];
            foreach (['barangay', 'location', 'alarm_level', 'cause', 'severity'] as $field) {
                if (! is_string($test[$field] ?? null) || trim($test[$field]) === '') {
                    throw new RuntimeException('Incomplete fire test row: '.$id);
                }
            }
            if (! in_array($test['severity'], ['Minor', 'Moderate', 'Major'], true)
                || ! in_array($test['alarm_level'], ['1st', '2nd', '3rd', '4th', '5th', 'Task Force Alpha', 'Task Force Bravo', 'General Alarm'], true)
                || ! is_array($test['generated_fields'] ?? null)) {
                throw new RuntimeException('Invalid fire test classification: '.$id);
            }
            foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
                if (! is_numeric($test[$field] ?? null) || abs($test[$field]) > $limit) {
                    throw new RuntimeException('Invalid fire test coordinate: '.$id);
                }
            }
        }

        return DB::transaction(function () use ($source, $rows, $testData): array {
            // Claim activation atomically. A later deployment must not archive new staff reports.
            $activate = DB::table('fire_dataset_activations')->insertOrIgnore([
                'version' => 'fire-records-117-v1', 'source_sha256' => $source['sha256'], 'created_at' => now(),
            ]) === 1;
            $complete = DB::table('fire_dataset_activations')->insertOrIgnore([
                'version' => 'fire-records-complete-test-v2', 'source_sha256' => $source['sha256'], 'created_at' => now(),
            ]) === 1;
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
            $summary = ['imported' => 0, 'preserved' => 0, 'dataset_rows' => count($rows), 'previous_records' => 0];
            foreach ($rows as $id => [$row, $start, $end]) {
                $example = str_starts_with($id, 'FIR-EX-');
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
                        'record_classification' => 'Dataset',
                        'source_origin' => $example ? 'Modeled' : 'Transcribed',
                        'data_source' => $source['source'],
                        'source_record' => ['sha256' => $source['sha256'], 'values' => $row,
                            'record_origin' => $example ? 'Modeled' : 'Transcribed', 'reported_at_is_fallback' => true, 'crosses_midnight' => $start->toDateString() !== $end->toDateString()],
                        'remarks' => $example ? 'Included in the current project dataset. Source origin: modeled.'
                            : 'Transcribed source record; not independently validated. Coordinates are approximate; cause and reference alarm are unconfirmed.',
                    ]);
                $summary[$incident->wasRecentlyCreated ? 'imported' : 'preserved']++;
                if ($activate) {
                    $provenance = $incident->source_record ?? [];
                    $provenance['record_origin'] = $example ? 'Modeled' : 'Transcribed';
                    $incident->record_classification = 'Dataset';
                    $incident->source_origin = $example ? 'Modeled' : 'Transcribed';
                    $incident->source_record = $provenance;
                    if ($incident->remarks === 'Modeled example. Excluded from operational totals and alerts.') {
                        $incident->remarks = 'Included in the current project dataset. Source origin: modeled.';
                    }
                    $incident->deleted_at = null;
                    $incident->save();
                }
                if ($complete || $incident->wasRecentlyCreated) {
                    $test = $testData['records'][$id];
                    $barangay = $barangays->get($this->key($test['barangay']));
                    if (! $barangay) throw new RuntimeException('Unknown fire test barangay: '.$id);
                    $provenance = $incident->source_record ?? ['sha256' => $source['sha256'], 'values' => $row];
                    $provenance['test_dataset'] = true;
                    $provenance['generated_fields'] = $test['generated_fields'];
                    $incident->fill([
                        'barangay_id' => $barangay->id, 'street' => $test['location'], 'location' => $test['location'],
                        'alarm_level' => $test['alarm_level'], 'cause' => $test['cause'], 'severity' => $test['severity'],
                        'latitude' => $test['latitude'], 'longitude' => $test['longitude'],
                        'coordinate_accuracy' => 'Approximate', 'source_record' => $provenance,
                    ])->save();
                }
            }
            if ($activate) {
                $summary['previous_records'] = FireIncident::withoutGlobalScope('operational_records')->withTrashed()
                    ->whereNotIn('incident_number', array_keys($rows))
                    ->update(['record_classification' => 'Superseded']);
            }
            return $summary;
        });
    }

    private function key(string $name): string
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
        return str_replace('hagdangbato', 'hagdanbato', $key);
    }
}
