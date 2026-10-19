<?php declare(strict_types=1);

namespace AdvancedSearchTest\Querier;

use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The resource type "concepts" may be set while the entity Concept does not
 * exist: module Thesaurus not installed, or before version 3.4.26, when the
 * concepts were items.
 *
 * @group querier
 */
class ThesaurusConceptsTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    protected $engineSettings;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $item = $this->api()->create('items', [
            'dcterms:title' => [
                ['type' => 'literal', 'property_id' => (int) $easyMeta->propertyId('dcterms:title'), '@value' => 'Concept as item'],
            ],
        ])->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];

        $this->engineSettings = $this->internalSearchEngine()->settings();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        if ($this->engineSettings !== null) {
            $engine = $this->internalSearchEngine();
            $this->api()->update('search_engines', $engine->id(), [
                'o:name' => $engine->name(),
                'o:engine_adapter' => 'internal',
                'o:settings' => $this->engineSettings,
            ]);
        }
        $this->logout();
        parent::tearDown();
    }

    public function testValuesWithResourceTypeConcepts(): void
    {
        $engine = $this->internalSearchEngine(['resource_types' => ['items', 'concepts']]);
        $querier = $engine->querier();
        $querier->setQuery(new \AdvancedSearch\Query());
        $property = new \ReflectionProperty($querier, 'resourceTypes');
        $property->setAccessible(true);
        $property->setValue($querier, ['items', 'concepts']);

        $values = $querier->queryValues('dcterms:title', 'Concept as');

        $this->assertContains('Concept as item', $values);
    }
}
