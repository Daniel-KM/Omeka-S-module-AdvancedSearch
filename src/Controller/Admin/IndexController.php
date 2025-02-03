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
        $settings = $this->plugins->get('settings')();
        $siteSettings = $this->plugins->get('siteSettings')();
        $messenger = $this->plugins->get('messenger');

        /*
        $improvedTemplates = [
            'common/advanced-search/properties-improved'
            'common/advanced-search/resource-class-improved',
            'common/advanced-search/resource-template-improved',
            'common/advanced-search/item-sets-improved',
            'common/advanced-search/site-improved',
            'common/advanced-search/media-type-improved',
            'common/advanced-search/owner-improved',
        ];
        */

        $results = [];
        $searchFields = $settings->get('advancedsearch_search_fields') ?: [];
        // foreach ($searchFields as $searchField) {
        //     if (substr($searchField, -9) === '-improved') {
        //         $results[0] = 'admin';
        //         break;
        //     }
        // }
        if (in_array('common/advanced-search/properties-improved', $searchFields)) {
            $results[0] = 'admin';
        }

        $siteSlugs = $api->search('sites', [], ['returnScalar' => 'slug'])->getContent();
        foreach ($siteSlugs as $siteId => $siteSlug) {
            $siteSettings->setTargetId($siteId);
            $searchFields = $siteSettings->get('advancedsearch_search_fields') ?: [];
            // foreach ($searchFields as $searchField) {
            //     if (substr($searchField, -9) === '-improved') {
            //         $results[$siteId] = $siteSlug;
            //         break;
            //     }
            // }
            if (in_array('common/advanced-search/properties-improved', $searchFields)) {
                $results[$siteId] = $siteSlug;
            }
        }

        if (!count($results)) {
            return false;
        }

        $message = new PsrMessage(
            'The setting to override search element "property" is enabled. This feature will be removed in a future version and should be {link}replaced by the search element "filter"{link_end}. Check your pages and settings. Matching sites: {json}', // @translate
            ['link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch#deprecated-improvements-of-the-advanced-search-elements" target="_blank" rel="noopener">', 'link_end' => '</a>', 'json' => json_encode($results, 448)]
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);

        return true;
    }
}
