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
        $setting = $plugins->get('setting');
        $siteSetting = $plugins->get('siteSetting');

        if (empty($searchConfigIdOrSlug)) {
            $searchConfigIdOrSlug = $isSiteRequest
                ? $siteSetting('advancedsearch_main_config')
                : $setting('advancedsearch_main_config');
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
            return null;
        }

        $api = $plugins->get('api');
        try {
            return $api->read('search_configs', [$isNumeric ? 'id' : 'slug' => $searchConfigIdOrSlug])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return null;
        }
    }
}
