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
