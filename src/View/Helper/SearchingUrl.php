<?php
namespace Search\View\Helper;

use Zend\View\Helper\AbstractHelper;

class SearchingUrl extends AbstractHelper
{
    /**
     * Get url to the search page of current site or the default search page.
     *
     * @param bool $useItemSearch Use item/search instead of item/browse when
     *   there is no search page.
     * @return string
     */
    public function __invoke($useItemSearch = false)
    {
        $view = $this->getView();

        // Check if the current site has a search form.
        $searchMainPage = $view->siteSetting('search_main_page');
        if ($searchMainPage) {
            /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
            $searchPage = $view->api()->searchOne('search_pages', ['id' => $searchMainPage])->getContent();
            if ($searchPage) {
                return $searchPage->siteUrl();
            }
        }

        return $view->url('site/resource', ['controller' => 'item', 'action' => $useItemSearch ? 'search' : 'browse'], true);
    }
}
