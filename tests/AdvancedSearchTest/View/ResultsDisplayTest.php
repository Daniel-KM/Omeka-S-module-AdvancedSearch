<?php declare(strict_types=1);

namespace AdvancedSearchTest\View;

use AdvancedSearch\Query;
use AdvancedSearch\Response;
use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The display mode of the results: class of the list and buttons to switch.
 *
 * @group view
 */
class ResultsDisplayTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var \Omeka\Api\Representation\ItemRepresentation
     */
    protected $item;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->item = $this->createItem([
            'dcterms:title' => [['@value' => 'Result display']],
        ]);
    }

    public function tearDown(): void
    {
        if ($this->searchConfig) {
            $this->api()->delete('search_configs', $this->searchConfig->id());
        }
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    public function testListClassFollowsTheMode(): void
    {
        $expected = [
            'auto' => 'resource-list search-result-list grid',
            'grid' => 'resource-list search-result-list grid',
            'list' => 'resource-list search-result-list list',
            'list_only' => 'resource-list search-result-list list only-mode',
            'grid_only' => 'resource-list search-result-list grid only-mode',
        ];
        foreach ($expected as $mode => $class) {
            $html = $this->render('search/results', $mode, ['resourceType' => 'items']);
            $this->assertStringContainsString('<ul class="' . $class . '">', $html, $mode);
            $this->assertStringContainsString('Result display', $html, $mode);
        }
    }

    public function testViewTypesAreButtons(): void
    {
        $html = $this->render('search/results-header-footer', 'list', [
            'resultPart' => 'header',
            'displayParts' => ['grid_list' => 'header'],
            'paginationPerPages' => [],
            'sortFields' => [],
        ]);
        $this->assertStringContainsString('<button type="button" class="search-view-type search-view-type-list active" aria-pressed="true">', $html);
        $this->assertStringContainsString('<button type="button" class="search-view-type search-view-type-grid" aria-pressed="false">', $html);
        $this->assertStringNotContainsString('<a class="search-view-type', $html);
    }

    public function testNoViewTypesWithSingleMode(): void
    {
        $html = $this->render('search/results-header-footer', 'list_only', [
            'resultPart' => 'header',
            'displayParts' => ['grid_list' => 'header'],
            'paginationPerPages' => [],
            'sortFields' => [],
        ]);
        $this->assertStringNotContainsString('search-view-type', $html);
    }

    protected function render(string $template, string $gridListMode, array $variables): string
    {
        if ($this->searchConfig) {
            $this->api()->delete('search_configs', $this->searchConfig->id());
        }
        $this->searchConfig = $this->api()->create('search_configs', [
            'o:name' => 'Results display',
            'o:slug' => 'results-display',
            'o:search_engine' => ['o:id' => $this->internalSearchEngine()->id()],
            'o:form_adapter' => 'main',
            'o:settings' => ['results' => ['grid_list_mode' => $gridListMode]],
        ])->getContent();

        $services = $this->getServiceLocator();
        $response = (new Response())
            ->setApi($services->get('Omeka\ApiManager'))
            ->setResourcesForType('items', [$this->item]);
        return $services->get('ViewRenderer')->partial($template, $variables + [
            'site' => null,
            'searchConfig' => $this->searchConfig,
            'query' => new Query(),
            'response' => $response,
            'itemSet' => null,
        ]);
    }
}
