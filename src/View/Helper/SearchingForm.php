<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SearchingForm extends AbstractHelper
{
    /**
     * Display the search form if any, else display the standard form.
     *
     * @param string $searchFormPartial Specific partial for the search form.
     * @param bool $skipFormAction
     * @return string
     */
    public function __invoke($searchFormPartial = null, $skipFormAction = false)
    {
        $view = $this->getView();

        // Check if the current site has a search form.
        $searchMainPage = $view->siteSetting('search_main_page');
        if ($searchMainPage) {
            /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
            $searchPage = $view->api()->searchOne('search_pages', ['id' => $searchMainPage])->getContent();
            if ($searchPage) {
                return (string) $view->searchForm($searchPage, $searchFormPartial, $skipFormAction);
            }
        }

        // Standard search form.
        return '<div id="search">' . $view->partial('common/search-form') . '</div>';
    }
}
