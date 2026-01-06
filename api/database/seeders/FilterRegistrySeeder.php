<?php

namespace Database\Seeders;

use App\Models\FilterRegistry;
use App\Services\FilterRegistry as HardcodedRegistry;
use Illuminate\Database\Seeder;

class FilterRegistrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filters = HardcodedRegistry::getFilters();

        foreach ($filters as $filter) {
            FilterRegistry::updateOrCreate(
                ['id' => $filter['id']],
                [
                    'label' => $filter['label'],
                    'group_name' => $filter['group'],
                    'applies_to' => $filter['applies_to'],
                    'type' => $filter['type'],
                    'input_type' => $filter['input'],
                    'data_source' => $filter['data_source'],
                    'fields' => $filter['fields'],
                    'search_config' => $filter['search'] ?? null,
                    'filtering_config' => $filter['filtering'] ?? null,
                    'aggregation_config' => $filter['aggregation'] ?? null,
                    'preloaded_values' => $filter['preloaded_values'] ?? null,
                    'range_config' => $filter['range'] ?? null,
                    'hint' => $filter['hint'] ?? null,
                    'sort_order' => $filter['sort_order'] ?? 0,
                    'is_active' => $filter['active'] ?? true,
                ]
            );
        }
    }
}
