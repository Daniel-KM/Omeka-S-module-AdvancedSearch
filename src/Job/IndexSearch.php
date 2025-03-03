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

use AdvancedSearch\Query;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Omeka\Job\AbstractJob;

class IndexSearch extends AbstractJob
{
    /**
     * The number of resources to index by step.
     *
     * Use a small number to avoid memory issues.
     * In practice, a number of 1 reduces memory usage of 1% or 2%.
     *
     * @var int
     */
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

        $api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $translator = $services->get('MvcTranslator');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('search/index/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $searchEngineId = $this->getArg('search_engine_id');
        $clearIndex = (bool) $this->getArg('clear_index');
        $startResourceId = (int) $this->getArg('start_resource_id');
        $resourceIds = $this->getArg('resource_ids', []) ?: [];
        $batchSize = abs((int) $this->getArg('resources_by_batch')) ?: self::BATCH_SIZE;
        $sleepAfterLoop = abs((int) $this->getArg('sleep_after_loop')) ?: 0;

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

        $listJobStatusesByIds = $services->get('ControllerPluginManager')->get('listJobStatusesByIds');
        $listJobStatusesByIds = $listJobStatusesByIds(self::class, true, null, $this->job->getId());
        $force = $this->getArg('force');
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

        if ($startResourceId > 1) {
            $this->logger->info(
                'Reindexing starts at resource #{resource_id}.', // @translate
                ['resource_id' => $startResourceId]
            );
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
            $loop = 1;

            $args = [
                'sort_by' => 'id',
                'sort_order' => 'asc',
            ];

            if (count($resourceIds)) {
                // The list of ids is cleaned above.
                $args['id'] = $resourceIds;
            } elseif ($startResourceId) {
                $ids = $api->search($resourceType, ['sort_by' => 'id', 'sort_order' => 'asc'], ['returnScalar' => 'id'])->getContent();
                $ids = array_values(array_filter(array_keys($ids), fn ($id) => $id >= $startResourceId));
                if (!$ids) {
                    continue;
                }
                $args['id'] = $ids;
            }
            if ($visibility) {
                $args['is_public'] = $visibility === 'private' ? 0 : 1;
            }

            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources */
            $resources = [];
            $countResources = 0;

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
                            ]
                        );
                    }
                    return;
                }

                $offset = $batchSize * ($loop - 1);
                $args['offset'] = $offset;
                $args['limit'] = $batchSize;
                $resources = $api->search($resourceType, $args)->getContent();

                $countResources = count($resources);
                $indexer->indexResources($resources);

                // Clear resources for memory usage.
                // TODO Remove any part of the representation, in particular the values (25% of memory issue).
                foreach ($resources as &$resource) {
                    $resource = null;
                }
                unset($resources);

                ++$loop;
                $totals[$resourceType] += $countResources;

                $entityManager->clear();

                // Useless in practice and need some seconds.
                // gc_collect_cycles();

                // May avoid issue with some firewall/proxy limit with Solr.
                if ($sleepAfterLoop) {
                    sleep($sleepAfterLoop);
                }

            } while ($countResources === $batchSize);
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
