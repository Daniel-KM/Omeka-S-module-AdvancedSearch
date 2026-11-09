<?php declare(strict_types=1);

namespace AdvancedSearchTest\FormAdapter;

use AdvancedSearch\Form\Element\MultiText;
use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * A filter "MultiText" is a repeatable text input searching the exact values.
 *
 * @group form-adapter
 */
class MultiTextTest extends AbstractHttpControllerTestCase
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
            'o:name' => 'TestMultiText',
            'o:slug' => 'test/multi-text',
            'o:search_engine' => ['o:id' => $searchEngine->id()],
            'o:form_adapter' => 'main',
            'o:settings' => [
                'request' => [],
                'q' => [],
                'index' => ['aliases' => []],
                'form' => ['filters' => [
                    'subject' => [
                        'field' => 'subject',
                        'label' => 'Subject',
                        'type' => 'MultiText',
                    ],
                ]],
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

    public function testFilterIsARepeatableTextInput(): void
    {
        $form = $this->searchConfig->form();
        $this->assertTrue($form->has('subject'));
        $this->assertInstanceOf(MultiText::class, $form->get('subject'));
    }

    public function testValuesAreSearchedExactly(): void
    {
        $query = $this->searchConfig->formAdapter()->toQuery(['subject' => ['Paris', 'Lyon']], [
            'available_fields' => [],
            'facet' => [],
            'advanced' => [],
            'aliases' => [],
            'fields_query_args' => [],
            'request' => [],
        ]);

        $filters = $query->getFiltersQuery()['subject'] ?? [];
        $this->assertSame(['Paris', 'Lyon'], array_column($filters, 'val'));
        $this->assertSame(['eq', 'eq'], array_column($filters, 'type'));
    }
}
