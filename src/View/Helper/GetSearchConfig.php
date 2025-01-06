<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use Laminas\View\Helper\AbstractHelper;

class GetSearchConfig extends AbstractHelper
{
    /**
     * Check and get a search config or get the current one.
     *
     * The search config should be available in the current site or in admin.
     */
    public function __invoke($searchConfigIdOrSlug = null): ?SearchConfigRepresentation
    {
        // Most of the time, only the current main search config is stored.
        static $searchConfigs = [];

        if (array_key_exists($searchConfigIdOrSlug, $searchConfigs)) {
            return $searchConfigs[$searchConfigIdOrSlug];
        }

        $plugins = $this->getView()->getHelperPluginManager();
        $isSiteRequest = $plugins->get('status')->isSiteRequest();
        $setting = $plugins->get('setting');
        $siteSetting = $plugins->get('siteSetting');

        $originalSearchConfigIdOrSlug = $searchConfigIdOrSlug;

        if (empty($searchConfigIdOrSlug)) {
            $searchConfigIdOrSlug = $isSiteRequest
                ? $siteSetting('advancedsearch_main_config')
                : $setting('advancedsearch_main_config');
            $searchConfigs[$originalSearchConfigIdOrSlug] = null;
            if (!$searchConfigIdOrSlug) {
                return null;
            }
        }

        $isNumeric = is_numeric($searchConfigIdOrSlug);

        // All configs are stored in a setting, so quick check it before read.
        $allConfigs = $setting('advancedsearch_all_configs', []);

        // All configs are available in admin, not in sites.
        if ($isSiteRequest) {
            $availables = $siteSetting('advancedsearch_configs', []);
            $allConfigs = array_intersect_key($allConfigs, array_flip($availables));
        }
        if (($isNumeric && !isset($allConfigs[$searchConfigIdOrSlug]))
            || (!$isNumeric && !in_array($searchConfigIdOrSlug, $allConfigs))
        ) {
            $searchConfigs[$originalSearchConfigIdOrSlug] = null;
            return null;
        }

        $api = $plugins->get('api');
        try {
            $searchConfigs[$originalSearchConfigIdOrSlug] = $api
                ->read('search_configs', [$isNumeric ? 'id' : 'slug' => $searchConfigIdOrSlug])
                ->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $searchConfigs[$originalSearchConfigIdOrSlug] = null;
        }

        return $searchConfigs[$originalSearchConfigIdOrSlug];
    }
}
