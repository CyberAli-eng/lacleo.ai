<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterValue;
use Illuminate\Database\Seeder;

class AnnualRevenueFilterValuesSeeder extends Seeder
{
    public function run(): void
    {
        $filters = Filter::whereIn('filter_id', ['annual_revenue', 'annual_revenue_contact'])->get();

        if ($filters->isEmpty()) {
            return;
        }

        $ranges = [
            [
                'id' => '0-1M',
                'name' => '$0 - $1M',
                'metadata' => ['range' => ['min' => 0, 'max' => 1000000]],
            ],
            [
                'id' => '1M-10M',
                'name' => '$1M - $10M',
                'metadata' => ['range' => ['min' => 1000000, 'max' => 10000000]],
            ],
            [
                'id' => '10M-50M',
                'name' => '$10M - $50M',
                'metadata' => ['range' => ['min' => 10000000, 'max' => 50000000]],
            ],
            [
                'id' => '50M-100M',
                'name' => '$50M - $100M',
                'metadata' => ['range' => ['min' => 50000000, 'max' => 100000000]],
            ],
            [
                'id' => '100M-500M',
                'name' => '$100M - $500M',
                'metadata' => ['range' => ['min' => 100000000, 'max' => 500000000]],
            ],
            [
                'id' => '500M-1B',
                'name' => '$500M - $1B',
                'metadata' => ['range' => ['min' => 500000000, 'max' => 1000000000]],
            ],
            [
                'id' => '1B+',
                'name' => '$1B+',
                'metadata' => ['range' => ['min' => 1000000000, 'max' => null]],
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

        $this->command->info('Annual Revenue filter values seeded successfully.');
    }
}
