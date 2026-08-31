<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2020-2026
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
        $api = $this->api();
        $searchEngines = $api->search('search_engines', ['sort_by' => 'name'])->getContent();
        $searchConfigs = $api->search('search_configs', ['sort_by' => 'name'])->getContent();
        $suggesters = $api->search('search_suggesters', ['sort_by' => 'name'])->getContent();

        $this->updateListSearchSlugs($searchConfigs);

        $runningJobs = $this->listRunningSearchJobs();

        return new ViewModel([
            'searchEngines' => $searchEngines,
            'searchConfigs' => $searchConfigs,
            'suggesters' => $suggesters,
            'runningJobs' => $runningJobs,
            // The default role(s) of each config in the admin board and the
            // sites exposing it, so the search pages table shows where each
            // page is actually used.
            'searchConfigDefaults' => $this->listSearchConfigDefaults(),
            'searchConfigSites' => $this->listSearchConfigSites(),
            'searchConfigSiteDefaults' => $this->listSearchConfigSiteDefaults(),
        ]);
    }

    /**
     * Map each config id to the admin roles it is the default for.
     *
     * @return array [config_id => ['admin', 'items', …]]
     */
    protected function listSearchConfigDefaults(): array
    {
        $settings = $this->settings();
        $keys = [
            'advancedsearch_main_config' => 'admin', // @translate
            'advancedsearch_items_config' => 'items', // @translate
            'advancedsearch_media_config' => 'media', // @translate
            'advancedsearch_item_sets_config' => 'item sets', // @translate
            'advancedsearch_api_config' => 'api', // @translate
        ];
        $result = [];
        foreach ($keys as $key => $role) {
            $configId = (int) $settings->get($key);
            if (!$configId) {
                continue;
            }
            // The api uses the external index only, so tell what it really
            // does: the setting alone does not say it.
            if ($key === 'advancedsearch_api_config') {
                try {
                    $searchConfig = $this->api()->read('search_configs', ['id' => $configId])->getContent();
                    $role = $searchConfig->hasExternalIndex()
                        ? 'api (index)' // @translate
                        : 'api (database)'; // @translate
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    // Keep the generic role.
                }
            }
            $result[$configId][] = $role;
        }
        return $result;
    }

    /**
     * Map each config id to the slugs of the sites exposing it.
     *
     * @return array [config_id => ['site-slug', …]]
     */
    protected function listSearchConfigSites(): array
    {
        $services = $this->getEvent()->getApplication()->getServiceManager();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $sites = $this->api()->search('sites', [], ['returnScalar' => 'slug'])->getContent();
        $result = [];
        foreach ($sites as $siteId => $slug) {
            $siteSettings->setTargetId($siteId);
            $configIds = $siteSettings->get('advancedsearch_configs', []) ?: [];
            foreach ($configIds as $configId) {
                $result[(int) $configId][] = $slug;
            }
        }
        return $result;
    }

    /**
     * Map each config id to the slugs of the sites using it by default.
     *
     * A site may expose many search pages, but only one is its default one.
     *
     * @return array [config_id => ['site-slug', …]]
     */
    protected function listSearchConfigSiteDefaults(): array
    {
        $services = $this->getEvent()->getApplication()->getServiceManager();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $sites = $this->api()->search('sites', [], ['returnScalar' => 'slug'])->getContent();
        $result = [];
        foreach ($sites as $siteId => $slug) {
            $siteSettings->setTargetId($siteId);
            $configId = (int) $siteSettings->get('advancedsearch_main_config');
            if ($configId) {
                $result[$configId][] = $slug;
            }
        }
        return $result;
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
     * List running jobs for search indexing and suggesters.
     *
     * @return array Keys: "engines" and "suggesters", each an
     * array of JobRepresentation keyed by engine/suggester id.
     */
    protected function listRunningSearchJobs(): array
    {
        $connection = $this->getEvent()
            ->getApplication()
            ->getServiceManager()
            ->get('Omeka\Connection');

        $jobClasses = [
            \AdvancedSearch\Job\IndexSearch::class,
            \AdvancedSearch\Job\IndexSuggestions::class,
        ];
        $solrClass = 'SearchSolr\Job\CreateSolrSuggesters';
        if (class_exists($solrClass)) {
            $jobClasses[] = $solrClass;
        }

        $sql = 'SELECT id, class, args FROM job'
            . ' WHERE class IN (?)'
            . ' AND status IN (?, ?)'
            . ' ORDER BY id DESC';
        $rows = $connection->executeQuery($sql, [
            $jobClasses,
            \Omeka\Entity\Job::STATUS_STARTING,
            \Omeka\Entity\Job::STATUS_IN_PROGRESS,
        ], [
            \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
            \Doctrine\DBAL\ParameterType::STRING,
            \Doctrine\DBAL\ParameterType::STRING,
        ])->fetchAllAssociative();

        if (!$rows) {
            return ['engines' => [], 'suggesters' => []];
        }

        $api = $this->api();
        $jobRepresentations = [];
        foreach (array_unique(array_column($rows, 'id')) as $jobId) {
            try {
                $jobRepresentations[$jobId] = $api
                    ->read('jobs', $jobId)->getContent();
            } catch (\Throwable $e) {
                // Job may have completed between query and read.
            }
        }

        $result = ['engines' => [], 'suggesters' => []];
        foreach ($rows as $row) {
            if (!isset($jobRepresentations[$row['id']])) {
                continue;
            }
            $job = $jobRepresentations[$row['id']];
            $args = json_decode($row['args'] ?? '{}', true) ?: [];
            if ($row['class'] === \AdvancedSearch\Job\IndexSearch::class) {
                foreach ($args['search_engine_ids'] ?? [] as $eid) {
                    $result['engines'][$eid] = $job;
                }
            } else {
                $sid = $args['search_suggester_id'] ?? null;
                if ($sid) {
                    $result['suggesters'][$sid] = $job;
                }
            }
        }
        return $result;
    }
}
