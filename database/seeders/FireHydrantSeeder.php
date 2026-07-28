<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\FireHydrant;
use Illuminate\Database\Seeder;

class FireHydrantSeeder extends Seeder
{
    public function run(): void
    {
        $hydrants = [

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-001',
                'location' => 'S. Laurel St. cor. Mc Collough St.',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-002',
                'location' => 'A. Mabini St. cor. Shaw Blvd.',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-003',
                'location' => '411 Shaw Blvd.',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-004',
                'location' => 'Fabella-Martinez St.',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-005',
                'location' => 'Addition Hills Barangay Hall',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-006',
                'location' => 'Correctional Road',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-007',
                'location' => 'Martinez Public Market',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-008',
                'location' => 'Addition Hills Elementary School',
            ],

            [
                'barangay' => 'Addition Hills',
                'hydrant_code' => 'AH-009',
                'location' => 'DOH Acacia Lane',
            ],

        ];

        foreach ($hydrants as $hydrant) {

            $barangay = Barangay::where(
                'name',
                $hydrant['barangay']
            )->first();

            if (!$barangay) {
                continue;
            }

            FireHydrant::updateOrCreate(

                [
                    'hydrant_code' => $hydrant['hydrant_code'],
                ],

                [
                    'barangay_id' => $barangay->id,
                    'location' => $hydrant['location'],
                    'latitude' => null,
                    'longitude' => null,
                    'status' => 'Active',
                    'remarks' => null,
                ]
            );
        }
    }
}