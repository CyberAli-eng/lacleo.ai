<?php

namespace App\Services;

class SearchTermNormalizer
{
    protected static array $synonyms = [
        'usa' => 'United States',
        'us' => 'United States',
        'u.s.' => 'United States',
        'united states of america' => 'United States',
        'uk' => 'United Kingdom',
        'u.k.' => 'United Kingdom',
        'uae' => 'United Arab Emirates',
        'u.a.e.' => 'United Arab Emirates',
    ];

    protected static array $regions = [
        'europe' => [
            'Albania',
            'Andorra',
            'Austria',
            'Belarus',
            'Belgium',
            'Bosnia and Herzegovina',
            'Bulgaria',
            'Croatia',
            'Cyprus',
            'Czech Republic',
            'Denmark',
            'Estonia',
            'Finland',
            'France',
            'Germany',
            'Greece',
            'Hungary',
            'Iceland',
            'Ireland',
            'Italy',
            'Kosovo',
            'Latvia',
            'Liechtenstein',
            'Lithuania',
            'Luxembourg',
            'Malta',
            'Moldova',
            'Monaco',
            'Montenegro',
            'Netherlands',
            'North Macedonia',
            'Norway',
            'Poland',
            'Portugal',
            'Romania',
            'Russia',
            'San Marino',
            'Serbia',
            'Slovakia',
            'Slovenia',
            'Spain',
            'Sweden',
            'Switzerland',
            'Ukraine',
            'United Kingdom',
            'Vatican City'
        ],
        'asia' => [
            'Afghanistan',
            'Armenia',
            'Azerbaijan',
            'Bahrain',
            'Bangladesh',
            'Bhutan',
            'Brunei',
            'Cambodia',
            'China',
            'Cyprus',
            'Georgia',
            'India',
            'Indonesia',
            'Iran',
            'Iraq',
            'Israel',
            'Japan',
            'Jordan',
            'Kazakhstan',
            'Kuwait',
            'Kyrgyzstan',
            'Laos',
            'Lebanon',
            'Malaysia',
            'Maldives',
            'Mongolia',
            'Myanmar',
            'Nepal',
            'North Korea',
            'Oman',
            'Pakistan',
            'Palestine',
            'Philippines',
            'Qatar',
            'Russia',
            'Saudi Arabia',
            'Singapore',
            'South Korea',
            'Sri Lanka',
            'Syria',
            'Taiwan',
            'Tajikistan',
            'Thailand',
            'Timor-Leste',
            'Turkey',
            'Turkmenistan',
            'United Arab Emirates',
            'Uzbekistan',
            'Vietnam',
            'Yemen'
        ],
        // Add more regions as needed
    ];

    public static function normalize(string $term): string
    {
        $lower = strtolower(trim($term));
        return self::$synonyms[$lower] ?? $term;
    }

    public static function expandRegion(string $term): ?array
    {
        $lower = strtolower(trim($term));
        return self::$regions[$lower] ?? null;
    }

    public static function getAllRegions(): array
    {
        return self::$regions;
    }
}
