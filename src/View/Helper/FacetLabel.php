<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class FacetLabel extends AbstractHelper
{
    public function __invoke($name): string
    {
        $settings = $this->getSearchConfig()->settings();
        if (!empty($settings['facet']['facets'][$name]['label'])) {
            return $settings['facet']['facets'][$name]['label'];
        }
        return $this->getAvailableFacetFields()[$name]['label'] ?? $name;
    }

    protected function getSearchConfig(): \AdvancedSearch\Api\Representation\SearchConfigRepresentation
    {
        if (!property_exists($this->view, 'searchConfig')) {
            if (property_exists($this->view, 'searchPage')) {
                $this->view->searchConfig = $this->view->searchPage;
            } else {
                $id = $this->view->params()->fromRoute('id');
                if (!$id) {
                    if ($this->view->status()->isSiteRequest()) {
                        $id = $this->view->siteSetting('advancedsearch_main_config')
                            ?: $this->view->setting('advancedsearch_main_config', 1);
                    } else {
                        $id = $this->view->setting('advancedsearch_main_config', 1);
                    }
                }
                $this->view->searchConfig = $this->view->api()
                    ->read('search_configs', ['id' => $id])
                    ->getContent();
            }
        }
        return $this->view->searchConfig;
    }

    protected function getAvailableFacetFields(): array
    {
        static $availableFacetFields;
        if (!isset($availableFacetFields)) {
            $availableFacetFields = [];
            $searchEngine = $this->getSearchConfig()->engine();
            if ($searchEngine) {
                $adapter = $searchEngine->adapter();
                if ($adapter) {
                    $availableFacetFields = $adapter
                        ->setSearchEngine($searchEngine)
                        ->getAvailableFacetFields();
                }
            }
        }
        return $availableFacetFields;
    }
}
