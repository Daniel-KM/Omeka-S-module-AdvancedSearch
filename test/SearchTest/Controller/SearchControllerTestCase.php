<?php declare(strict_types=1);

namespace SearchTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class SearchControllerTestCase extends OmekaControllerTestCase
{
    protected $searchIndex;
    protected $searchPage;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();

        $this->setupTestSearchAdapter();

        $response = $this->api()->create('search_indexes', [
            'o:name' => 'TestIndex',
            'o:adapter' => 'test',
            'o:settings' => [
                'resources' => [
                    'items',
                    'item_sets',
                ],
            ],
        ]);
        $searchIndex = $response->getContent();
        $response = $this->api()->create('search_pages', [
            'o:name' => 'TestPage',
            'o:path' => 'test/search',
            'o:index_id' => $searchIndex->id(),
            'o:form' => 'basic',
            'o:settings' => [
                'facets' => [],
                'sort_fields' => [],
            ],
        ]);
        $searchPage = $response->getContent();

        $this->searchIndex = $searchIndex;
        $this->searchPage = $searchPage;
    }

    public function tearDown(): void
    {
        $this->api()->delete('search_pages', $this->searchPage->id());
        $this->api()->delete('search_indexes', $this->searchIndex->id());
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
