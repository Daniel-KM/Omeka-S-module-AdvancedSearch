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
    public function __invoke($searchConfigIdOrPath = null): ?SearchConfigRepresentation
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $isSiteRequest = $plugins->get('status')->isSiteRequest();
        $setting = $plugins->get($isSiteRequest ? 'siteSetting' : 'setting');

        if (empty($searchConfigIdOrPath)) {
            $searchConfigIdOrPath = $setting('advancedsearch_main_config');
            if (!$searchConfigIdOrPath) {
                return null;
            }
        }

        $isNumeric = is_numeric($searchConfigIdOrPath);

        // Quick check: no check on path here.
        $available = $setting('advancedsearch_configs', []);
        if ($isNumeric && !in_array($searchConfigIdOrPath, $available)) {
            return null;
        }

        $api = $plugins->get('api');
        try {
            $searchConfig = $api->read('search_configs', [$isNumeric ? 'id' : 'path' => $searchConfigIdOrPath])->getContent();
        } catch (\Omeka\Mvc\Exception\NotFoundException $e) {
            return null;
        }

        $searchConfigIdOrPath = $searchConfig->id();
        return $isNumeric || in_array($searchConfig->id(), $available)
            ? $searchConfig
            : null;
    }
}
