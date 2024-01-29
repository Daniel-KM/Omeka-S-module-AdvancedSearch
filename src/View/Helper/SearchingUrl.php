<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Stdlib\Message;

class SearchingUrl extends AbstractHelper
{
    /**
     * Get url to the search page of current site or the default search page.
     *
     * @param bool $useItemSearch Use item/search instead of item/browse when
     *   there is no search config.
     * @param array $options Url options, like "query" and "force_canonical".
     * @return string
     */
    public function __invoke($useItemSearch = false, array $options = []): string
    {
        $view = $this->getView();

        // Check if the current site/admin has a search form.
        $isSiteRequest = $view->status()->isSiteRequest();
        $searchMainPage = $isSiteRequest
            ? $view->siteSetting('advancedsearch_main_config')
            : $view->setting('advancedsearch_main_config');
        if ($searchMainPage) {
            /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
            $searchConfig = $view->api()->searchOne('search_configs', ['id' => $searchMainPage])->getContent();
            if ($searchConfig) {
                try {
                    return $view->url('search-page-' . $searchConfig->id(), [], $options, true);
                } catch (\Exception $e) {
                    $this->getView()->logger()->err(
                        'Search engine {name} (#{search_config_id}) is not available.', // @translate
                        ['name' => $searchConfig->name(), 'search_config_id' => $searchConfig->id()]
                    );
                }
            }
        }

        return $view->url($isSiteRequest ? 'site/resource' : 'admin/default', ['controller' => 'item', 'action' => $useItemSearch ? 'search' : 'browse'], $options, true);
    }
}
