<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SearchingForm extends AbstractHelper
{
    /**
     * Display the search form if any, else display the standard form.
     *
     * @uses \AdvancedSearch\View\Helper\SearchForm
     */
    public function __invoke(?string $searchFormPartial = null, bool $skipFormAction = false): string
    {
        $view = $this->getView();

        // Check if the current site has a search form.
        $searchMainPage = $view->siteSetting('advancedsearch_main_config');
        if ($searchMainPage) {
            /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
            $searchConfig = $view->api()->searchOne('search_configs', ['id' => $searchMainPage])->getContent();
            if ($searchConfig) {
                return (string) $view->searchForm($searchConfig, $searchFormPartial, $skipFormAction);
            }
        }

        // Standard search form.
        return '<div id="search">' . $view->partial('common/search-form') . '</div>';
    }
}
