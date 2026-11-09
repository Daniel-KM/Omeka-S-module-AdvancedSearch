<?php declare(strict_types=1);

namespace AdvancedSearchTest\FormAdapter;

use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Simple args of the url (site, item set, class…) are separate conditions.
 *
 * They must not be chained with the advanced filters: the first filter may be
 * joined with "or", so the whole site would be returned (links built by the
 * module Reference, with "site_id" and filters joined with "or").
 *
 * @group form-adapter
 */
class SimpleArgsTest extends AbstractHttpControllerTestCase
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
            'o:name' => 'TestSimpleArgs',
            'o:slug' => 'test/simple-args',
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

    protected function toQuery(array $request): \AdvancedSearch\Query
    {
        return $this->searchConfig->formAdapter()->toQuery($request, [
            'available_fields' => [],
            'facet' => [],
            'advanced' => $this->searchConfig->advancedFilterSettings(),
            'aliases' => [],
            'fields_query_args' => [],
            'request' => [],
        ]);
    }

    public function testSimpleArgIsNotChainedWithAdvancedFilters(): void
    {
        $query = $this->toQuery([
            'site_id' => '1',
            'filter' => [
                ['join' => 'or', 'field' => 'dcterms:contributor', 'type' => 'eq', 'val' => 'Paris, Gaston'],
                ['join' => 'or', 'field' => 'dcterms:creator', 'type' => 'eq', 'val' => 'Paris, Gaston'],
            ],
        ]);

        $this->assertSame(['site_id' => ['1']], $query->getFilters());
        $filtersQuery = $query->getFiltersQuery();
        $this->assertArrayNotHasKey('site_id', $filtersQuery);
        $this->assertSame('or', $filtersQuery['dcterms:contributor'][0]['join']);
        $this->assertSame('or', $filtersQuery['dcterms:creator'][0]['join']);
    }

    public function testMultipleValuesOfSimpleArgAreKept(): void
    {
        $query = $this->toQuery(['item_set_id' => ['3', '4']]);

        $this->assertSame(['item_set_id' => ['3', '4']], $query->getFilters());
        $this->assertSame([], $query->getFiltersQuery());
    }

    public function testAdvancedFiltersWithoutSimpleArgAreUnchanged(): void
    {
        $query = $this->toQuery([
            'filter' => [
                ['join' => 'and', 'field' => 'dcterms:creator', 'type' => 'eq', 'val' => 'Paris, Gaston'],
            ],
        ]);

        $this->assertSame([], $query->getFilters());
        $this->assertSame('eq', $query->getFiltersQuery()['dcterms:creator'][0]['type']);
    }

    public function testCorePropertyArgsAreConvertedIntoFilters(): void
    {
        $query = $this->toQuery([
            'property' => [
                ['joiner' => 'or', 'property' => 'dcterms:spatial', 'type' => 'eq', 'text' => 'Assam'],
            ],
        ]);

        $filtersQuery = $query->getFiltersQuery();
        $this->assertArrayHasKey('dcterms:spatial', $filtersQuery);
        $this->assertSame('eq', $filtersQuery['dcterms:spatial'][0]['type']);
        $this->assertSame('Assam', $filtersQuery['dcterms:spatial'][0]['val']);
    }
}
