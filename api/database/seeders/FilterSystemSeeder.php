<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Filter;
use App\Models\FilterGroup;
use Illuminate\Database\Seeder;

class FilterSystemSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'company' => FilterGroup::firstOrCreate(
                ['name' => 'Company'],
                [
                    'description' => 'Company related filters',
                    'sort_order' => 1,
                ]
            ),
            'role' => FilterGroup::firstOrCreate(
                ['name' => 'Role'],
                [
                    'description' => 'Role and position related filters',
                    'sort_order' => 2,
                ]
            ),
            'demographhics' => FilterGroup::firstOrCreate(
                ['name' => 'Demographics'],
                [
                    'description' => 'Location and demographic filters',
                    'sort_order' => 3,
                ]
            ),
            'personal' => FilterGroup::firstOrCreate(
                ['name' => 'Personal'],
                [
                    'description' => 'Personal information filters',
                    'sort_order' => 3,
                ]
            ),
        ];

        $this->createCompanyGroupFilters($groups['company']);
        $this->createRoleGroupFilters($groups['role']);
        $this->createDemographicsGroupFilters($groups['demographhics']);
        $this->createPersonalGroupFilters($groups['personal']);

        $this->call(FilterValuesSeeder::class);

    }

    private function createCompanyGroupFilters(FilterGroup $group): void
    {
        // Company Name (Company)
        Filter::updateOrCreate(
            ['filter_id' => 'company_name_company'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'company',
                'name' => 'Company Name',
                'elasticsearch_field' => 'company',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Company::class,
                    'field_type' => 'text',
                    'search_fields' => ['company', 'company_also_known_as', 'company.keyword'],
                ],
                'sort_order' => 1,
            ]
        );

        // Company Name (Contact)
        Filter::updateOrCreate(
            ['filter_id' => 'company_name_contact'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Company Name',
                'elasticsearch_field' => 'company',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Contact::class,
                    'field_type' => 'text',
                    'search_fields' => ['company', 'company_also_known_as'],
                ],
                'sort_order' => 1,
            ]
        );

        // Industry
        Filter::updateOrCreate(
            ['filter_id' => 'industry'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'company',
                'name' => 'Industry',
                'elasticsearch_field' => 'industry',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Company::class,
                    'field_type' => 'keyword',
                    'search_fields' => ['industry'],
                ],
                'sort_order' => 3,
            ]
        );

        // Company Size / Employee (Company)
        Filter::updateOrCreate(
            ['filter_id' => 'company_headcount'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'company',
                'name' => 'Company Size / Employee',
                'elasticsearch_field' => 'employees',
                'value_source' => 'predefined',
                'value_type' => 'number',
                'input_type' => 'select',
                'is_searchable' => false,
                'allows_exclusion' => true,
                'sort_order' => 4,
            ]
        );

        // Company Size / Employee (Contact)
        Filter::updateOrCreate(
            ['filter_id' => 'company_headcount_contact'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Company Size / Employee',
                'elasticsearch_field' => 'employees',
                'value_source' => 'predefined',
                'value_type' => 'number',
                'input_type' => 'select',
                'is_searchable' => false,
                'allows_exclusion' => true,
                'sort_order' => 4,
            ]
        );

        // Company Domain (Company)
        Filter::updateOrCreate(
            ['filter_id' => 'company_domain_company'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'company',
                'name' => 'Company Domain',
                'elasticsearch_field' => 'website',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Company::class,
                    'field_type' => 'keyword',
                    'search_fields' => ['website'],
                ],
                'sort_order' => 2,
            ]
        );

        // Company Domain (Contact)
        Filter::updateOrCreate(
            ['filter_id' => 'company_domain_contact'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Company Domain',
                'elasticsearch_field' => 'website',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Contact::class,
                    'field_type' => 'keyword',
                    'search_fields' => ['website'],
                ],
                'sort_order' => 2,
            ]
        );

        // Technologies (Company)
        Filter::updateOrCreate(
            ['filter_id' => 'technologies'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'company',
                'name' => 'Technologies',
                'elasticsearch_field' => 'technologies',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Company::class,
                    'field_type' => 'keyword',
                    'search_fields' => ['technologies', 'company_technologies'],
                ],
                'sort_order' => 5,
            ]
        );
    }

    private function createRoleGroupFilters(FilterGroup $group): void
    {
        // Job Title
        Filter::updateOrCreate(
            ['filter_id' => 'job_title'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Job Title',
                'elasticsearch_field' => 'title',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Contact::class,
                    'field_type' => 'text',
                    'search_fields' => ['title', 'job_title', 'normalized_title', 'title_keywords'],
                ],
                'sort_order' => 1,
            ]
        );

        // Function/Department
        Filter::updateOrCreate(
            ['filter_id' => 'departments'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Department',
                'elasticsearch_field' => 'departments',
                'value_source' => 'elasticsearch',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Contact::class,
                    'field_type' => 'keyword',
                    'search_fields' => ['departments'],
                ],
                'sort_order' => 2,
            ]
        );

        // Seniority Level
        Filter::updateOrCreate(
            ['filter_id' => 'seniority'],
            [
                'is_active' => true,
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Seniority Level',
                'elasticsearch_field' => 'seniority_level',
                'value_source' => 'predefined',
                'value_type' => 'string',
                'input_type' => 'multi_select',
                'is_searchable' => false,
                'allows_exclusion' => true,
                'settings' => [
                    'field_type' => 'keyword',
                ],
                'sort_order' => 3,
            ]
        );

        // TODO: Future, No elastic field with this data
        // Filter::create([
        //     'filter_group_id' => $group->id,
        //     'filter_id' => 'years_of_experience',
        //     'filter_type' => 'contact',
        //     'name' => 'Years of Experience',
        //     'elasticsearch_field' => 'experience_years',
        //     'value_source' => 'predefined',
        //     'value_type' => 'number',
        //     'input_type' => 'select',
        //     'is_searchable' => false,
        //     'allows_exclusion' => true,
        //     'settings' => [
        //         'target_model' => Contact::class,
        //         'use_range' => true
        //     ],
        //     'sort_order' => 4
        // ]);
    }

    private function createDemographicsGroupFilters(FilterGroup $group): void
    {
        // Company Location
        Filter::updateOrCreate(
            ['filter_id' => 'company_location'],
            [
                'filter_group_id' => $group->id,
                'filter_type' => 'company',
                'name' => 'Company Location',
                'elasticsearch_field' => 'location',
                'value_source' => 'specialized',
                'value_type' => 'location',
                'input_type' => 'hierarchical',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Company::class,
                    'hierarchy_levels' => [
                        'country' => [
                            'field' => 'location.country',
                            'title' => 'Country',
                        ],
                        'state' => [
                            'field' => 'location.state',
                            'title' => 'State/Region',
                        ],
                        'city' => [
                            'field' => 'location.city',
                            'title' => 'City',
                        ],
                        'street' => [
                            'field' => 'location.street',
                            'title' => 'Street',
                        ],
                        'postal_code' => [
                            'field' => 'location.postal_code',
                            'title' => 'Postal Code',
                        ],
                    ],
                ],
                'sort_order' => 1,
            ]
        );

        // Contact Location
        Filter::updateOrCreate(
            ['filter_id' => 'contact_location'],
            [
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Contact Location',
                'elasticsearch_field' => 'location',
                'value_source' => 'specialized',
                'value_type' => 'location',
                'input_type' => 'hierarchical',
                'is_searchable' => true,
                'allows_exclusion' => true,
                'settings' => [
                    'target_model' => Contact::class,
                    'hierarchy_levels' => [
                        'country' => [
                            'field' => 'location.country',
                            'title' => 'Country',
                        ],
                        'state' => [
                            'field' => 'location.state',
                            'title' => 'State/Region',
                        ],
                        'city' => [
                            'field' => 'location.city',
                            'title' => 'City',
                        ],
                        'street' => [
                            'field' => 'location.street',
                            'title' => 'Street',
                        ],
                        'postal_code' => [
                            'field' => 'location.postal_code',
                            'title' => 'Postal Code',
                        ],
                    ],
                ],
                'sort_order' => 2,
            ]
        );
    }

    private function createPersonalGroupFilters(FilterGroup $group): void
    {
        // First Name
        Filter::updateOrCreate(
            ['filter_id' => 'first_name'],
            [
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'First Name',
                'elasticsearch_field' => 'first_name',
                'value_source' => 'direct',
                'value_type' => 'string',
                'input_type' => 'text',
                'is_searchable' => false,
                'allows_exclusion' => false,
                'settings' => [
                    'validation' => [
                        'min_length' => 2,
                        'max_length' => 50,
                        'pattern' => '/^[a-zA-Z\s\'-]+$/',
                    ],
                ],
                'sort_order' => 1,
            ]
        );

        // Last Name
        Filter::updateOrCreate(
            ['filter_id' => 'last_name'],
            [
                'filter_group_id' => $group->id,
                'filter_type' => 'contact',
                'name' => 'Last Name',
                'elasticsearch_field' => 'last_name',
                'value_source' => 'direct',
                'value_type' => 'string',
                'input_type' => 'text',
                'is_searchable' => false,
                'allows_exclusion' => false,
                'settings' => [
                    'validation' => [
                        'min_length' => 2,
                        'max_length' => 50,
                        'pattern' => '/^[a-zA-Z\s\'-]+$/',
                    ],
                ],
                'sort_order' => 2,
            ]
        );
    }
}
