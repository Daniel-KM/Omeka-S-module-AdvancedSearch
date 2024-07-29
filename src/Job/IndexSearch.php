<?php declare(strict_types=1);
/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2024
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
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Omeka\Job\AbstractJob;

class IndexSearch extends AbstractJob
{
    const BATCH_SIZE = 100;

    /**
     * @var \Laminas\Log\Logger
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
        $this->logger = $services->get('Omeka\Logger');
        $translator = $services->get('MvcTranslator');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('search/index/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $batchSize = self::BATCH_SIZE;

        $searchEngineId = $this->getArg('search_engine_id');
        $startResourceId = (int) $this->getArg('start_resource_id');
        $resourceIds = $this->getArg('resource_ids', []) ?: [];

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation $searchEngine */
        $searchEngine = $api->read('search_engines', $searchEngineId)->getContent();
        $indexer = $searchEngine->indexer();

        // Clean resource ids to avoid check later.
        $ids = array_filter(array_map('intval', $resourceIds));
        if (count($resourceIds) !== count($ids)) {
            $this->logger->notice(
                'Search index #{search_engine_id} ("{name}"): the list of resource ids contains invalid ids.', // @translate
                ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
            );
            return;
        }
        $resourceIds = $ids;
        unset($ids);
        if ($resourceIds) {
            $startResourceId = 1;
        }

        $resourceTypes = $searchEngine->setting('resource_types', []);
        $selectedResourceTypes = $this->getArg('resource_types', []);
        if ($selectedResourceTypes) {
            $resourceTypes = array_intersect($resourceTypes, $selectedResourceTypes);
        }
        $resourceTypes = array_filter($resourceTypes, fn ($resourceType) => $indexer->canIndex($resourceType));
        if (empty($resourceTypes)) {
            $this->logger->notice(
                'Search index #{search_engine_id} ("{name}"): there is no resource type to index or the indexation is not needed.', // @translate
                ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
            );
            return;
        }

        $totalJobs = $services->get('ControllerPluginManager')->get('totalJobs');
        $totalJobs = $totalJobs(self::class, true);
        $force = $this->getArg('force');
        if ($totalJobs > 1) {
            if (!$force) {
                $this->logger->err(
                    'Search index #{search_engine_id} ("{name}"): There are already {total} other jobs "Index Search" and the current one is not forced.', // @translate
                    ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name(), 'total' => $totalJobs - 1]
                );
                return;
            }
            $this->logger->warn(
                'There are already {total} other jobs "Index Search". Slowdowns may occur on the site.', // @translate
                ['total' => $totalJobs - 1]
            );
        }

        $timeStart = microtime(true);

        $this->logger->notice(
            'Search index #{search_engine_id} ("{name}"): start of indexing', // @translate
            ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
        );

        $rTypes = $resourceTypes;
        sort($rTypes);
        $fullClearIndex = empty($resourceIds)
            && $startResourceId <= 0
            && array_values($rTypes) === ['item_sets', 'items'];

        if ($fullClearIndex) {
            $indexer->clearIndex();
        } elseif (empty($resourceIds) && $startResourceId > 0) {
            $this->logger->info(
                'Search index is not cleared: reindexing starts at resource #{resource_id}.', // @translate
                ['resource_id' => $startResourceId]
            );
        }

        $resources = [];
        $totals = [];
        foreach ($resourceTypes as $resourceType) {
            if (!$fullClearIndex && empty($resourceIds) && $startResourceId <= 0) {
                $query = new Query();
                $query
                    // By default the query process public resources only.
                    ->setIsPublic(false)
                    ->setResourceTypes([$resourceType]);
                $indexer->clearIndex($query);
            }

            $totals[$resourceType] = 0;
            $searchConfig = 1;
            $entityClass = $apiAdapters->get($resourceType)->getEntityClass();
            $dql = "SELECT resource FROM $entityClass resource";
            $parameter = null;
            if (count($resourceIds)) {
                // The list of ids is cleaned above.
                $dql .= ' WHERE resource.id IN (:resource_ids)';
                $parameter = ['name' => 'resource_ids', 'bind' => $resourceIds, 'type' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY];
            } elseif ($startResourceId) {
                $dql .= ' WHERE resource.id >= :start_resource_id';
                $parameter = ['name' => 'start_resource_id', 'bind' => $startResourceId, 'type' => \Doctrine\DBAL\ParameterType::INTEGER];
            }
            $dql .= " ORDER BY resource.id ASC";

            do {
                if ($this->shouldStop()) {
                    if (empty($resources)) {
                        $this->logger->warn(
                            'Search index #{search_engine_id} ("{name}"): the indexing was stopped. Nothing was indexed.', // @translate
                            ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
                        );
                    } else {
                        $totalResults = [];
                        foreach ($resourceTypes as $resourceType) {
                            $totalResults[] = (new PsrMessage(
                                '{resource_type}: {count} indexed', // @translate
                                ['resource_type' => $resourceType, 'count' => $totals[$resourceType]]
                            ))->setTranslator($translator);
                        }
                        $resource = array_pop($resources);
                        $this->logger->warn(
                            'Search index #{search_engine_id} ("{name}"): the indexing was stopped. Last indexed resource: {resource_type} #{resource_id}; {results}. Execution time: {duration} seconds.', // @translate
                            [
                                'search_engine_id' => $searchEngine->id(),
                                'name' => $searchEngine->name(),
                                'resource_type' => $resource->getResourceType(),
                                'resource_id' => $resource->getId(),
                                'results' => implode('; ', $totalResults),
                                'duration' => (int) (microtime(true) - $timeStart),
                            ]
                        );
                    }
                    return;
                }

                // TODO Use doctrine large iterable data-processing? See https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html#iterating-large-results-for-data-processing
                $offset = $batchSize * ($searchConfig - 1);
                $qb = $entityManager
                    ->createQuery($dql)
                    ->setFirstResult($offset)
                    ->setMaxResults($batchSize);
                if ($parameter) {
                    $qb
                        ->setParameter($parameter['name'], $parameter['bind'], $parameter['type']);
                }
                /** @var \Omeka\Entity\Resource[] $resources */
                $resources = $qb->getResult();

                $indexer->indexResources($resources);

                ++$searchConfig;
                $totals[$resourceType] += count($resources);
                $entityManager->clear();
            } while (count($resources) === $batchSize);
        }

        $totalResults = [];
        foreach ($resourceTypes as $resourceType) {
            $totalResults[] = (new PsrMessage(
                '{resource_type}: {count} indexed', // @translate
                ['resource_type' => $resourceType, 'count' => $totals[$resourceType]]
            ))->setTranslator($translator);
        }

        $timeTotal = (int) (microtime(true) - $timeStart);

        $this->logger->info(
            'Search index #{search_engine_id} ("{name}"): end of indexing. {results}. Execution time: {duration} seconds. Failed indexed resources should be checked manually.', // @translate
            [
                'search_engine_id' => $searchEngine->id(),
                'name' => $searchEngine->name(),
                'results' => implode('; ', $totalResults),
                'duration' => $timeTotal,
            ]
        );
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
