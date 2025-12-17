<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Filter;
use App\Models\FilterValue;
use Illuminate\Database\Seeder;

class FilterValuesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCompanyHeadcountRanges();
        // TODO: As data comes in
        // $this->seedCompanyTypes();
        $this->seedSeniorityLevels();
        // $this->seedProfileLanguages();
        // $this->seedYearsOfExperience();
    }

    private function seedCompanyHeadcountRanges(): void
    {
        $filters = Filter::whereIn('filter_id', ['company_headcount', 'company_headcount_contact'])->get();

        if ($filters->isEmpty()) {
            return;
        }

        $ranges = [
            [
                'id' => '1-10',
                'name' => '1-10',
                'metadata' => ['range' => ['min' => 1, 'max' => 10]],
            ],
            [
                'id' => '11-50',
                'name' => '11-50',
                'metadata' => ['range' => ['min' => 11, 'max' => 50]],
            ],
            [
                'id' => '51-200',
                'name' => '51-200',
                'metadata' => ['range' => ['min' => 51, 'max' => 200]],
            ],
            [
                'id' => '201-500',
                'name' => '201-500',
                'metadata' => ['range' => ['min' => 201, 'max' => 500]],
            ],
            [
                'id' => '501-1000',
                'name' => '501-1,000',
                'metadata' => ['range' => ['min' => 501, 'max' => 1000]],
            ],
            [
                'id' => '1001-5000',
                'name' => '1,001-5,000',
                'metadata' => ['range' => ['min' => 1001, 'max' => 5000]],
            ],
            [
                'id' => '5001-10000',
                'name' => '5,001-10,000',
                'metadata' => ['range' => ['min' => 5001, 'max' => 10000]],
            ],
            [
                'id' => '10001+',
                'name' => '10,001+',
                'metadata' => ['range' => ['min' => 10001, 'max' => null]],
            ],
        ];

        foreach ($filters as $filter) {
            foreach ($ranges as $idx => $range) {
                FilterValue::updateOrCreate(
                    [
                        'value_id' => $range['id'],
                        'filter_id' => $filter->id, // Use the specific filter ID
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
    }

    // private function seedCompanyTypes(): void
    // {
    //     $filter = Filter::where('filter_id', 'company_type')->first();

    //     if (!$filter) return;

    //     $types = [
    //         ['id' => 'public', 'name' => 'Public Company'],
    //         ['id' => 'private', 'name' => 'Private Company'],
    //         ['id' => 'nonprofit', 'name' => 'Nonprofit'],
    //         ['id' => 'government', 'name' => 'Government Agency'],
    //         ['id' => 'educational', 'name' => 'Educational Institution']
    //     ];

    //     foreach ($types as $type) {
    //         FilterValue::create([
    //             'filter_id' => $filter->id,
    //             'value_id' => $type['id'],
    //             'display_value' => $type['name']
    //         ]);
    //     }
    // }

    private function seedSeniorityLevels(): void
    {
        $filter = Filter::where('filter_id', 'seniority')->first();

        if (!$filter) {
            return;
        }

        $levels = [
            ['id' => 'entry', 'name' => 'Entry Level'],
            ['id' => 'mid', 'name' => 'Mid-Senior Level'],
            ['id' => 'senior', 'name' => 'Senior Level'],
            ['id' => 'director', 'name' => 'Director'],
            ['id' => 'vp', 'name' => 'VP/Executive'],
        ];

        foreach ($levels as $idx => $level) {
            FilterValue::updateOrCreate(
                [
                    'value_id' => $level['id'],
                ],
                [
                    'filter_id' => $filter->id,
                    'display_value' => $level['name'],
                    'is_active' => true,
                    'order' => $idx + 1,
                ]
            );
        }
    }

    // private function seedProfileLanguages(): void
    // {
    //     $filter = Filter::where('filter_id', 'profile_language')->first();

    //     if (!$filter) return;

    //     $languages = [
    //         ['id' => 'en', 'name' => 'English'],
    //         ['id' => 'es', 'name' => 'Spanish'],
    //         ['id' => 'fr', 'name' => 'French'],
    //         ['id' => 'de', 'name' => 'German'],
    //         ['id' => 'zh', 'name' => 'Chinese']
    //     ];

    //     foreach ($languages as $language) {
    //         FilterValue::create([
    //             'filter_id' => $filter->id,
    //             'value_id' => $language['id'],
    //             'display_value' => $language['name']
    //         ]);
    //     }
    // }

    // private function seedYearsOfExperience(): void
    // {
    //     $filter = Filter::where('filter_id', 'years_of_experience')->first();

    //     if (!$filter) return;

    //     $ranges = [
    //         [
    //             'id' => '0-2',
    //             'name' => '0-2 years',
    //             'metadata' => ['range' => ['min' => 0, 'max' => 2]]
    //         ],
    //         [
    //             'id' => '3-5',
    //             'name' => '3-5 years',
    //             'metadata' => ['range' => ['min' => 3, 'max' => 5]]
    //         ],
    //         [
    //             'id' => '6-10',
    //             'name' => '6-10 years',
    //             'metadata' => ['range' => ['min' => 6, 'max' => 10]]
    //         ],
    //         [
    //             'id' => '11-15',
    //             'name' => '11-15 years',
    //             'metadata' => ['range' => ['min' => 11, 'max' => 15]]
    //         ],
    //         [
    //             'id' => '15+',
    //             'name' => '15+ years',
    //             'metadata' => ['range' => ['min' => 15, 'max' => null]]
    //         ]
    //     ];

    //     foreach ($ranges as $range) {
    //         FilterValue::create([
    //             'filter_id' => $filter->id,
    //             'value_id' => $range['id'],
    //             'display_value' => $range['name'],
    //             'metadata' => $range['metadata']
    //         ]);
    //     }
    // }
}
