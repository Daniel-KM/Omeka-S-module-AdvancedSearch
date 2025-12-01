<?php declare(strict_types=1);
/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2025
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

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\Query;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Omeka\Job\AbstractJob;

class IndexSearch extends AbstractJob
{
    /**
     * The number of resources to index by step.
     *
     * A lower batch size does not mean lower memory usage. It may depends on
     * resources and number of linked resources.
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-SearchSolr/-/issues/13
     *
     * @var int
     */
    const BATCH_SIZE = 500;

    /**
     * @var \Omeka\Api\Adapter\Manager
     */
    protected $adapterManager;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var int
     */
    protected $batchSize = self::BATCH_SIZE;

    /**
     * @var array
     */
    protected $resourceIds = [];

    /**
     * @var array
     */
    protected $resourceTypes = [];

    /**
     * @var int
     */
    protected $resourcesOffset = 0;

    /**
     * @var array
     */
    protected $resourcesLimit = 0;

    /**
     * @var int
     */
    protected $sleepAfterLoop = 0;

    /**
     * @var int
     */
    protected $startResourceId = 0;

    /**
     * @var array
     */
    protected $staticEntityIds = [];

    public function perform(): void
    {
        // TODO Paralelize independant search engines. Useless for now.

        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->adapterManager = $services->get('Omeka\ApiAdapterManager');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('search/index/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $searchEngineIds = $this->getArg('search_engine_ids');

        $this->resourceIds = $this->getArg('resource_ids', []) ?: [];
        $this->startResourceId = abs((int) $this->getArg('start_resource_id'));

        $this->resourcesLimit = abs((int) $this->getArg('resources_limit'));
        $this->resourcesOffset = abs((int) $this->getArg('resources_offset'));

        $this->batchSize = abs((int) $this->getArg('resources_by_batch')) ?: self::BATCH_SIZE;
        $this->sleepAfterLoop = abs((int) $this->getArg('sleep_after_loop')) ?: 0;

        $this->resourceTypes = $this->getArg('resource_types') ?: [];

        $force = $this->getArg('force');

        if (!$searchEngineIds) {
            $this->logger->warn(
                'No search engine is defined to be indexed.' // @translate
            );
            return;
        }

        // Quick check on resource types.

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $this->api->search('search_engines', ['id' => $searchEngineIds])->getContent();
        $searchEnginesToProcess = [];
        foreach ($searchEngines as $searchEngine) {
            $indexer = $searchEngine->indexer();
            $searchEngineResourceTypes = $this->resourceTypes ?: $searchEngine->setting('resource_types', []);
            foreach ($searchEngineResourceTypes as $resourceType) {
                if ($indexer->canIndex($resourceType)
                    && in_array($resourceType, $searchEngine->setting('resource_types', []))
                ) {
                    $searchEnginesToProcess[$searchEngine->id()] = $searchEngine;
                }
            }
        }
        if (!count($searchEnginesToProcess)) {
            $this->logger->warn(
                'No search engine is defined to index the specified resource types.' // @translate
            );
            return;
        }
        $searchEngines = $searchEnginesToProcess;
        unset($searchEnginesToProcess);

        // Clean resource ids to avoid check later.
        $ids = array_values(array_filter(array_map('intval', $this->resourceIds)));
        if (count($this->resourceIds) !== count($ids)) {
            $this->logger->warn(
                'Search index #{search_engine_id} ("{name}"): the list of resource ids contains invalid ids.', // @translate
                ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
            );
            return;
        }
        $this->resourceIds = $ids;
        unset($ids);

        $listJobStatusesByIds = $services->get('ControllerPluginManager')->get('listJobStatusesByIds');
        $listJobStatusesByIds = $listJobStatusesByIds(self::class, true, null, $this->job->getId());
        if (count($listJobStatusesByIds)) {
            if (!$force) {
                $this->logger->err(
                    'Search index #{search_engine_id} ("{name}"): There are already {total} other jobs "Index Search" (#{list}) and the current one is not forced.', // @translate
                    [
                        'search_engine_id' => $searchEngine->id(),
                        'name' => $searchEngine->name(),
                        'total' => count($listJobStatusesByIds),
                        'list' => implode(', #', array_keys($listJobStatusesByIds)),
                    ]
                );
                return;
            }
            $this->logger->warn(
                'There are already {total} other jobs "Index Search". Slowdowns may occur on the site.', // @translate
                ['total' => $listJobStatusesByIds]
            );
        }

        if ($this->resourceIds) {
            $this->startResourceId = 1;
        }
        if ($this->startResourceId > 1) {
            $this->logger->info(
                'Reindexing starts at resource #{resource_id}.', // @translate
                ['resource_id' => $this->startResourceId]
            );
        }

        // Because this is an indexer that is used in background, another entity
        // manager is used to avoid conflicts with the main entity manager when
        // it is run in foreground or when multiple resources are imported in
        // bulk and a sub-job is launched. So a flush() or a clear() will not be
        // applied on the imported resources but only on the indexed ones.
        $isBackend = empty($_SERVER['REQUEST_METHOD']);
        if ($isBackend) {
            $this->entityManager = $services->get('Omeka\EntityManager');
            $this->logger->debug(
                'Process done with the main entity manager' // @translate
            );
        } else {
            $this->entityManager = $this->getNewEntityManager($services->get('Omeka\EntityManager'));
            $this->logger->debug(
                'Process done with a new entity manager' // @translate
            );
        }

        // Disable query caching to reduce memory overhead.
        $configuration = $this->entityManager->getConfiguration();
        /* TODO Null cannot be passed and ArrayAdapter is not yet available in omeka 4.1.
        $arrayAdapterQuery = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $configuration->setQueryCache($arrayAdapterQuery);
        $arrayAdapterResult = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $configuration->setResultCache($arrayAdapterResult);
        */
        $arrayCacheQuery = new \Doctrine\Common\Cache\ArrayCache();
        $configuration->setQueryCacheImpl($arrayCacheQuery);
        $arrayCacheResult = new \Doctrine\Common\Cache\ArrayCache();
        $configuration->setResultCacheImpl($arrayCacheResult);

        // Disable second-level cache if enabled.
        if ($configuration->isSecondLevelCacheEnabled()) {
            $cacheFactory = $configuration->getSecondLevelCacheConfiguration()->getCacheFactory();
            if (method_exists($cacheFactory, 'getRegion')) {
                $cacheFactory->getRegion('default_query_region')->evictAll();
            }
        }

        // Prepare data to refresh entity manager.
        $this->staticEntityIds = [
            'job_id' => $this->job->getId(),
            'user_id' => $this->job->getOwner() ? $this->job->getOwner()->getId() : null,
        ];

        // Set READ_UNCOMMITTED for performance during read-heavy indexing.
        // WARNING: May read uncommitted changes from concurrent transactions.
        // Only use when indexing can tolerate temporary inconsistencies that will
        // be corrected on next full reindex.
        $this->entityManager->getConnection()->setTransactionIsolation(
            \Doctrine\DBAL\TransactionIsolationLevel::READ_UNCOMMITTED
        );

        $timeStart = microtime(true);

        foreach ($searchEngines as $searchEngine) {
            $this->indexSearchEngine($searchEngine);
        }

        $timeTotal = (int) (microtime(true) - $timeStart);
        $maxMemory = memory_get_peak_usage(true);

        $this->logger->info(
            'End of indexing. Execution time: {duration} seconds. Max memory: {memory}. Failed indexed resources should be checked manually.', // @translate
            ['duration' => $timeTotal, 'memory' => $maxMemory]
        );
    }

    protected function indexSearchEngine(SearchEngineRepresentation $searchEngine): void
    {
        $indexer = $searchEngine->indexer();

        $clearIndex = (bool) $this->getArg('clear_index');

        $engineResourceTypes = $searchEngine->setting('resource_types', []);
        $resourceTypes = $this->resourceTypes
            ? array_intersect($engineResourceTypes, $this->resourceTypes)
            : $engineResourceTypes;
        $resourceTypes = array_filter($resourceTypes, fn ($resourceType) => $indexer->canIndex($resourceType));

        if (empty($resourceTypes)) {
            $this->logger->notice(
                'Search index #{search_engine_id} ("{name}"): there is no resource type to index or the indexation is not needed.', // @translate
                ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
            );
            return;
        }

        // Use the option set in the form if any, only when the search engine
        // manage all visibilities.
        $visibility = $searchEngine->setting('visibility');
        $visibility = in_array($visibility, ['public', 'private']) ? $visibility : null;
        if (!$visibility) {
            $visibilityJob = $this->getArg('visibility');
            $visibility = in_array($visibilityJob, ['public', 'private']) ? $visibilityJob : null;
        }

        if ($visibility) {
            $this->logger->notice(
                'Search index #{search_engine_id} ("{name}"): Only {visibility} resources will be indexed.', // @translate
                ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name(), 'visibility' => $visibility]
            );
        }

        $timeStart = microtime(true);

        $this->logger->notice(
            'Search index #{search_engine_id} ("{name}"): start of indexing', // @translate
            ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
        );

        if ($clearIndex && empty($resourceTypes)) {
            $this->logger->info(
                'Search index is fully cleared.' // @translate
            );
            $indexer->clearIndex();
        }

        $totals = [];
        foreach ($resourceTypes as $resourceType) {
            if ($clearIndex) {
                $query = new Query();
                // Here, no need to init the aliases and aggregated fields
                // because there is no site or admin and aliases are managed
                // earlier or later.
                $query
                    // By default the query process public resources only.
                    // TODO Check the purpose of the check of isPublic here.
                    ->setIsPublic(false)
                    ->setResourceTypes([$resourceType]);
                $indexer->clearIndex($query);
            }

            $totals[$resourceType] = 0;

            $args = [
                'sort_by' => 'id',
                'sort_order' => 'asc',
            ];

            if ($visibility) {
                $args['is_public'] = $visibility === 'private' ? 0 : 1;
            }

            if (count($this->resourceIds)) {
                // The list of ids is cleaned above.
                $args['id'] = $this->resourceIds;
            } else {
                $resourceQueryArgs = ['sort_by' => 'id', 'sort_order' => 'asc'];
                if ($this->resourcesLimit) {
                    $resourceQueryArgs['limit'] = $this->resourcesLimit;
                }
                if ($this->resourcesOffset) {
                    $resourceQueryArgs['offset'] = $this->resourcesOffset;
                }

                $ids = $this->api->search($resourceType, $resourceQueryArgs, ['returnScalar' => 'id'])->getContent();
                if ($this->startResourceId) {
                    $ids = array_keys(array_filter($ids, fn ($id) => $id >= $this->startResourceId, ARRAY_FILTER_USE_KEY));
                }
                if (!count($ids)) {
                    continue;
                }
                $args['id'] = $ids;
            }

            $ids = $args['id'];

            $loop = 1;
            $resources = [];
            $totalToProcessForCurrentResourceType = count($args['id']);

            // $lastMemUsage = round(memory_get_usage(true) / 1024 / 1024, 2);

            do {
                if ($this->shouldStop()) {
                    $this->logStopMessage($searchEngine, $resources, $resourceTypes, $totals, $timeStart);
                    return;
                }

                $offset = $this->batchSize * ($loop - 1);
                $resourceIdsSlice = array_slice($ids, $offset, $this->batchSize);
                if (!count($resourceIdsSlice)) {
                    break;
                }

                $args['id'] = $resourceIdsSlice;

                /**
                 * @var \Omeka\Entity\Resource $entity
                 * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
                 */
                $entities = $this->api->search($resourceType, $args, ['responseContent' => 'resource'])->getContent();
                $resources = array_map(fn ($entity) => $this->adapterManager->get($resourceType)->getRepresentation($entity), $entities);
                if (count($resources) !== count($resourceIdsSlice)) {
                    $this->logger->err('Error retrieving batch #{number} of {resource_type}: some resources are missing.', // @translate
                    ['number' => $loop, 'resource_type' => $resourceType]);
                }

                // Index entire batch at once.
                if (count($resources)) {
                    try {
                        $indexer->indexResources($resources);
                        $totals[$resourceType] += count($resources);
                    } catch (\Exception $e) {
                        $this->logger->err(
                            'Error indexing batch #{number} of {resource_type}: {message}', // @translate
                            ['number' => $loop, 'resource_type' => $resourceType, 'message' => $e->getMessage()]
                        );
                    }

                    // Immediately detach all entities from this batch.
                    foreach ($entities as $entity) {
                        $this->entityManager->detach($entity);
                    }
                    unset($resources, $resource, $resourceIdsSlice);
                    unset($entities, $entity);
                }

                /*
                $this->logger->info(
                    'Memory usage after batch #{number}: {memory} MB', // @translate
                    ['number' => $loop, 'memory' => round(memory_get_usage(true) / 1024 / 1024, 2)]
                );
                */

                ++$loop;
                $this->refreshEntityManager();

                sleep($this->sleepAfterLoop);
            } while ($offset <= $totalToProcessForCurrentResourceType);
        }

        $totalResults = [];
        foreach ($resourceTypes as $resourceType) {
            $totalResults[] = new PsrMessage(
                '{resource_type}: {count} indexed', // @translate
                ['resource_type' => $resourceType, 'count' => $totals[$resourceType]]
            );
        }

        $timeTotal = (int) (microtime(true) - $timeStart);

        $this->logger->info(
            'Search index #{search_engine_id} ("{name}"): end of indexing. {results}. Execution time: {duration} seconds. Failed indexed resources should be checked manually.', // @translate
            [
                'search_engine_id' => $searchEngine->id(),
                'name' => $searchEngine->name(),
                'results' => implode('; ', $totalResults),
                'duration' => $timeTotal,
                // 'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]
        );
    }

    protected function logStopMessage(
        SearchEngineRepresentation $searchEngine,
        ?array $resources,
        array $resourceTypes,
        array $totals,
        float $timeStart
    ): void {
        if (empty($resources)) {
            $this->logger->warn(
                'Search index #{search_engine_id} ("{name}"): the indexing was stopped. Nothing was indexed.', // @translate
                ['search_engine_id' => $searchEngine->id(), 'name' => $searchEngine->name()]
            );
            return;
        }

        $totalResults = [];
        foreach ($resourceTypes as $resourceType) {
            $totalResults[] = new PsrMessage(
                '{resource_type}: {count} indexed', // @translate
                ['resource_type' => $resourceType, 'count' => $totals[$resourceType]]
            );
        }

        /** @var \Omeka\Entity\Resource $resource */
        $resource = array_pop($resources);

        $this->logger->warn(
            'Search index #{search_engine_id} ("{name}"): the indexing was stopped. Last indexed resource: {resource_type} #{resource_id}; {results}. Execution time: {duration} seconds.', // @translate
            [
                'search_engine_id' => $searchEngine->id(),
                'name' => $searchEngine->name(),
                'resource_type' => $resource->resourceName(),
                'resource_id' => $resource->id(),
                'results' => implode('; ', $totalResults),
                'duration' => (int) (microtime(true) - $timeStart),
                // 'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]
        );
    }

    protected function refreshEntityManager(): void
    {
        // Clear query result cache to prevent memory build new entities.
        // Useless.
        // $this->entityManager->getConfiguration()->getResultCacheImpl()?->deleteAll();

        // Try to flush and clear all managed entities.
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Try to clear identity map more explicitly.
        // Useless with clear().
        /*
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $reflectionProperty = new \ReflectionProperty(get_class($unitOfWork), 'identityMap');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($unitOfWork, []);
        */

        // Force garbage collection after clearing.
        // Useless in practice and need some seconds.
        // gc_collect_cycles();

        // Keep user.
        $user = $this->staticEntityIds['user_id']
            ? $this->entityManager->find(\Omeka\Entity\User::class, $this->staticEntityIds['user_id'])
            : null;
        if ($user && !$this->entityManager->contains($user)) {
            $this->entityManager->persist($user);
            // $this->getServiceLocator()->get('Omeka\AuthenticationService')->setIdentity($user);
        }

        // Keep job.
        $this->job = $this->entityManager->find(\Omeka\Entity\Job::class, $this->staticEntityIds['job_id']);
        if (!$this->entityManager->contains($this->job)) {
            $this->job->setOwner($user);
            $this->entityManager->persist($this->job);
        }
    }

    /**
     * Create a new EntityManager with the same config.
     *
     * It is used only for foreground jobs to avoid conflicts with the main one.
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
