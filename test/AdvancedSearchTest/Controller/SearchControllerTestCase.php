<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class SearchControllerTestCase extends OmekaControllerTestCase
{
    protected $searchEngine;
    protected $searchConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();

        $this->setupTestSearchAdapter();

        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestIndex',
            'o:adapter' => 'test',
            'o:settings' => [
                'resource_types' => [
                    'items',
                    'item_sets',
                ],
            ],
        ]);
        $searchEngine = $response->getContent();
        $response = $this->api()->create('search_configs', [
            'o:name' => 'TestPage',
            'o:slug' => 'test/search',
            'o:engine' => $searchEngine->id(),
            'o:form' => 'basic',
            'o:settings' => [
                'facets' => [],
                'sort_fields' => [],
            ],
        ]);
        $searchConfig = $response->getContent();

        $this->searchEngine = $searchEngine;
        $this->searchConfig = $searchConfig;
    }

    public function tearDown(): void
    {
        $this->api()->delete('search_configs', $this->searchConfig->id());
        $this->api()->delete('search_engines', $this->searchEngine->id());
    }

    protected function setupTestSearchAdapter(): void
    {
        $serviceLocator = $this->getApplication()->getServiceManager();
        $adapterManager = $serviceLocator->get('AdvancedSearch\AdapterManager');
        $config = [
            'invokables' => [
                'test' => \AdvancedSearch\Test\Adapter\TestAdapter::class,
            ],
        ];
        $adapterManager->configure($config);
    }

    protected function resetApplication(): void
    {
        parent::resetApplication();

        $this->setupTestSearchAdapter();
    }
}
