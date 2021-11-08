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
            $this->view->searchConfig = $this->view->api()
            ->read('search_configs', ['id' => $this->view->params()->fromRoute('id')])
            ->getContent();
        }
        return $this->view->searchConfig;
    }

    protected function getAvailableFacetFields(): array
    {
        static $availableFacetFields;
        if (!isset($availableFacetFields)) {
            $engine = $this->getSearchConfig()->engine();
            $availableFacetFields = $engine->adapter()
                ->getAvailableFacetFields($engine);
        }
        return $availableFacetFields;
    }
}
