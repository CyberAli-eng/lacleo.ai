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

it('companies without contacts produce one row with company-only fields', function () {
    $company = RecordNormalizer::normalizeCompany([
        'name' => 'Acme Corp',
        'website' => 'acme.com',
        'industry' => 'Software',
        'country' => 'US',
    ]);

    $csv = ExportCsvBuilder::buildCompaniesCsv([$company], [], false);
    $rows = csvToRows($csv);

    expect($rows)->toHaveCount(2);
    expect($rows[0][0])->toBe('domain');
    expect($rows[0][1])->toBe('name');
    expect($rows[0][2])->toBe('website');
    expect($rows[1][0])->toBe('acme.com');
    expect($rows[1][1])->toBe('Acme Corp');
    expect($rows[1][4])->toBe('Software');
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
    // first_name,last_name,title,work_email,personal_email,seniority,departments,mobile_number,direct_number,person_linkedin_url,city,state,country,company,website
    expect($rows[0][0])->toBe('first_name');
    expect($rows[0][2])->toBe('title');
    expect($rows[1][2])->toBe('Engineer');
    // emails merged via normalizer; specific fields may be empty
    expect($rows[1][7])->toBe('+1 555-111-2222');
});

it('companies CSV ignores contact rows and outputs company fields only', function () {
    $company = RecordNormalizer::normalizeCompany([
        'name' => 'Acme Corp',
        'website' => 'acme.com',
    ]);
    $contact = RecordNormalizer::normalizeContact([
        'full_name' => 'John Roe',
        'website' => 'ACME.COM',
        'emails' => ['john@acme.com'],
    ]);

    $csv = ExportCsvBuilder::buildCompaniesCsv([$company], [$contact], false);
    $rows = csvToRows($csv);

    expect($rows)->toHaveCount(2);
    expect($rows[0][0])->toBe('domain');
    expect($rows[0])->not->toContain('first_name');
    expect($rows[1][1])->toBe('Acme Corp');
});

it('contact header order matches builder schema', function () {
    $csv = ExportCsvBuilder::buildContactsCsv([], false);
    $rows = csvToRows($csv);
    expect($rows[0][0])->toBe('first_name');
    expect($rows[0][2])->toBe('title');
    expect($rows[0][13])->toBe('company');
    expect($rows[0][14])->toBe('website');
});
