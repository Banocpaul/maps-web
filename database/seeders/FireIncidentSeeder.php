<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\FireIncident;
use Illuminate\Database\Seeder;

class FireIncidentSeeder extends Seeder
{
    public function run(): void
    {
        $incidents = [

            [
                'incident_number' => 'FI-2026-0001',
                'barangay' => 'Addition Hills',
                'incident_type' => 'Residential Fire',
                'location' => 'S. Laurel Street',
                'severity' => 'Moderate',
                'status' => 'Resolved',
                'reported_at' => now()->subDays(15),
                'responded_at' => now()->subDays(15)->addMinutes(8),
                'resolved_at' => now()->subDays(15)->addHour(),
                'remarks' => 'Sample historical incident.',
            ],

            [
                'incident_number' => 'FI-2026-0002',
                'barangay' => 'Highway Hills',
                'incident_type' => 'Electrical Fire',
                'location' => 'Shaw Boulevard',
                'severity' => 'Minor',
                'status' => 'Resolved',
                'reported_at' => now()->subDays(8),
                'responded_at' => now()->subDays(8)->addMinutes(6),
                'resolved_at' => now()->subDays(8)->addMinutes(40),
                'remarks' => 'Sample historical incident.',
            ],

            [
                'incident_number' => 'FI-2026-0003',
                'barangay' => 'Plainview',
                'incident_type' => 'Commercial Fire',
                'location' => 'Mabini Street',
                'severity' => 'Major',
                'status' => 'Responding',
                'reported_at' => now()->subHour(),
                'responded_at' => now()->subMinutes(45),
                'resolved_at' => null,
                'remarks' => 'Sample active incident.',
            ],

        ];

        foreach ($incidents as $incident) {

            $barangay = Barangay::where(
                'name',
                $incident['barangay']
            )->first();

            if (!$barangay) {
                continue;
            }

            FireIncident::updateOrCreate(

                [
                    'incident_number' => $incident['incident_number'],
                ],

                [
                    'barangay_id' => $barangay->id,
                    'incident_type' => $incident['incident_type'],
                    'location' => $incident['location'],
                    'latitude' => null,
                    'longitude' => null,
                    'severity' => $incident['severity'],
                    'status' => $incident['status'],
                    'reported_at' => $incident['reported_at'],
                    'responded_at' => $incident['responded_at'],
                    'resolved_at' => $incident['resolved_at'],
                    'remarks' => $incident['remarks'],
                ]
            );
        }
    }
}