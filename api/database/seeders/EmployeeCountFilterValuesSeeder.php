<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Filter;
use App\Models\FilterValue;

class EmployeeCountFilterValuesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the employee count brackets
        $brackets = [
            '1-10',
            '11-50',
            '51-200',
            '201-500',
            '501-1000',
            '1001-5000',
            '5001-10000',
            '10000+',
        ];

        // Get all employee count related filters
        $filterIds = ['company_headcount', 'company_headcount_contact', 'employee_count_contact'];

        foreach ($filterIds as $filterId) {
            $filter = Filter::where('filter_id', $filterId)->first();
            
            if (!$filter) {
                continue;
            }

            // Delete existing values for this filter
            FilterValue::where('filter_id', $filter->id)->delete();

            // Create new values
            foreach ($brackets as $order => $bracket) {
                FilterValue::create([
                    'filter_id' => $filter->id,
                    'value_id' => $bracket,
                    'display_value' => $bracket,
                    'order' => $order + 1,
                    'is_active' => true,
                ]);
            }

            $this->command->info("Employee count filter values created for {$filterId}!");
        }
    }
}
