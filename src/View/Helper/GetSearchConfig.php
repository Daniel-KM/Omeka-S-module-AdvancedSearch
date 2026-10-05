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
    public function __invoke($searchConfigIdOrSlug = null, ?string $resourceName = null): ?SearchConfigRepresentation
    {
        // Most of the time, only the current main search config is stored.
        static $searchConfigs = [];

        // The context is part of the key: the same call gives another config in
        // admin, in a site, and in another context, for example during the
        // routing of a module (ark, identifier, etc.), where the site is not
        // known yet.
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $status = $plugins->get('status');
        $context = $status->isAdminRequest()
            ? 'admin'
            : ($status->isSiteRequest() ? 'site' : 'other');

        $cacheKey = $context . '/' . $searchConfigIdOrSlug . '/' . $resourceName;

        if (array_key_exists($cacheKey, $searchConfigs)) {
            return $searchConfigs[$cacheKey];
        }

        // If the site settings are not ready, get the default site one.
        // The try/catch avoids issue when the helper is called before the site
        // setting target is set.

        $isSiteRequest = $status->isSiteRequest();
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
            'digital_objects' => 'advancedsearch_digital_objects_config',
        ];

        $originalCacheKey = $cacheKey;

        if (empty($searchConfigIdOrSlug)) {
            if ($isSiteRequest) {
                $configKey = $configKeys[$resourceName] ?? 'advancedsearch_main_config';
                try {
                    $searchConfigIdOrSlug = $siteSetting($configKey);
                } catch (\Throwable $e) {
                    $defaultSiteId = $plugins->get('defaultSite')('id');
                    $searchConfigIdOrSlug = $siteSetting($configKey, null, $defaultSiteId);
                }
            } elseif ($status->isAdminRequest()) {
                // A page available only in admin can be set for the admin side
                // bar, so it is never used by a site, that has no route for it.
                $searchConfigIdOrSlug = $setting('advancedsearch_admin_config')
                    ?: $setting('advancedsearch_main_config');
            } else {
                // The context is unknown: no site route is matched, but the
                // page may be rendered by the theme of a site, for example on
                // the route of a module (ark, identifier, etc.) or on an error
                // page. So the global setting is used and it must be a page
                // available in the sites.
                $searchConfigIdOrSlug = $setting('advancedsearch_main_config');
            }
            if (!$searchConfigIdOrSlug) {
                $searchConfigs[$cacheKey] = null;
                return null;
            }
            $cacheKey = $context . '/' . $searchConfigIdOrSlug . '/' . $resourceName;
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
            } catch (\Throwable $e) {
                // When Common is upgrading, DefaultSite is not available, and
                // many features of the module anyway.
                if (!$plugins->has('defaultSite')) {
                    return null;
                }
                $defaultSiteId = $plugins->get('defaultSite')('id');
                if (!$defaultSiteId) {
                    return null;
                }
                $availables = $siteSetting('advancedsearch_configs', [], $defaultSiteId);
            } catch (\Throwable $e) {
                return null;
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
        } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
            // The search config may be unavailable during a rest api request.
            return null;
        }

        return $searchConfigs[$cacheKey];
    }
}
