<?php declare(strict_types=1);

namespace AdvancedSearchTest\Querier;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Run the reference queries through the Internal engine (the api) on deterministic
 * fixtures and assert querier-agnostic invariants.
 *
 * This test needs no Solr, so it runs in CI: it exercises the query pivot
 * normalization (property[]/filter[] to the flat filters) and the Internal
 * resolution of every argument. The parity with the Solr engine is checked by
 * the development script data/scripts/compare-queriers.php, which reuses the
 * same reference queries (QuerierParityQueries) against a real Solr core.
 */
class InternalQuerierParityTest extends AbstractHttpControllerTestCase
{
    protected $itemSets = [];
    protected $items = [];
    protected $searchEngine;
    protected $template;
    protected $fixture = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $api = $this->api();
        $titleId = $this->getPropertyId('dcterms:title');
        $subjectId = $this->getPropertyId('dcterms:subject');
        $relationId = $this->getPropertyId('dcterms:relation');
        $dateId = $this->getPropertyId('dcterms:date');

        // Three item sets.
        foreach (['Photos', 'Documents', 'Videos'] as $name) {
            $this->itemSets[] = $api->create('item_sets', [
                'dcterms:title' => [[
                    'type' => 'literal',
                    'property_id' => $titleId,
                    '@value' => $name,
                ]],
            ])->getContent();
        }

        // A resource class and a resource template really installed by the
        // core.
        $classId = (int) $this->connection()->fetchOne(
            'SELECT id FROM resource_class WHERE local_name = "Text" LIMIT 1'
        );
        $this->template = $api->create('resource_templates', [
            'o:label' => 'Parity template',
        ])->getContent();

        // Ten items: known titles, a shared subject value on the first four, a
        // linked relation on the first item, a date on the first item.
        for ($i = 1; $i <= 10; $i++) {
            $data = [
                'o:item_set' => [['o:id' => $this->itemSets[$i % 3]->id()]],
                'o:resource_class' => $classId ? ['o:id' => $classId] : null,
                'o:resource_template' => ['o:id' => $this->template->id()],
                'dcterms:title' => [[
                    'type' => 'literal',
                    'property_id' => $titleId,
                    '@value' => "Parity item $i",
                ]],
            ];
            if ($i <= 4) {
                $data['dcterms:subject'] = [[
                    'type' => 'literal',
                    'property_id' => $subjectId,
                    '@value' => 'Europe',
                ]];
            }
            $this->items[] = $api->create('items', array_filter($data))->getContent();
        }
        // A linked relation from item 2 to item 1, and a date on item 1.
        $api->update('items', $this->items[1]->id(), [
            'dcterms:relation' => [[
                'type' => 'resource:item',
                'property_id' => $relationId,
                'value_resource_id' => $this->items[0]->id(),
            ]],
        ], [], ['isPartial' => true, 'collectionAction' => 'append']);
        $api->update('items', $this->items[0]->id(), [
            'dcterms:date' => [[
                'type' => 'literal',
                'property_id' => $dateId,
                '@value' => '1914',
            ]],
        ], [], ['isPartial' => true, 'collectionAction' => 'append']);

        $this->searchEngine = $api->create('search_engines', [
            'o:name' => 'ParityInternalEngine',
            'o:engine_adapter' => 'internal',
            'o:settings' => ['resource_types' => ['items', 'item_sets']],
        ])->getContent();

