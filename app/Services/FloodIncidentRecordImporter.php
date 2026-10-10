<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FloodIncidentRecordImporter
{
    public function import(string $path): int
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Flood incident file is not readable.');
        }
        $contents = file_get_contents($path);
        if (str_ends_with($path, '.gz')) {
            $contents = gzdecode($contents);
        }
        if ($contents === false) {
            throw new RuntimeException('Flood incident file could not be decoded.');
        }
        $hash = hash('sha256', $contents);
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $contents);
        rewind($handle);
        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new RuntimeException('Flood incident file has no header.');
            }
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            $required = ['EVENT_ID', 'OBSERVATION_DATETIME', 'FLOOD_START_DATETIME', 'FLOOD_SUBSIDED_DATETIME', 'DURATION_HOURS', 'STATUS', 'BARANGAY', 'FLOOD_CODE', 'LATITUDE', 'LONGITUDE', 'NEAREST_WATERWAY', 'ELEVATION_M', 'DISTANCE_TO_WATERWAY_M', 'RAINFALL_24H_MM', 'RAINFALL_3D_MM', 'RAINFALL_7D_MM', 'TEMPERATURE_C', 'TEMP_MAX_C', 'TEMP_MIN_C', 'WIND_SPEED_KPH', 'WIND_DIRECTION_DEG', 'STORM_SIGNAL', 'YEAR'];
            if (array_diff($required, $headers) !== []) {
                throw new RuntimeException('Flood incident file is missing required columns.');
            }
            // Validate every row before writing; event IDs may repeat in the supplied history.
            $rows = [];
            $number = 0;
            while (($values = fgetcsv($handle)) !== false) {
                $number++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException('Invalid flood CSV row '.($number + 1));
                }
                $source = array_combine($headers, $values);
                $row = [];
                foreach ($required as $column) {
                    $row[strtolower($column)] = trim($source[$column]);
                }
                $observation = Carbon::createFromFormat('!n/j/Y', $row['observation_datetime'], 'Asia/Manila');
                if ($observation->format('n/j/Y') !== $row['observation_datetime']) {
                    throw new RuntimeException('Invalid observation date on row '.($number + 1));
                }
                $start = Carbon::createFromFormat('!n/j/Y g:i:s A', $observation->format('n/j/Y').' '.$row['flood_start_datetime'], 'Asia/Manila');
                $end = $row['flood_subsided_datetime'] !== ''
                    ? Carbon::createFromFormat('!n/j/Y H:i', $row['flood_subsided_datetime'], 'Asia/Manila')
                    : null;
                if (! in_array($row['flood_code'], ['A', 'B', 'C', 'D'], true)
                    || ! in_array($row['status'], ['Active', 'Subsided'], true)
                    || $row['event_id'] === '' || $row['barangay'] === ''
                    || ($row['status'] === 'Subsided' && $end === null)
                    || ($end !== null && $end->lt($start))) {
                    throw new RuntimeException('Invalid flood incident on row '.($number + 1));
                }
                foreach (array_diff($required, ['EVENT_ID', 'OBSERVATION_DATETIME', 'FLOOD_START_DATETIME', 'FLOOD_SUBSIDED_DATETIME', 'STATUS', 'BARANGAY', 'FLOOD_CODE', 'NEAREST_WATERWAY']) as $column) {
                    $key = strtolower($column);
                    if (! is_numeric($row[$key]) || ! is_finite((float) $row[$key])) {
                        throw new RuntimeException('Invalid '.$column.' on row '.($number + 1));
                    }
                }
                $row['observation_datetime'] = $observation->format('Y-m-d H:i:s');
                $row['flood_start_datetime'] = $start->format('Y-m-d H:i:s');
                $row['flood_subsided_datetime'] = $end?->format('Y-m-d H:i:s');
                $row['event_date'] = $observation->toDateString();
                $row['month'] = $observation->month;
                $row['day_of_week'] = $observation->format('l');
                $row['source_file_hash'] = $hash;
                $row['source_row'] = $number;
                $row['created_at'] = now();
                $row['updated_at'] = now();
                $rows[] = $row;
            }
            if ($rows === []) {
                throw new RuntimeException('Flood incident file has no records.');
            }
            DB::transaction(function () use ($rows): void {
                foreach (array_chunk($rows, 250) as $batch) {
                    // Re-running the same file cannot duplicate or overwrite reviewed records.
                    DB::table('flood_incident_records')->upsert($batch, ['source_file_hash', 'source_row'], ['source_file_hash']);
                }
            });

            return count($rows);
        } finally {
            fclose($handle);
        }
    }
}
