<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use Laminas\View\Helper\AbstractHelper;

class SearchConfigCurrent extends AbstractHelper
{
    /**
     * Get the search config of the current site or admin.
     */
    public function __invoke(): ?SearchConfigRepresentation
    {
        $view = $this->getView();

        // Check if the current site/admin has a search form.
        $isSiteRequest = $view->status()->isSiteRequest();
        $searchMainConfig = $isSiteRequest
            ? (int) $view->siteSetting('advancedsearch_main_config')
            : (int) $view->setting('advancedsearch_main_config');
        if ($searchMainConfig) {
            try {
                return $view->api()->read('search_configs', ['id' => $searchMainConfig])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $view->logger()->err(
                    'Search engine #{search_config_id} does not exist.', // @translate
                    ['search_config_id' => $searchMainConfig]
                );
                return null;
            }
        }
        return null;
    }
}
