<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;
use RuntimeException;
use SplFileObject;

class BarangayHistoricalFloodCountSeeder extends Seeder
{
    /**
     * Maps dataset barangay names to the exact names currently stored
     * in the barangays table.
     */
    private const BARANGAY_ALIASES = [
        'New Zaniga' => 'New Zaniga',
        'New Zañiga' => 'New Zaniga',

        'Old Zaniga' => 'Old Zaniga',
        'Old Zañiga' => 'Old Zaniga',

        'Pag Asa' => 'Pag-Asa',
        'Pag-Asa' => 'Pag-Asa',
        'Pagasa' => 'Pag-Asa',

        'Mabini J. Rizal' => 'Mabini-J. Rizal',
        'Mabini J Rizal' => 'Mabini-J. Rizal',
        'Mabini-J Rizal' => 'Mabini-J. Rizal',
        'Mabini-J. Rizal' => 'Mabini-J. Rizal',

        'Harapin ang Bukas' => 'Harapin Ang Bukas',
        'Harapin Ang Bukas' => 'Harapin Ang Bukas',

        'Wack-Wack Greenhills' => 'Wack-Wack Greenhills East',
        'Wack Wack Greenhills' => 'Wack-Wack Greenhills East',
        'Wack-Wack Greenhills East' => 'Wack-Wack Greenhills East',

        'Hagdan Bato Itaas' => 'Hagdang Bato Itaas',
        'Hagdang Bato Itaas' => 'Hagdang Bato Itaas',

        'Hagdan Bato Libis' => 'Hagdang Bato Libis',
        'Hagdang Bato Libis' => 'Hagdang Bato Libis',

        'Hagdan Bato Ibaba' => 'Hagdang Bato Libis',
        'Hagdang Bato Ibaba' => 'Hagdang Bato Libis',
    ];

    public function run(): void
    {
        $csvPath = storage_path('app/datasets/DataFlood.csv');

        if (! file_exists($csvPath)) {
            throw new RuntimeException(
                "Dataset was not found at: {$csvPath}"
            );
        }

        $file = new SplFileObject($csvPath);

        $file->setFlags(
            SplFileObject::READ_CSV
            | SplFileObject::SKIP_EMPTY
            | SplFileObject::DROP_NEW_LINE
        );

        $header = null;
        $barangayIndex = null;
        $dateIndex = null;

        $records = [];
        $latestDate = null;

        foreach ($file as $row) {
            if (
                ! is_array($row)
                || $row === [null]
                || count($row) === 0
            ) {
                continue;
            }

            if ($header === null) {
                $header = array_map(
                    static function ($column): string {
                        return trim(
                            preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                (string) $column
                            )
                        );
                    },
                    $row
                );

                $barangayIndex = array_search(
                    'barangay',
                    $header,
                    true
                );

                $dateIndex = array_search(
                    'date',
                    $header,
                    true
                );

                if ($barangayIndex === false) {
                    throw new RuntimeException(
                        'The CSV does not contain a barangay column.'
                    );
                }

                if ($dateIndex === false) {
                    throw new RuntimeException(
                        'The CSV does not contain a date column.'
                    );
                }

                continue;
            }

            if (
                ! array_key_exists($barangayIndex, $row)
                || ! array_key_exists($dateIndex, $row)
            ) {
                continue;
            }

            $barangay = $this->normalizeBarangayName(
                (string) $row[$barangayIndex]
            );

            $dateValue = trim(
                (string) $row[$dateIndex]
            );

            if ($barangay === '' || $dateValue === '') {
                continue;
            }

            $timestamp = strtotime($dateValue);

            if ($timestamp === false) {
                continue;
            }

            if (
                $latestDate === null
                || $timestamp > $latestDate
            ) {
                $latestDate = $timestamp;
            }

            $records[] = [
                'barangay' => $barangay,
                'timestamp' => $timestamp,
            ];
        }

        if ($latestDate === null) {
            throw new RuntimeException(
                'No valid dates were found in the dataset.'
            );
        }

        $fiveYearsAgo = strtotime(
            '-5 years',
            $latestDate
        );

        if ($fiveYearsAgo === false) {
            throw new RuntimeException(
                'Unable to calculate the five-year counting period.'
            );
        }

        $fiveYearCounts = [];

        foreach ($records as $record) {
            if ($record['timestamp'] < $fiveYearsAgo) {
                continue;
            }

            $barangay = $record['barangay'];

            $fiveYearCounts[$barangay] =
                ($fiveYearCounts[$barangay] ?? 0) + 1;
        }

        /*
         * Reset every barangay first so barangays with no matching
         * incidents receive a valid value of zero instead of NULL.
         */
        Barangay::query()->update([
            'historical_flood_count_5y' => 0,
        ]);

        $updated = 0;
        $unmatched = [];

        foreach ($fiveYearCounts as $barangayName => $count) {
            $barangay = Barangay::query()
                ->whereRaw(
                    'LOWER(TRIM(name)) = ?',
                    [mb_strtolower(trim($barangayName))]
                )
                ->first();

            if (! $barangay) {
                $unmatched[$barangayName] = $count;

                continue;
            }

            $barangay->update([
                'historical_flood_count_5y' => $count,
            ]);

            $updated++;
        }

        $this->command?->info(
            "Updated {$updated} barangays."
        );

        $this->command?->info(
            'Dataset latest date: ' .
            date('Y-m-d', $latestDate)
        );

        $this->command?->info(
            'Five-year counting period begins: ' .
            date('Y-m-d', $fiveYearsAgo)
        );

        if ($unmatched !== []) {
            $this->command?->warn(
                'Unmatched dataset barangays:'
            );

            foreach ($unmatched as $name => $count) {
                $this->command?->warn(
                    "- {$name}: {$count} records"
                );
            }
        } else {
            $this->command?->info(
                'All dataset barangay names matched the database.'
            );
        }
    }

    private function normalizeBarangayName(
        string $name
    ): string {
        $normalized = trim(
            preg_replace(
                '/\s+/',
                ' ',
                $name
            )
        );

        return self::BARANGAY_ALIASES[$normalized]
            ?? $normalized;
    }
}