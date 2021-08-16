<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class SearchControllerTestCase extends OmekaControllerTestCase
{
    protected $searchEngine;
    protected $searchPage;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();

        $this->setupTestSearchAdapter();

        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestIndex',
            'o:adapter' => 'test',
            'o:settings' => [
                'resources' => [
                    'items',
                    'item_sets',
                ],
            ],
        ]);
        $searchEngine = $response->getContent();
        $response = $this->api()->create('search_pages', [
            'o:name' => 'TestPage',
            'o:path' => 'test/search',
            'o:index_id' => $searchEngine->id(),
            'o:form' => 'basic',
            'o:settings' => [
                'facets' => [],
                'sort_fields' => [],
            ],
        ]);
        $searchPage = $response->getContent();

        $this->searchEngine = $searchEngine;
        $this->searchPage = $searchPage;
    }

    public function tearDown(): void
    {
        $this->api()->delete('search_pages', $this->searchPage->id());
        $this->api()->delete('search_engines', $this->searchEngine->id());
    }

    protected function setupTestSearchAdapter(): void
    {
        $serviceLocator = $this->getApplication()->getServiceManager();
        $adapterManager = $serviceLocator->get('Search\AdapterManager');
        $config = [
            'invokables' => [
                'test' => \Search\Test\Adapter\TestAdapter::class,
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
