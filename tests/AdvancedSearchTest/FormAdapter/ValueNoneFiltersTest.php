<?php declare(strict_types=1);

namespace AdvancedSearchTest\FormAdapter;

use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Filters without value ("ex", "nex"…) are kept whatever the form options.
 *
 * They are set by links, for example the link to the resources without title
 * built by the module Reference.
 *
 * @group form-adapter
 */
class ValueNoneFiltersTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $searchEngine = $this->internalSearchEngine(['resource_types' => ['items']]);
        $this->searchConfig = $this->api()->create('search_configs', [
            'o:name' => 'TestValueNoneFilters',
            'o:slug' => 'test/value-none-filters',
            'o:search_engine' => ['o:id' => $searchEngine->id()],
            'o:form_adapter' => 'main',
            'o:settings' => [
                'request' => [],
                'q' => [],
                'index' => ['aliases' => []],
                'form' => ['filters' => []],
                'results' => [],
                'facet' => ['facets' => []],
            ],
        ])->getContent();
    }

    public function tearDown(): void
    {
        if ($this->searchConfig) {
            $this->api()->delete('search_configs', $this->searchConfig->id());
        }
        parent::tearDown();
    }

    protected function toQuery(array $request, bool $joiner, bool $operator): \AdvancedSearch\Query
    {
        return $this->searchConfig->formAdapter()->toQuery($request, [
            'available_fields' => [],
            'facet' => [],
            'advanced' => ['field_joiner' => $joiner, 'field_operator' => $operator],
            'aliases' => [],
            'fields_query_args' => [],
            'request' => [],
        ]);
    }

    public function formOptionsProvider(): array
    {
        return [
            'no joiner, no operator' => [false, false],
            'no joiner, operator' => [false, true],
            'joiner, no operator' => [true, false],
            'joiner, operator' => [true, true],
        ];
    }

    /**
     * @dataProvider formOptionsProvider
     */
    public function testFilterWithoutValueIsKept(bool $joiner, bool $operator): void
    {
        $query = $this->toQuery([
            'resource_class_id' => ['26'],
            'filter' => [
                ['join' => 'and', 'field' => 'dcterms:title', 'type' => 'nex'],
            ],
        ], $joiner, $operator);

        $filtersQuery = $query->getFiltersQuery();
        $this->assertArrayHasKey('dcterms:title', $filtersQuery);
        $this->assertSame('nex', $filtersQuery['dcterms:title'][0]['type']);
        $this->assertSame('and', $filtersQuery['dcterms:title'][0]['join']);
        $this->assertSame('', $filtersQuery['dcterms:title'][0]['val']);
    }

    /**
     * @dataProvider formOptionsProvider
     */
    public function testFilterWithValueTypeAndWithoutValueIsSkipped(bool $joiner, bool $operator): void
    {
        $query = $this->toQuery([
            'filter' => [
                ['join' => 'and', 'field' => 'dcterms:title', 'type' => 'eq'],
                ['join' => 'and', 'field' => 'dcterms:subject', 'type' => 'eq', 'val' => ' '],
            ],
        ], $joiner, $operator);

        $this->assertSame([], $query->getFiltersQuery());
    }
}
