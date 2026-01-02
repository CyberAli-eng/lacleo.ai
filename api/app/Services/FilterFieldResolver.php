<?php

namespace App\Services;

class FilterFieldResolver
{
    /**
     * Maps frontend filter IDs to canonical Elasticsearch field paths.
     */
    protected static array $mapping = [
        // --- Company Filters ---
        'company_name' => 'company',
        'company_domain' => 'website',
        'business_category' => 'business_category',
        'industry' => 'business_category',
        'sic_code' => 'keywords',
        'keywords' => 'keywords',
        'technologies' => 'keywords',
        'company_technologies' => 'keywords',
        'employee_count' => 'employee_count',
        'annual_revenue' => 'annual_revenue',
        'founded_year' => 'founded_year',

        // --- Company Location ---
        'company_country' => 'location.country',
        'company_state' => 'location.state',
        'company_city' => 'location.city',

        // --- Company Existence ---
        'company_phone_exists' => 'company_phone',
        'company_linkedin_exists' => 'company_linkedin_url',

        // --- Contact Filters ---
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'job_title' => 'title',
        'seniority' => 'seniority',
        'department' => 'departments',

        // --- Contact Location ---
        'contact_country' => 'location.country',
        'contact_state' => 'location.state',
        'contact_city' => 'location.city',

        // --- Contact Existence ---
        'work_email_exists' => 'emails.email',
        'personal_email_exists' => 'emails.email',
        'mobile_number_exists' => 'phone_numbers.phone_number',
        'direct_number_exists' => 'phone_numbers.phone_number',
        'contact_linkedin_exists' => 'linkedin_url',
    ];

    public static function resolve(string $filterId, ?string $default = null): ?string
    {
        return self::$mapping[$filterId] ?? $default ?? $filterId;
    }

    public static function resolveForContact(string $filterId): string
    {
        $field = self::resolve($filterId);

        // Fields that require bridging to company index or website mapping
        $needsBridge = [
            'business_category',
            'keywords',
            'employee_count',
            'annual_revenue',
            'founded_year',
            'company_phone',
            'company_country',
            'company_state',
            'company_city'
        ];

        if (in_array($filterId, $needsBridge) || in_array($field, ['keywords'])) {
            return "__BRIDGE__:{$filterId}";
        }

        return $field;
    }
}
