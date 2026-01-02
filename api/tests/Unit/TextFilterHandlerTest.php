<?php

namespace Tests\Unit;

use App\Elasticsearch\ElasticQueryBuilder;
use App\Filters\Handlers\TextFilterHandler;
use App\Models\Company;
use App\Models\Filter;
use PHPUnit\Framework\TestCase;

class TextFilterHandlerTest extends TestCase
{
    public function testApplyWithCommaSeparatedValues()
    {
        $filter = new Filter([
            'filter_id' => 'technologies',
            'name' => 'Technologies',
            'group' => 'Technology',
            'type' => 'text',
            'settings' => [
                'fields' => [
                    'company' => ['company_technologies'],
                ],
                'target_model' => Company::class,
                'filtering' => ['mode' => 'match', 'split_on_comma' => true],
            ],
        ]);

        $handler = new TextFilterHandler($filter);
        $builder = new ElasticQueryBuilder(Company::class);

        // Test include
        $handler->apply($builder, ['include' => ['React', 'Vue'], 'operator' => 'and']);
        $query = $builder->toArray();

        // Verify query structure
        $this->assertArrayHasKey('query', $query);
        $bool = $query['query']['bool'] ?? [];
        $this->assertNotEmpty($bool['filter'] ?? $bool['must'] ?? []);
    }

    public function testApplyWithExactMatchQuotes()
    {
        $filter = new Filter([
            'filter_id' => 'technologies',
            'name' => 'Technologies',
            'group' => 'Technology',
            'type' => 'text',
            'settings' => [
                'fields' => [
                    'company' => ['company_technologies'],
                ],
                'target_model' => Company::class,
            ],
        ]);

        $handler = new TextFilterHandler($filter);
        $builder = new ElasticQueryBuilder(Company::class);

        // Test exact match with quotes
        $handler->apply($builder, ['include' => ['"React"']]);
        $query = $builder->toArray();

        $json = json_encode($query);
        // Expect to find a term query for the keyword subfield
        $this->assertStringContainsString('company_technologies.keyword', $json);
    }

    public function testExpandJobTitleAbbreviation()
    {
        $filter = new Filter([
            'filter_id' => 'job_title',
            'name' => 'Job Title',
            'group' => 'Role',
            'type' => 'text',
            'settings' => [
                'fields' => [
                    'contact' => ['title'],
                ],
                'target_model' => \App\Models\Contact::class,
            ],
        ]);

        $handler = new TextFilterHandler($filter);

        // Expansion is internal, but we can verify it via apply()
        $builder = new ElasticQueryBuilder(\App\Models\Contact::class);
        $handler->apply($builder, ['include' => ['CTO']], 'contact');
        $json = json_encode($builder->toArray());

        $this->assertStringContainsString('Chief Technical Officer', $json);
        $this->assertStringContainsString('Chief Technology Officer', $json);
    }
}
