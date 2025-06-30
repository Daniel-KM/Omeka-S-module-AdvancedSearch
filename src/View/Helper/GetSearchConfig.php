<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use Laminas\View\Helper\AbstractHelper;

class GetSearchConfig extends AbstractHelper
{
    /**
     * Check and get main or resource search config or the current one.
     *
     * The search config should be available in the current site or in admin.
     */
    public function __invoke($searchConfigIdOrSlug = null, string $resourceName = null): ?SearchConfigRepresentation
    {
        // Most of the time, only the current main search config is stored.
        static $searchConfigs = [];

        $cacheKey = $searchConfigIdOrSlug . '/' . $resourceName;

        if (array_key_exists($cacheKey, $searchConfigs)) {
            return $searchConfigs[$cacheKey];
        }

        // If the site settings are not ready, get the default site one.
        // The try/catch avoids issue when the helper is called before the site
        // setting target is set.

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $isSiteRequest = $plugins->get('status')->isSiteRequest();
        $setting = $plugins->get('setting');
        $siteSetting = $plugins->get('siteSetting');

        $configKeys = [
            '' => 'advancedsearch_main_config',
            'resources' => 'advancedsearch_main_config',
            'items' => 'advancedsearch_items_config',
            'media' => 'advancedsearch_media_config',
            'item_sets' => 'advancedsearch_item_sets_config',
            'annotations' => 'advancedsearch_annotations_config',
            'value_annotations' => 'advancedsearch_value_annotations_config',
        ];

        $originalCacheKey = $cacheKey;

        if (empty($searchConfigIdOrSlug)) {
            if ($isSiteRequest) {
                $configKey = $configKeys[$searchConfigIdOrSlug] ?? 'advancedsearch_main_config';
                try {
                    $searchConfigIdOrSlug = $siteSetting($configKey);
                } catch (\Exception $e) {
                    $defaultSiteId = $plugins->get('defaultSite')('id');
                    $searchConfigIdOrSlug = $siteSetting($configKey, null, $defaultSiteId);
                }
            } else {
                $searchConfigIdOrSlug = $setting('advancedsearch_main_config');
            }
            if (!$searchConfigIdOrSlug) {
                $searchConfigs[$cacheKey] = null;
                return null;
            }
            $cacheKey = $searchConfigIdOrSlug . '/' . $resourceName;
        }

        // Don't set it early because the cache key may have changed.
        $searchConfigs[$originalCacheKey] = null;
        $searchConfigs[$cacheKey] = null;

        $isNumeric = is_numeric($searchConfigIdOrSlug);

        // All configs are stored in a setting, so quick check it before read.
        $allConfigs = $setting('advancedsearch_all_configs', []);

        // All configs are available in admin, not in sites.
        if ($isSiteRequest) {
            try {
                $availables = $siteSetting('advancedsearch_configs', []);
            } catch (\Exception $e) {
                $defaultSiteId = $plugins->get('defaultSite')('id');
                $availables = $siteSetting('advancedsearch_configs', [], $defaultSiteId);
            }
            $allConfigs = array_intersect_key($allConfigs, array_flip($availables));
        }
        if (($isNumeric && !isset($allConfigs[$searchConfigIdOrSlug]))
            || (!$isNumeric && !in_array($searchConfigIdOrSlug, $allConfigs))
        ) {
            return null;
        }

        $api = $plugins->get('api');
        try {
            $searchConfigs[$cacheKey] = $api
                ->read('search_configs', $isNumeric ? ['id' => $searchConfigIdOrSlug] : ['slug' => $searchConfigIdOrSlug])
                ->getContent();
            $searchConfigs[$originalCacheKey] = $searchConfigs[$cacheKey];
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return null;
        }

        return $searchConfigs[$cacheKey];
    }
}
