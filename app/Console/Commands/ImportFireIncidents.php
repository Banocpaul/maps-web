<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\FireIncident;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFireIncidents extends Command
{
    protected $signature = 'fire:import
                            {file=fire_incidents_cleaned.csv}
                            {--replace : Delete previously imported historical records first}';

    protected $description = 'Import cleaned historical fire incidents from a CSV file';

    public function handle(): int
    {
        $fileName = $this->argument('file');
        $filePath = storage_path('app/imports/' . $fileName);

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $deleted = FireIncident::where('data_source', 'Historical FireData.xlsx')->delete();
            $this->info("Deleted {$deleted} previously imported historical records.");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error('Unable to open CSV.');
            return self::FAILURE;
        }

        $headers = fgetcsv($handle);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $headers = array_map(fn($h)=>trim((string)$h), $headers);

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {

                if (count($row) != count($headers)) {
                    $skipped++;
                    continue;
                }

                $data = array_combine($headers, $row);

                $barangay = Barangay::whereRaw(
                    'LOWER(name)=?',
                    [mb_strtolower(trim($data['barangay'] ?? ''))]
                )->first();

                if (!$barangay) {
                    $skipped++;
                    continue;
                }

                $occurredAt = $this->parseDateTime($data['occurred_at'] ?? null);
                $fireOutAt  = $this->parseDateTime($data['fire_out_at'] ?? null);

                $attributes = [
                    'barangay_id' => $barangay->id,
                    'incident_type' => $this->nullableString($data['incident_type'] ?? null) ?? 'Structural Fire',
                    'location' => $this->nullableString($data['location'] ?? null) ?? ('Location not specified - '.$barangay->name),
                    'latitude' => null,
                    'longitude' => null,
                    'severity' => $this->normalizeSeverity($data['severity'] ?? null),
                    'status' => $this->nullableString($data['status'] ?? null) ?? 'Resolved',

                    'reported_at' => $occurredAt,
                    'responded_at' => $occurredAt,
                    'resolved_at' => $fireOutAt,

                    'occurred_at' => $occurredAt,
                    'fire_out_at' => $fireOutAt,

                    'duration_minutes' => $this->nullableInteger($data['duration_minutes'] ?? null),
                    'individuals_affected' => $this->nullableInteger($data['individuals_affected'] ?? null) ?? 0,
                    'houses_destroyed' => $this->nullableInteger($data['houses_destroyed'] ?? null) ?? 0,

                    'alarm_level' => $this->nullableString($data['alarm_level'] ?? null),
                    'data_source' => $this->nullableString($data['data_source'] ?? null),
                    'remarks' => $this->nullableString($data['remarks'] ?? null),
                ];

                $record = FireIncident::where('incident_number', $data['incident_number'])->first();

                if ($record) {
                    $record->update($attributes);
                    $updated++;
                } else {
                    FireIncident::create([
                        'incident_number' => $data['incident_number'],
                        ...$attributes
                    ]);
                    $imported++;
                }
            }

            DB::commit();
            fclose($handle);

        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);

            $this->error('Import failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->table(
            ['Result','Count'],
            [
                ['Imported',$imported],
                ['Updated',$updated],
                ['Skipped',$skipped],
            ]
        );

        return self::SUCCESS;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        $value = trim((string)$value);
        return $value === '' ? null : (int)$value;
    }

    private function normalizeSeverity(mixed $value): string
    {
        $value = mb_strtolower(trim((string)$value));

        return match($value){
            'critical','high','major','severe' => 'Major',
            'moderate','medium' => 'Moderate',
            default => 'Minor',
        };
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        $value = trim((string)$value);
        if ($value === '') return null;

        return Carbon::createFromFormat('Y-m-d H:i:s', $value);
    }
}