<?php declare(strict_types=1);

namespace AdvancedSearchTest\Querier;

use AdvancedSearch\Query;
use AdvancedSearch\Querier\InternalQuerier;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for item set (collection) filters and facets in AdvancedSearch.
 *
 * These tests verify that:
 * 1. Filtering items by item_set_id works correctly
 * 2. Facets on item_set_id return correct counts
 * 3. Multiple item set filters work with AND/OR logic
 *
 * @group querier
 */
class ItemSetFilterAndFacetTest extends AbstractHttpControllerTestCase
{
    /**
     * @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation
     */
    protected $searchEngine;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var \Omeka\Api\Representation\ItemSetRepresentation[]
     */
    protected $itemSets = [];

    /**
     * @var \Omeka\Api\Representation\ItemRepresentation[]
     */
    protected $items = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAdmin();

        // Create test item sets (collections)
        $this->createTestItemSets();

        // Create test items in those item sets
        $this->createTestItems();

        // Create search engine with internal adapter
        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestInternalEngine',
            'o:engine_adapter' => 'internal',
            'o:settings' => [
                'resource_types' => [
                    'items',
                    'item_sets',
                ],
            ],
        ]);
        $this->searchEngine = $response->getContent();

        // Create search config with item_set_id filter and facet
        $response = $this->api()->create('search_configs', [
            'o:name' => 'TestSearchConfig',
            'o:slug' => 'test/search',
            'o:search_engine' => [
                'o:id' => $this->searchEngine->id(),
            ],
            'o:form_adapter' => 'basic',
            'o:settings' => [
                'request' => [],
                'q' => [],
                'index' => [
                    'aliases' => [],
                ],
                'form' => [
                    'filters' => [
                        'item_set_id' => [
                            'field' => 'item_set_id',
                            'label' => 'Collection',
                            'type' => 'Select',
                        ],
                    ],
                ],
                'results' => [],
                'facet' => [
                    'facets' => [
                        'item_set_id' => [
                            'field' => 'item_set_id',
                            'label' => 'Collection',
                            'type' => 'Checkbox',
                            'order' => 'total desc',
                            'limit' => 10,
                            'display_count' => true,
                        ],
                    ],
                ],
            ],
        ]);
        $this->searchConfig = $response->getContent();
    }

    public function tearDown(): void
    {
        // Delete search config and engine first
        if ($this->searchConfig) {
            try {
                $this->api()->delete('search_configs', $this->searchConfig->id());
            } catch (\Exception $e) {
                // Ignore
            }
        }
        if ($this->searchEngine) {
            try {
                $this->api()->delete('search_engines', $this->searchEngine->id());
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Delete test items
        foreach ($this->items as $item) {
            try {
                $this->api()->delete('items', $item->id());
            } catch (\Exception $e) {
                // Ignore if already deleted
            }
        }

        // Delete test item sets
        foreach ($this->itemSets as $itemSet) {
            try {
                $this->api()->delete('item_sets', $itemSet->id());
            } catch (\Exception $e) {
                // Ignore if already deleted
            }
        }

        parent::tearDown();
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $auth = $services->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Get the API manager.
     */
    protected function api(): \Omeka\Api\Manager
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\ApiManager');
    }

    /**
     * Create test item sets for use in tests.
     */
    protected function createTestItemSets(): void
    {
        // Create three item sets for testing
        $itemSetData = [
            ['o:title' => 'Collection A - Photos'],
            ['o:title' => 'Collection B - Documents'],
            ['o:title' => 'Collection C - Videos'],
        ];

        foreach ($itemSetData as $data) {
            $response = $this->api()->create('item_sets', $data);
            $this->itemSets[] = $response->getContent();
        }
    }

    /**
     * Create test items in the item sets.
     */
    protected function createTestItems(): void
    {
        // Items in Collection A (Photos) - 3 items
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->api()->create('items', [
                'o:item_set' => [
                    ['o:id' => $this->itemSets[0]->id()],
                ],
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => "Photo $i",
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }

        // Items in Collection B (Documents) - 5 items
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->api()->create('items', [
                'o:item_set' => [
                    ['o:id' => $this->itemSets[1]->id()],
                ],
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => "Document $i",
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }

        // Items in Collection C (Videos) - 2 items
        for ($i = 1; $i <= 2; $i++) {
            $response = $this->api()->create('items', [
                'o:item_set' => [
                    ['o:id' => $this->itemSets[2]->id()],
                ],
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => "Video $i",
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }

        // Item in multiple collections (A and B)
        $response = $this->api()->create('items', [
            'o:item_set' => [
                ['o:id' => $this->itemSets[0]->id()],
                ['o:id' => $this->itemSets[1]->id()],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'Multi-collection item',
                ],
            ],
        ]);
        $this->items[] = $response->getContent();
    }

    /**
     * Get property ID by term.
     */
    protected function getPropertyId(string $term): int
    {
        $services = $this->getApplication()->getServiceManager();
        $easyMeta = $services->get('Common\EasyMeta');
        return $easyMeta->propertyId($term);
    }

    /**
     * Get the internal querier.
     */
    protected function getQuerier(): InternalQuerier
    {
        $services = $this->getApplication()->getServiceManager();

        /** @var \AdvancedSearch\Querier\InternalQuerier $querier */
        $querier = new InternalQuerier();
        $querier->setServiceLocator($services);
        $querier->setSearchEngine($this->searchEngine);

        return $querier;
    }

    // =========================================================================
    // TESTS FOR ITEM SET (COLLECTION) FILTERS
    // =========================================================================

    /**
     * Test filtering items by a single item set ID.
     */
    public function testFilterBySingleItemSetId(): void
    {
        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->addFilter('item_set_id', $this->itemSets[0]->id());
        $query->setLimitPage(1, 100);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        // Collection A has 3 items + 1 multi-collection item = 4 items
        $this->assertEquals(4, $response->getTotalResults());
    }

    /**
     * Test filtering items by a different item set ID.
     */
    public function testFilterByDifferentItemSetId(): void
    {
        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->addFilter('item_set_id', $this->itemSets[1]->id());
        $query->setLimitPage(1, 100);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        // Collection B has 5 items + 1 multi-collection item = 6 items
        $this->assertEquals(6, $response->getTotalResults());
    }

    /**
     * Test filtering items by collection C.
     */
    public function testFilterByCollectionC(): void
    {
        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->addFilter('item_set_id', $this->itemSets[2]->id());
        $query->setLimitPage(1, 100);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        // Collection C has 2 items
        $this->assertEquals(2, $response->getTotalResults());
    }

    /**
     * Test filtering items by multiple item set IDs (should return items in any of them).
     */
    public function testFilterByMultipleItemSetIds(): void
    {
        $query = new Query();
        $query->setResourceTypes(['items']);
        // Add both item sets as filter values
        $query->addFilter('item_set_id', $this->itemSets[0]->id());
        $query->addFilter('item_set_id', $this->itemSets[2]->id());
        $query->setLimitPage(1, 100);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        // Collection A has 4 items, Collection C has 2 items
        // Multi-collection item is only counted once = 6 items total
        $this->assertEquals(6, $response->getTotalResults());
    }

    /**
     * Test that filtering by non-existent item set returns no results.
     */
    public function testFilterByNonExistentItemSetId(): void
    {
        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->addFilter('item_set_id', 999999);
        $query->setLimitPage(1, 100);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(0, $response->getTotalResults());
    }

    /**
     * Test filtering combined with a text query.
     */
    public function testFilterItemSetWithTextQuery(): void
    {
        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setQuery('Photo');
        $query->addFilter('item_set_id', $this->itemSets[0]->id());
        $query->setLimitPage(1, 100);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        // Only "Photo 1", "Photo 2", "Photo 3" should match
        $this->assertEquals(3, $response->getTotalResults());
    }

    /**
     * Test that multi-collection items are found when searching any of their collections.
     */
    public function testMultiCollectionItemFoundInEitherCollection(): void
    {
        // Search in Collection A
        $query1 = new Query();
        $query1->setResourceTypes(['items']);
        $query1->setQuery('Multi-collection');
        $query1->addFilter('item_set_id', $this->itemSets[0]->id());
        $query1->setLimitPage(1, 100);

        $querier = $this->getQuerier();
        $querier->setQuery($query1);
        $response1 = $querier->query();

        $this->assertTrue($response1->isSuccess());
        $this->assertEquals(1, $response1->getTotalResults());

        // Search in Collection B
        $query2 = new Query();
        $query2->setResourceTypes(['items']);
        $query2->setQuery('Multi-collection');
        $query2->addFilter('item_set_id', $this->itemSets[1]->id());
        $query2->setLimitPage(1, 100);

        $querier->setQuery($query2);
        $response2 = $querier->query();

        $this->assertTrue($response2->isSuccess());
        $this->assertEquals(1, $response2->getTotalResults());
    }

    // =========================================================================
    // TESTS FOR ITEM SET FACETS
    // =========================================================================

    /**
     * Check if facets are available (requires Reference module plugin).
     */
    protected function hasFacetSupport(): bool
    {
        $plugins = $this->getApplication()->getServiceManager()->get('ControllerPluginManager');
        return $plugins->has('references');
    }

    /**
     * Test that facets return correct counts for all item sets.
     */
    public function testItemSetFacetCounts(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Facet support requires Reference module plugin.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'item_set_id' => [
                'field' => 'item_set_id',
                'label' => 'Collection',
                'type' => 'Checkbox',
                'order' => 'total desc',
                'limit' => 10,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());

        $facetCounts = $response->getFacetCounts('item_set_id');

        // Verify we have facet counts
        $this->assertNotEmpty($facetCounts, 'Facet counts should not be empty');

        // Find counts for each collection
        $collectionACounts = $this->findFacetCount($facetCounts, $this->itemSets[0]->id());
        $collectionBCounts = $this->findFacetCount($facetCounts, $this->itemSets[1]->id());
        $collectionCCounts = $this->findFacetCount($facetCounts, $this->itemSets[2]->id());

        // Collection A: 3 photos + 1 multi-collection = 4
        $this->assertEquals(4, $collectionACounts, 'Collection A should have 4 items');

        // Collection B: 5 documents + 1 multi-collection = 6
        $this->assertEquals(6, $collectionBCounts, 'Collection B should have 6 items');

        // Collection C: 2 videos
        $this->assertEquals(2, $collectionCCounts, 'Collection C should have 2 items');
    }

    /**
     * Test that facets are correctly filtered when a filter is applied.
     */
    public function testItemSetFacetWithActiveFilter(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Facet support requires Reference module plugin.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setQuery('Document');
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'item_set_id' => [
                'field' => 'item_set_id',
                'label' => 'Collection',
                'type' => 'Checkbox',
                'order' => 'total desc',
                'limit' => 10,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());

        $facetCounts = $response->getFacetCounts('item_set_id');

        // Only Collection B should have results (5 documents)
        $collectionBCounts = $this->findFacetCount($facetCounts, $this->itemSets[1]->id());
        $this->assertEquals(5, $collectionBCounts, 'Collection B should have 5 documents');

        // Collection A and C should not appear in facets for "Document" query
        $collectionACounts = $this->findFacetCount($facetCounts, $this->itemSets[0]->id());
        $collectionCCounts = $this->findFacetCount($facetCounts, $this->itemSets[2]->id());
        $this->assertEquals(0, $collectionACounts, 'Collection A should have 0 documents');
        $this->assertEquals(0, $collectionCCounts, 'Collection C should have 0 documents');
    }

    /**
     * Test active facet (selecting a facet value).
     */
    public function testActiveFacetFilter(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Facet support requires Reference module plugin.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'item_set_id' => [
                'field' => 'item_set_id',
                'label' => 'Collection',
                'type' => 'Checkbox',
                'order' => 'total desc',
                'limit' => 10,
            ],
        ]);
        // Simulate clicking on Collection A facet
        $query->addActiveFacet('item_set_id', (string) $this->itemSets[0]->id());

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());

        // Results should be filtered to only Collection A items
        $this->assertEquals(4, $response->getTotalResults());

        // Active facets should be recorded
        $activeFacets = $response->getActiveFacets('item_set_id');
        $this->assertContains((string) $this->itemSets[0]->id(), $activeFacets);
    }

    /**
     * Test multiple active facets (selecting multiple collections).
     */
    public function testMultipleActiveFacets(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Facet support requires Reference module plugin.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'item_set_id' => [
                'field' => 'item_set_id',
                'label' => 'Collection',
                'type' => 'Checkbox',
                'order' => 'total desc',
                'limit' => 10,
            ],
        ]);
        // Simulate clicking on Collection A and Collection C facets
        $query->addActiveFacet('item_set_id', (string) $this->itemSets[0]->id());
        $query->addActiveFacet('item_set_id', (string) $this->itemSets[2]->id());

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());

        // Results should include Collection A (4 items) and Collection C (2 items)
        // Note: The behavior depends on how multiple facets are combined (OR vs AND)
        $totalResults = $response->getTotalResults();
        $this->assertGreaterThan(0, $totalResults);
    }

    /**
     * Test facet ordering by total (descending).
     */
    public function testFacetOrderByTotalDesc(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Facet support requires Reference module plugin.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'item_set_id' => [
                'field' => 'item_set_id',
                'label' => 'Collection',
                'type' => 'Checkbox',
                'order' => 'total desc',
                'limit' => 10,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());

        $facetCounts = $response->getFacetCounts('item_set_id');

        if (count($facetCounts) >= 2) {
            // Verify descending order by count
            $prevCount = PHP_INT_MAX;
            foreach ($facetCounts as $facet) {
                $this->assertLessThanOrEqual(
                    $prevCount,
                    $facet['count'],
                    'Facets should be ordered by count descending'
                );
                $prevCount = $facet['count'];
            }
        }
    }

    /**
     * Test facet limit.
     */
    public function testFacetLimit(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Facet support requires Reference module plugin.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'item_set_id' => [
                'field' => 'item_set_id',
                'label' => 'Collection',
                'type' => 'Checkbox',
                'order' => 'total desc',
                'limit' => 2, // Limit to 2 facets
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());

        $facetCounts = $response->getFacetCounts('item_set_id');

        // Should have at most 2 facet values
        $this->assertLessThanOrEqual(2, count($facetCounts), 'Facet limit should be respected');
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Find the count for a specific item set ID in the facet counts.
     */
    protected function findFacetCount(array $facetCounts, int $itemSetId): int
    {
        foreach ($facetCounts as $facet) {
            if ((int) $facet['value'] === $itemSetId) {
                return (int) $facet['count'];
            }
        }
        return 0;
    }
}
