<?php declare(strict_types=1);

namespace AdvancedSearchTest\Querier;

use AdvancedSearch\Query;
use AdvancedSearch\Querier\InternalQuerier;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for `first_digits` numeric values in InternalQuerier facet parsing.
 *
 * Verifies that first_digits = 2, 3 etc. are passed through to References
 * and produce decade/century-level facet values.
 *
 * @group querier
 */
class FirstDigitsFacetTest extends AbstractHttpControllerTestCase
{
    /**
     * @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation
     */
    protected $searchEngine;

    /**
     * @var \Omeka\Api\Representation\ItemRepresentation[]
     */
    protected $items = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->createTestData();
        $this->createSearchEngine();
    }

    public function tearDown(): void
    {
        if ($this->searchEngine) {
            try {
                $this->api()->delete('search_engines', $this->searchEngine->id());
            } catch (\Exception $e) {
            }
        }
        foreach ($this->items as $item) {
            try {
                $this->api()->delete('items', $item->id());
            } catch (\Exception $e) {
            }
        }
        parent::tearDown();
    }

    protected function loginAdmin(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $auth = $services->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function api(): \Omeka\Api\Manager
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\ApiManager');
    }

    protected function getPropertyId(string $term): int
    {
        return $this->getApplication()->getServiceManager()
            ->get('Common\EasyMeta')->propertyId($term);
    }

    protected function createTestData(): void
    {
        $dates = [
            '2014-10-12',
            '2019-03-20',
            '2021-01-10',
            '1999-12-31',
            '1850-06-15',
            '-500-10-12',
            '-523-03-01',
        ];
        foreach ($dates as $date) {
            $response = $this->api()->create('items', [
                'dcterms:title' => [[
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => "Item $date",
                ]],
                'dcterms:date' => [[
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:date'),
                    '@value' => $date,
                ]],
            ]);
            $this->items[] = $response->getContent();
        }
    }

    protected function createSearchEngine(): void
    {
        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestFirstDigitsEngine',
            'o:engine_adapter' => 'internal',
            'o:settings' => [
                'resource_types' => ['items'],
            ],
        ]);
        $this->searchEngine = $response->getContent();
    }

    protected function hasFacetSupport(): bool
    {
        $plugins = $this->getApplication()->getServiceManager()
            ->get('ControllerPluginManager');
        return $plugins->has('references');
    }

    protected function getQuerier(): InternalQuerier
    {
        $services = $this->getApplication()->getServiceManager();
        $querier = new InternalQuerier();
        $querier->setServiceLocator($services);
        $querier->setSearchEngine($this->searchEngine);
        return $querier;
    }

    /**
     * Extract facet val values from the facet counts structure.
     *
     * Facet counts from InternalQuerier have the structure:
     * [['value' => idx, 'count' => ['val' => ..., 'total' => ...]], ...]
     *
     * @return array List of facet val values (mixed int/string).
     */
    protected function extractFacetValues(array $facets): array
    {
        $values = [];
        foreach ($facets as $facet) {
            if (isset($facet['count']['val'])) {
                $values[] = $facet['count']['val'];
            } elseif (isset($facet['value'])) {
                $values[] = $facet['value'];
            }
        }
        return $values;
    }

    // =========================================================================
    // FIRST_DIGITS PARSING TESTS
    // =========================================================================

    /**
     * Test that first_digits = true (boolean) produces full year facets.
     */
    public function testFirstDigitsTrueProducesFullYears(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'first_digits' => true,
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');
        $this->assertNotEmpty($facets, 'Facets should not be empty with first_digits=true');

        $values = $this->extractFacetValues($facets);
        // Should have full years.
        $this->assertContains(2014, $values);
        $this->assertContains(1999, $values);
    }

    /**
     * Test that first_digits = 3 produces decade-level facets.
     */
    public function testFirstDigits3ProducesDecadeFacets(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'first_digits' => 3,
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');
        $this->assertNotEmpty($facets, 'Facets should not be empty with first_digits=3');

        $values = $this->extractFacetValues($facets);
        // 2014 and 2019 -> 201
        $this->assertContains(201, $values);
        // 2021 -> 202
        $this->assertContains(202, $values);
        // Full year 2014 should NOT appear.
        $this->assertNotContains(2014, $values);
    }

    /**
     * Test that first_digits = 2 produces century-level facets.
     */
    public function testFirstDigits2ProducesCenturyFacets(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'first_digits' => 2,
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');
        $this->assertNotEmpty($facets, 'Facets should not be empty with first_digits=2');

        $values = $this->extractFacetValues($facets);
        // All 20xx -> 20
        $this->assertContains(20, $values);
        // 1999 -> 19
        $this->assertContains(19, $values);
        // 1850 -> 18
        $this->assertContains(18, $values);
    }

    /**
     * Test that first_digits = false disables digit extraction.
     */
    public function testFirstDigitsFalseDisablesExtraction(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'Checkbox',
                'first_digits' => false,
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');
        $this->assertNotEmpty($facets, 'Facets should not be empty with first_digits=false');

        $values = $this->extractFacetValues($facets);
        // Should have full date strings, not just years.
        $this->assertContains('2014-10-12', $values);
        $this->assertContains('1999-12-31', $values);
    }

    /**
     * Test first_digits parsing: string '3' is treated as integer 3.
     */
    public function testFirstDigitsStringNumericIsParsedAsInteger(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'first_digits' => '3', // String, not int.
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');

        $values = $this->extractFacetValues($facets);
        // Should behave like integer 3: decade grouping.
        $this->assertContains(201, $values);
        $this->assertNotContains(2014, $values);
    }

    /**
     * Test first_digits via options sub-key (as configured in form).
     */
    public function testFirstDigitsViaOptionsSubKey(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'options' => [
                    'first_digits' => 2,
                ],
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');

        $values = $this->extractFacetValues($facets);
        $this->assertContains(20, $values);
        $this->assertNotContains(2014, $values);
    }

    /**
     * Test first_digits = true with negative years produces full negative years.
     */
    public function testFirstDigitsTrueWithNegativeYears(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'first_digits' => true,
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');

        $values = $this->extractFacetValues($facets);
        $this->assertContains(-500, $values);
        $this->assertContains(-523, $values);
    }

    /**
     * Test first_digits = 2 with negative years truncates to 2 digits.
     *
     * -500 -> -50, -523 -> -52
     */
    public function testFirstDigits2WithNegativeYears(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'first_digits' => 2,
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');

        $values = $this->extractFacetValues($facets);
        // -500 -> first 2 digits of 500 = 50, negated -> -50
        $this->assertContains(-50, $values);
        // -523 -> first 2 digits of 523 = 52, negated -> -52
        $this->assertContains(-52, $values);
        // Full negative year should NOT appear.
        $this->assertNotContains(-500, $values);
    }

    /**
     * Test first_digits = 3 with negative years keeps all 3 digits.
     *
     * -500 -> -500, -523 -> -523 (3-digit numbers stay intact)
     */
    public function testFirstDigits3WithNegativeYears(): void
    {
        if (!$this->hasFacetSupport()) {
            $this->markTestSkipped('Requires Reference module.');
        }

        $query = new Query();
        $query->setResourceTypes(['items']);
        $query->setLimitPage(1, 100);
        $query->setFacets([
            'dcterms:date' => [
                'field' => 'dcterms:date',
                'label' => 'Date',
                'type' => 'SelectRange',
                'first_digits' => 3,
                'order' => 'values asc',
                'limit' => 100,
            ],
        ]);

        $querier = $this->getQuerier();
        $querier->setQuery($query);
        $response = $querier->query();

        $this->assertTrue($response->isSuccess());
        $facets = $response->getFacetCounts('dcterms:date');

        $values = $this->extractFacetValues($facets);
        $this->assertContains(-500, $values);
        $this->assertContains(-523, $values);
    }
}
