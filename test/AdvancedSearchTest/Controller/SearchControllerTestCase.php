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

        $this->setupTestEngineAdapter();

        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestIndex',
            'o:engine_adapter' => 'test',
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
            'o:search_engine' => [
                'o:id' => $searchEngine->id(),
            ],
            'o:form_adapter' => 'basic',
            'o:settings' => [
                'request' => [],
                'q' => [],
                'index' => [],
                'form' => [],
                'results' => [],
                'facet' => [],
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

    protected function setupTestEngineAdapter(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $engineAdapterManager = $services->get('AdvancedSearch\EngineAdapterManager');
        $config = [
            'invokables' => [
                'test' => \AdvancedSearch\Test\EngineAdapter\TestEnginerAdapter::class,
            ],
        ];
        $engineAdapterManager->configure($config);
    }

    protected function resetApplication(): void
    {
        parent::resetApplication();

        $this->setupTestEngineAdapter();
    }
}
