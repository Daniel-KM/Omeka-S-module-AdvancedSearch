<?php declare(strict_types=1);
/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2021
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

namespace AdvancedSearch\Job;

use AdvancedSearch\Query;
use Doctrine\ORM\EntityManager;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class IndexSearch extends AbstractJob
{
    const BATCH_SIZE = 100;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Logger
     */
    protected $logger;

    public function perform(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $services = $this->getServiceLocator();

        // Because this is an indexer that is used in background, another entity
        // manager is used to avoid conflicts with the main entity manager, for
        // example when the job is run in foreground or multiple resources are
        // imported in bulk, so a flush() or a clear() will not be applied on
        // the imported resources but only on the indexed resources.
        $entityManager = $this->getNewEntityManager($services->get('Omeka\EntityManager'));

        $apiAdapters = $services->get('Omeka\ApiAdapterManager');
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');
        $this->logger = $services->get('Omeka\Logger');

        $batchSize = (int) $settings->get('advancedsearch_batch_size');
        if ($batchSize <= 0) {
            $batchSize = self::BATCH_SIZE;
        }

        $searchEngineId = $this->getArg('search_engine_id');
        $startResourceId = (int) $this->getArg('start_resource_id');

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation $searchEngine */
        $searchEngine = $api->read('search_engines', $searchEngineId)->getContent();
        $indexer = $searchEngine->indexer();

        $resourceNames = $searchEngine->setting('resources', []);
        $selectedResourceNames = $this->getArg('resource_names', []);
        if ($selectedResourceNames) {
            $resourceNames = array_intersect($resourceNames, $selectedResourceNames);
        }
        $resourceNames = array_filter($resourceNames, function ($resourceName) use ($indexer) {
            return $indexer->canIndex($resourceName);
        });
        if (empty($resourceNames)) {
            $this->logger->notice(new Message(
                'Search index #%d ("%s"): there is no resource type to index or the indexation is not needed.', // @translate
                $searchEngine->id(), $searchEngine->name()
            ));
            return;
        }

        $totalJobs = $services->get('ControllerPluginManager')->get('totalJobs');
        $totalJobs = $totalJobs(self::class, true);
        $force = $this->getArg('force');
        if ($totalJobs > 1) {
            if (!$force) {
                $this->logger->err(new Message(
                    'Search index #%d ("%s"): There are already %d other jobs "Index Search" and the current one is not forced.', // @translate
                    $searchEngine->id(), $searchEngine->name(), $totalJobs - 1
                ));
                return;
            }

            $this->logger->warn(new Message(
                'There are already %d other jobs "Index Search". Slowdowns may occur on the site.', // @translate
                $totalJobs - 1
            ));
        }

        $timeStart = microtime(true);

        $this->logger->info(new Message('Search index #%d ("%s"): start of indexing', // @translate
            $searchEngine->id(), $searchEngine->name()));

        $rNames = $resourceNames;
        sort($rNames);
        $fullClearIndex = $startResourceId <= 0
            && array_values($rNames) === ['item_sets', 'items'];

        if ($fullClearIndex) {
            $indexer->clearIndex();
        } elseif ($startResourceId > 0) {
            $this->logger->info(new Message(
                'Search index is not cleared: reindexing starts at resource #%d.', // @translate
                $startResourceId
            ));
        }

        $resources = [];
        $totals = [];
        foreach ($resourceNames as $resourceName) {
            if (!$fullClearIndex && $startResourceId <= 0) {
                $query = new Query();
                $query->setResources([$resourceName]);
                $indexer->clearIndex($query);
            }

            $totals[$resourceName] = 0;
            $searchConfig = 1;
            $entityClass = $apiAdapters->get($resourceName)->getEntityClass();
            $dql = "SELECT resource FROM $entityClass resource";
            if ($startResourceId) {
                $dql .= " WHERE resource.id >= $startResourceId";
            }
            $dql .= " ORDER BY resource.id";

            do {
                if ($this->shouldStop()) {
                    if (empty($resources)) {
                        $this->logger->warn(new Message('Search index #%d ("%s"): the indexing was stopped. Nothing was indexed.', // @translate
                            $searchEngine->id(), $searchEngine->name()));
                    } else {
                        $totalResults = [];
                        foreach ($resourceNames as $resourceName) {
                            $totalResults[] = new Message('%s: %d indexed', $resourceName, $totals[$resourceName]); // @translate
                        }
                        $resource = array_pop($resources);
                        $this->logger->warn(new Message(
                            'Search index #%d ("%s"): the indexing was stopped. Last indexed resource: %s #%d; %s. Execution time: %d seconds.', // @translate
                            $searchEngine->id(),
                            $searchEngine->name(),
                            $resource->getResourceName(),
                            $resource->getId(),
                            implode('; ', $totalResults),
                            (int) (microtime(true) - $timeStart
                        )));
                    }
                    return;
                }

                // TODO Use doctrine large iterable data-processing? See https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html#iterating-large-results-for-data-processing
                $offset = $batchSize * ($searchConfig - 1);
                $q = $entityManager
                    ->createQuery($dql)
                    ->setFirstResult($offset)
                    ->setMaxResults($batchSize);
                /** @var \Omeka\Entity\Resource[] $resources */
                $resources = $q->getResult();

                $indexer->indexResources($resources);

                ++$searchConfig;
                $totals[$resourceName] += count($resources);
                $entityManager->clear();
            } while (count($resources) == $batchSize);
        }

        $totalResults = [];
        foreach ($resourceNames as $resourceName) {
            $totalResults[] = new Message('%s: %d indexed', $resourceName, $totals[$resourceName]); // @translate
        }
        $this->logger->info(new Message('Search index #%d ("%s"): end of indexing. %s. Execution time: %s seconds. Failed indexed resources should be checked manually.', // @translate
            $searchEngine->id(), $searchEngine->name(), implode('; ', $totalResults), (int) (microtime(true) - $timeStart)
        ));
    }

    /**
     * Create a new EntityManager with the same config.
     */
    private function getNewEntityManager(EntityManager $entityManager): EntityManager
    {
        return EntityManager::create(
            $entityManager->getConnection(),
            $entityManager->getConfiguration(),
            $entityManager->getEventManager()
        );
    }
}
