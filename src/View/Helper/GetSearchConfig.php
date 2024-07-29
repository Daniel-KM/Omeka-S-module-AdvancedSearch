<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use Laminas\View\Helper\AbstractHelper;

class GetSearchConfig extends AbstractHelper
{
    /**
     * Check and get a search config or get the default one.
     *
     * The search config should be available in the current site or in admin.
     */
    public function __invoke($searchConfigIdOrSlug = null): ?SearchConfigRepresentation
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $isSiteRequest = $plugins->get('status')->isSiteRequest();
        $setting = $plugins->get($isSiteRequest ? 'siteSetting' : 'setting');

        if (empty($searchConfigIdOrSlug)) {
            $searchConfigIdOrSlug = $setting('advancedsearch_main_config');
            if (!$searchConfigIdOrSlug) {
                return null;
            }
        }

        $isNumeric = is_numeric($searchConfigIdOrSlug);

        // Quick check: no check on slug here.
        $available = $setting('advancedsearch_configs', []);
        if ($isNumeric && !in_array($searchConfigIdOrSlug, $available)) {
            return null;
        }

        $api = $plugins->get('api');
        try {
            $searchConfig = $api->read('search_configs', [$isNumeric ? 'id' : 'slug' => $searchConfigIdOrSlug])->getContent();
        } catch (\Omeka\Mvc\Exception\NotFoundException $e) {
            return null;
        }

        $searchConfigIdOrSlug = $searchConfig->id();
        return $isNumeric || in_array($searchConfig->id(), $available)
            ? $searchConfig
            : null;
    }
}
