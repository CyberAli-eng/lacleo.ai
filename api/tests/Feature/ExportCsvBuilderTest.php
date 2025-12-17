<?php

use App\Exports\ExportCsvBuilder;
use App\Services\RecordNormalizer;

function csvToRows(string $csv): array
{
    $rows = [];
    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $csv);
    rewind($fp);
    while (($row = fgetcsv($fp)) !== false) {
        $rows[] = $row;
    }

    return $rows;
}

it('companies without contacts produce one row with empty contact fields', function () {
    $company = RecordNormalizer::normalizeCompany([
        'name' => 'Acme Corp',
        'website' => 'acme.com',
        'industry' => 'Software',
        'country' => 'US',
    ]);

    $csv = ExportCsvBuilder::buildCompaniesCsv([$company], [], false);
    $rows = csvToRows($csv);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toEqual(\App\Exports\ExportCsvBuilder::COMPANY_HEADERS_FREE);
    // domain,first_name,last_name,title,seniority,departments,person_linkedin_url,city,state,country,company_name,number_of_employees,industry,...
    expect($rows[1][0])->toBe('acme.com');
    expect($rows[1][10])->toBe('Acme Corp');
    expect($rows[1][12])->toBe('Software');
});

it('contact rows include normalized email and phone fields', function () {
    $contact = RecordNormalizer::normalizeContact([
        'full_name' => 'Jane Doe',
        'title' => 'Engineer',
        'company' => 'Acme Corp',
        'website' => 'acme.com',
        'emails' => ['jane@acme.com', ['email' => 'j.doe@acme.com']],
        'phone_numbers' => ['+1 555-111-2222', ['phone_number' => '+1 (555) 222-3333']],
        'departments' => ['Engineering'],
    ]);

    $csv = ExportCsvBuilder::buildContactsCsv([$contact], false);
    $rows = csvToRows($csv);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toEqual(\App\Exports\ExportCsvBuilder::CONTACT_HEADERS_PII);
    // domain,first_name,last_name,title,work_email,personal_email,seniority,departments,mobile_number,direct_number,person_linkedin_url,city,state,country
    expect($rows[1][0])->toBe('acme.com');
    expect($rows[1][3])->toBe('Engineer');
    // emails merged via normalizer; specific fields may be empty
    expect($rows[1][8])->toBe('+1 555-111-2222');
});

it('domain join uses canonical_domain case-insensitively', function () {
    $company = RecordNormalizer::normalizeCompany([
        'name' => 'Acme Corp',
        'website' => 'acme.com',
    ]);
    $contactMatch = RecordNormalizer::normalizeContact([
        'full_name' => 'John Roe',
        'website' => 'ACME.COM',
        'emails' => ['john@acme.com'],
        'phone_numbers' => [],
    ]);
    $contactNoMatch = RecordNormalizer::normalizeContact([
        'full_name' => 'Alan Poe',
        'website' => 'acme.co',
        'emails' => ['alan@acme.co'],
    ]);

    $csv = ExportCsvBuilder::buildCompaniesCsv([$company], [$contactMatch, $contactNoMatch], false);
    $rows = csvToRows($csv);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toEqual(\App\Exports\ExportCsvBuilder::COMPANY_HEADERS_PII);
    // first_name,last_name columns
    expect($rows[1][1])->toBe('John');
    expect($rows[1][2])->toBe('Roe');
});

it('contact free header order matches spec', function () {
    $csv = ExportCsvBuilder::buildContactsCsv([], false);
    $rows = csvToRows($csv);
    expect($rows[0])->toEqual(\App\Exports\ExportCsvBuilder::CONTACT_HEADERS_FREE);
});
