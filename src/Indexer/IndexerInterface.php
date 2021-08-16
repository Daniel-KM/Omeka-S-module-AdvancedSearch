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

namespace AdvancedSearch\Indexer;

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\Query;
use Laminas\Log\LoggerAwareInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Resource;

/**
 * The signature uses "IndexerInterface" instead of "self" for compatibility with php < 7.4.
 */
interface IndexerInterface extends LoggerAwareInterface
{
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator): IndexerInterface;

    public function setSearchEngine(SearchEngineRepresentation $engine): IndexerInterface;

    /**
     * Inidicate if the resource can be indexed.
     *
     * @param string $resourceName The resource type ("items", "item_sets"…).
     * @return bool
     */
    public function canIndex(string $resourceName): bool;

    /**
     * Reset the index.
     *
     * @param Query $query Allows to limit clearing to some resources.
     * @return self
     */
    public function clearIndex(?Query $query = null): IndexerInterface;

    /**
     * Index a resource.
     */
    public function indexResource(Resource $resource): IndexerInterface;

    /**
     * Index multiple resources.
     *
     * @param Resource[] $resources
     */
    public function indexResources(array $resources): IndexerInterface;

    /**
     * Unindex a deleted resource.
     *
     * @param string $resourceName The resource type ("items", "item_sets"…).
     * @param int $id
     * @return self
     */
    public function deleteResource(string $resourceName, $id): IndexerInterface;
}
