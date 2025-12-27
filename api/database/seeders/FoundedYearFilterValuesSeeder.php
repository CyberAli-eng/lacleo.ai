<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterValue;
use Illuminate\Database\Seeder;

class FoundedYearFilterValuesSeeder extends Seeder
{
    public function run(): void
    {
        $filters = Filter::whereIn('filter_id', ['founded_year', 'founded_year_contact'])->get();

        if ($filters->isEmpty()) {
            return;
        }

        $currentYear = date('Y');
        $ranges = [
            [
                'id' => 'before-1950',
                'name' => 'Before 1950',
                'metadata' => ['range' => ['min' => null, 'max' => 1949]],
            ],
            [
                'id' => '1950-1975',
                'name' => '1950 - 1975',
                'metadata' => ['range' => ['min' => 1950, 'max' => 1975]],
            ],
            [
                'id' => '1976-1990',
                'name' => '1976 - 1990',
                'metadata' => ['range' => ['min' => 1976, 'max' => 1990]],
            ],
            [
                'id' => '1991-2000',
                'name' => '1991 - 2000',
                'metadata' => ['range' => ['min' => 1991, 'max' => 2000]],
            ],
            [
                'id' => '2001-2010',
                'name' => '2001 - 2010',
                'metadata' => ['range' => ['min' => 2001, 'max' => 2010]],
            ],
            [
                'id' => '2011-2015',
                'name' => '2011 - 2015',
                'metadata' => ['range' => ['min' => 2011, 'max' => 2015]],
            ],
            [
                'id' => '2016-2020',
                'name' => '2016 - 2020',
                'metadata' => ['range' => ['min' => 2016, 'max' => 2020]],
            ],
            [
                'id' => '2021-present',
                'name' => "2021 - {$currentYear}",
                'metadata' => ['range' => ['min' => 2021, 'max' => null]],
            ],
        ];

        foreach ($filters as $filter) {
            foreach ($ranges as $idx => $range) {
                FilterValue::updateOrCreate(
                    [
                        'value_id' => $range['id'],
                        'filter_id' => $filter->id,
                    ],
                    [
                        'display_value' => $range['name'],
                        'metadata' => $range['metadata'],
                        'is_active' => true,
                        'order' => $idx + 1,
                    ]
                );
            }
        }

        $this->command->info('Founded Year filter values seeded successfully.');
    }
}