        $this->fixture = [
            'word' => 'Golden',
            'title_sample' => 'Parity item 1',
            'subject' => 'Europe',
            'property_term' => 'dcterms:subject',
            'second_term' => 'dcterms:title',
            'ids' => array_map(fn ($it) => $it->id(), array_slice($this->items, 0, 5)),
            'item_set_id' => $this->itemSets[1]->id(),
            'resource_class_id' => $classId,
            'resource_class_term' => 'dctype:Text',
            'resource_template_id' => $this->template->id(),
            'owner_id' => 1,
            'linked_term' => 'dcterms:relation',
            'linked_id' => $this->items[0]->id(),
            'date_term' => 'dcterms:date',
            'year' => 1914,
        ];
    }

    public function tearDown(): void
    {
        foreach (['search_engines' => [$this->searchEngine]] as $type => $resources) {
            foreach ($resources as $resource) {
                if ($resource) {
                    try {
                        $this->api()->delete($type, $resource->id());
                    } catch (\Exception $e) {
                    }
                }
            }
        }
        foreach ($this->items as $item) {
            try {
                $this->api()->delete('items', $item->id());
            } catch (\Exception $e) {
            }
        }
        foreach ($this->itemSets as $itemSet) {
            try {
                $this->api()->delete('item_sets', $itemSet->id());
            } catch (\Exception $e) {
            }
        }
        if ($this->template) {
            try {
                $this->api()->delete('resource_templates', $this->template->id());
            } catch (\Exception $e) {
            }
        }
        parent::tearDown();
    }

    /**
     * Every reference query executes on the Internal engine without error.
     */
    public function testReferenceQueriesExecute(): void
    {
        foreach (QuerierParityQueries::all($this->fixture) as $label => $entry) {
            $ids = $this->runQuery($entry['query']);
            $this->assertIsArray($ids, "Query $label should return an id array.");
        }
    }

    /**
     * Querier-agnostic invariants that must hold whatever the engine.
     */
    public function testInvariants(): void
    {
        $q = QuerierParityQueries::all($this->fixture);
        $all = $this->runQuery([]);
        $total = count($all);

        // Existence partitions the whole set.
        $ex = $this->runQuery($q['prop_ex']['query']);
        $nex = $this->runQuery($q['prop_nex']['query']);
        $this->assertSame($total, count($ex) + count($nex), 'ex + nex must be the total.');
        $this->assertEmpty(array_intersect($ex, $nex), 'ex and nex are disjoint.');

        // Negation is the complement.
        $eq = $this->runQuery($q['prop_eq']['query']);
        $neq = $this->runQuery($q['prop_neq']['query']);
        $this->assertSame($total, count($eq) + count($neq), 'eq + neq must be the total.');

        // Exact is a subset of existence and of contains.
        $in = $this->runQuery($q['prop_in']['query']);
        $this->assertEmpty(array_diff($eq, $ex), 'eq must be a subset of ex.');
        $this->assertEmpty(array_diff($eq, $in), 'eq must be a subset of in.');

        // A filter on a system field equals the matching scalar arg.
        $this->assertEqualIds(
            $this->runQuery($q['item_set_id']['query']),
            $this->runQuery($q['filter_system_item_set']['query']),
            'filter[item_set_id] must equal the arg item_set_id.'
        );
        $this->assertEqualIds(
            $this->runQuery($q['owner_id']['query']),
            $this->runQuery($q['filter_system_owner']['query']),
            'filter[owner_id] must equal the arg owner_id.'
        );

        // filter[] equals the equivalent property[] row.
        $this->assertEqualIds($eq, $this->runQuery($q['filter_eq']['query']), 'filter eq must equal property eq.');

        // A scalar arg and its negation partition the whole set.
        $inSet = $this->runQuery($q['item_set_id']['query']);
        $notInSet = $this->runQuery($q['not_item_set_id']['query']);
        $this->assertSame($total, count($inSet) + count($notInSet), 'item_set + not_item_set must be the total.');

        // OR is a superset of each operand; the mix keeps the SQL precedence
        // (A and B) or C, so it is a superset of C.
        $orTwo = $this->runQuery($q['prop_or_two']['query']);
        $this->assertEmpty(array_diff($eq, $orTwo), 'or must contain the first operand.');
        $mix = $this->runQuery($q['prop_and_or_mix']['query']);
        $this->assertEmpty(array_diff($eq, $mix), 'the and/or mix must contain the trailing or operand.');

        // A combination is a subset of each of its parts.
        $combo = $this->runQuery($q['combo_set_class']['query']);
        $this->assertEmpty(array_diff($combo, $inSet), 'combo must be a subset of item_set.');

        // An unknown arg is ignored, like the api does.
        $this->assertEqualIds($all, $this->runQuery($q['unhandled_arg']['query']), 'an unknown arg must be ignored.');

        // res and nres partition the whole set on the linked property.
        $res = $this->runQuery($q['prop_res']['query']);
        $nres = $this->runQuery($q['prop_nres']['query']);
        $this->assertSame($total, count($res) + count($nres), 'res + nres must be the total.');
        $this->assertContains($this->items[1]->id(), $res, 'the linking item must be in res.');
    }

    protected function runQuery(array $query): array
    {
        $query['per_page'] = 1000;
        $query['page'] = 1;
        return array_map('intval', array_values(
            $this->api()->search('items', $query, ['returnScalar' => 'id'])->getContent()
        ));
    }

    protected function assertEqualIds(array $expected, array $actual, string $message): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, $message);
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

    protected function connection(): \Doctrine\DBAL\Connection
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\Connection');
    }

    protected function getPropertyId(string $term): int
    {
        [$prefix, $localName] = explode(':', $term);
        return (int) $this->connection()->fetchOne(
            'SELECT p.id FROM property p
            INNER JOIN vocabulary v ON v.id = p.vocabulary_id
            WHERE v.prefix = ? AND p.local_name = ?',
            [$prefix, $localName]
        );
    }
}
