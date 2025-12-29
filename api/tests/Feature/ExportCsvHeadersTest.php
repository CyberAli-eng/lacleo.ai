<?php

use App\Exports\ExportCsvBuilder;
use App\Services\RecordNormalizer;

function rowsFromCsv(string $csv): array
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

it('companies export preserves canonical headers and includes rows for companies with/without contacts', function () {
    $companies = [
        RecordNormalizer::normalizeCompany(['name' => 'Acme Corp', 'website' => 'acme.com', 'industry' => 'Software', 'country' => 'US']),
        RecordNormalizer::normalizeCompany(['name' => 'Beta LLC', 'website' => 'beta.io', 'industry' => 'Finance', 'country' => 'DE']),
    ];
    $contacts = [
        RecordNormalizer::normalizeContact([
            'full_name' => 'Jane Doe',
            'title' => 'Engineer',
            'company' => 'Acme Corp',
            'website' => 'acme.com',
            'emails' => ['jane@acme.com'],
            'phone_numbers' => ['+1 555 111 2222'],
            'departments' => ['Engineering'],
        ]),
    ];

    $csv = ExportCsvBuilder::buildCompaniesCsv($companies, $contacts, false);
    $rows = rowsFromCsv($csv);

    // Company-only headers per builder
    expect($rows[0][0])->toBe('domain');
    expect($rows[0][1])->toBe('name');
    expect($rows[0][2])->toBe('website');
    $body = array_slice($rows, 1);
    expect(count($body))->toBe(2);
    $acmeRow = collect($body)->first(fn ($r) => $r[1] === 'Acme Corp');
    $betaRow = collect($body)->first(fn ($r) => $r[1] === 'Beta LLC');
    expect($acmeRow[1])->toBe('Acme Corp');
    expect($betaRow[1])->toBe('Beta LLC');
});
