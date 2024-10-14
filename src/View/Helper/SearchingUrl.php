<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Exception;
use Laminas\View\Helper\AbstractHelper;

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

        $searchMainConfig = $isSiteRequest
            ? $view->siteSetting('advancedsearch_main_config')
            : $view->setting('advancedsearch_main_config');

        if ($searchMainConfig) {
            try {
                /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
                $searchConfig = $view->api()->read('search_configs', [is_numeric($searchMainConfig) ? 'id' : 'slug' => $searchMainConfig])->getContent();
                return $view->url('search-page-' . $searchConfig->slug(), [], $options, true);
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $view->logger()->err(
                    'Search engine #{search_config} does not exist.', // @translate
                    ['search_config' => $searchMainConfig]
                );
            } catch (Exception $e) {
                $view->logger()->err(
                    'Search engine {name} (#{search_config}) is not available.', // @translate
                    ['name' => $searchConfig->name(), 'search_config' => $searchConfig->id()]
                );
            }
        }

        return $view->url($isSiteRequest ? 'site/resource' : 'admin/default', ['controller' => 'item', 'action' => $useItemSearch ? 'search' : 'browse'], $options, true);
    }
}
