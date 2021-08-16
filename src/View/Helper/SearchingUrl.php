<?php declare(strict_types=1);
namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SearchingUrl extends AbstractHelper
{
    /**
     * Get url to the search page of current site or the default search page.
     *
     * @param bool $useItemSearch Use item/search instead of item/browse when
     *   there is no search config.
     * @return string
     */
    public function __invoke($useItemSearch = false)
    {
        $view = $this->getView();

        // Check if the current site has a search form.
        $searchMainPage = $view->siteSetting('advancedsearch_main_config');
        if ($searchMainPage) {
            /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
            $searchConfig = $view->api()->searchOne('search_configs', ['id' => $searchMainPage])->getContent();
            if ($searchConfig) {
                return $searchConfig->siteUrl();
            }
        }

        return $view->url('site/resource', ['controller' => 'item', 'action' => $useItemSearch ? 'search' : 'browse'], true);
    }
}
