<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SearchingForm extends AbstractHelper
{
    /**
     * Display the search form if any, else display the standard form.
     *
     * @deprecated since 3.4.9. Use GetSearchConfig() and SearchForm().
     *
     * @uses \AdvancedSearch\View\Helper\GetSearchConfig
     * @uses \AdvancedSearch\View\Helper\SearchForm
     */
    public function __invoke(?string $searchFormPartial = null, bool $skipFormAction = false): string
    {
        $view = $this->getView();

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $view->getSearchConfig();
        if ($searchConfig) {
            return (string) $view->searchForm($searchConfig, $searchFormPartial, $skipFormAction);
        }

        // Standard search form.
        return '<div id="search">' . $view->partial('common/search-form') . '</div>';
    }
}
