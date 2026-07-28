<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        $barangays = [
            [
                'name' => 'Addition Hills',
                'district' => 1,
            ],
            [
                'name' => 'Bagong Silang',
                'district' => 1,
            ],
            [
                'name' => 'Barangka Drive',
                'district' => 2,
            ],
            [
                'name' => 'Barangka Ibaba',
                'district' => 2,
            ],
            [
                'name' => 'Barangka Ilaya',
                'district' => 2,
            ],
            [
                'name' => 'Barangka Itaas',
                'district' => 2,
            ],
            [
                'name' => 'Buayang Bato',
                'district' => 2,
            ],
            [
                'name' => 'Burol',
                'district' => 1,
            ],
            [
                'name' => 'Daang Bakal',
                'district' => 1,
            ],
            [
                'name' => 'Hagdang Bato Itaas',
                'district' => 1,
            ],
            [
                'name' => 'Hagdang Bato Libis',
                'district' => 1,
            ],
            [
                'name' => 'Harapin Ang Bukas',
                'district' => 1,
            ],
            [
                'name' => 'Highway Hills',
                'district' => 1,
            ],
            [
                'name' => 'Hulo',
                'district' => 2,
            ],
            [
                'name' => 'Mabini-J. Rizal',
                'district' => 2,
            ],
            [
                'name' => 'Malamig',
                'district' => 2,
            ],
            [
                'name' => 'Mauway',
                'district' => 1,
            ],
            [
                'name' => 'Namayan',
                'district' => 2,
            ],
            [
                'name' => 'New Zaniga',
                'district' => 1,
            ],
            [
                'name' => 'Old Zaniga',
                'district' => 2,
            ],
            [
                'name' => 'Pag-Asa',
                'district' => 1,
            ],
            [
                'name' => 'Plainview',
                'district' => 2,
            ],
            [
                'name' => 'Pleasant Hills',
                'district' => 1,
            ],
            [
                'name' => 'Poblacion',
                'district' => 1,
            ],
            [
                'name' => 'San Jose',
                'district' => 2,
            ],
            [
                'name' => 'Vergara',
                'district' => 2,
            ],
            [
                'name' => 'Wack-Wack Greenhills East',
                'district' => 1,
            ],
        ];

        foreach ($barangays as $barangay) {
            Barangay::updateOrCreate(
                ['name' => $barangay['name']],
                [
                    'district' => $barangay['district'],
                    'is_active' => true,
                ]
            );
        }
    }
}