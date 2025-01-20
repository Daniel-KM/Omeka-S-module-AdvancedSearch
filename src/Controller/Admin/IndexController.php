<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2020-2025
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace AdvancedSearch\Controller\Admin;

use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function browseAction()
    {
        $this->warnOverriddenSearch();

        $api = $this->api();
        $searchEngines = $api->search('search_engines', ['sort_by' => 'name'])->getContent();
        $searchConfigs = $api->search('search_configs', ['sort_by' => 'name'])->getContent();
        $suggesters = $api->search('search_suggesters', ['sort_by' => 'name'])->getContent();

        $this->updateListSearchSlugs($searchConfigs);

        return new ViewModel([
            'searchEngines' => $searchEngines,
            'searchConfigs' => $searchConfigs,
            'suggesters' => $suggesters,
        ]);
    }

    /**
     * Store all slugs in settings.
     *
     * This setting "advancedsearch_all_configs" simplifies settings management.
     */
    protected function updateListSearchSlugs(array $searchConfigs): void
    {
        $searchConfigSlugs = [];
        foreach ($searchConfigs as $searchConfig) {
            $searchConfigSlugs[$searchConfig->id()] = $searchConfig->slug();
        }
        $this->settings()->set('advancedsearch_all_configs', $searchConfigSlugs);
    }

    /**
     * Adapted:
     * @see \AdvancedSearch\Module::warnOverriddenSearch()
     * @see \AdvancedSearch\Controller\Admin\IndexController::warnOverriddenSearch()
     *
     * @todo Identify modules, blocks and queries that use old features.
     */
    protected function warnOverriddenSearch(): bool
    {
        $api = $this->plugins->get('api');
        $logger = $this->plugins->get('logger')();
        $settings = $this->plugins->get('settings')();
        $siteSettings = $this->plugins->get('siteSettings')();
        $messenger = $this->plugins->get('messenger');

        $settingKeys = [
            'advancedsearch_property_improved'
                => 'The setting to override search element "properties" is enabled. This feature is deprecated and will be removed in a future version. All improved queries should be replaced by the equivalent filter queries. Check your pages and settings. Matching sites: {json}', // @translate
            /*
            'advancedsearch_metadata_improved'
                => 'The setting to override search resource metadata is enabled to allow to search resources without owner, class, template or item set. This feature is deprecated and will be removed in a future version. All improved queries should be replaced by the equivalent filter meta queries. Check your pages and settings. Matching sites: {json}', // @translate
            'advancedsearch_media_type_improved'
                => 'The setting to override search element "media type" is enabled to allow to search main and multiple media-types. This feature is deprecated and will be removed in a future version. All improved queries should be replaced by the equivalent filter meta queries. Check your pages and settings. Matching sites: {json}', // @translate
            */
        ];

        foreach ($settingKeys as $settingKey => $settingMessage) {
            $results = [];
            if ($settings->get($settingKey)) {
                $results[0] = 'admin';
            }

            $siteSlugs = $api->search('sites', [], ['returnScalar' => 'slug'])->getContent();
            foreach ($siteSlugs as $siteId => $siteSlug) {
                $siteSettings->setTargetId($siteId);
                if ($siteSettings->get($settingKey)) {
                    $results[$siteId] = $siteSlug;
                }
            }

            if (!count($results)) {
                return false;
            }

            $message = new PsrMessage($settingMessage, ['json' => json_encode($results, 448)]);
            $logger->warn($message->getMessage(), $message->getContext());
            $messenger->addWarning($message);
        }

        return true;
    }
}
