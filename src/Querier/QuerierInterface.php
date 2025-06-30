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

namespace AdvancedSearch\Querier;

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use Laminas\Log\LoggerAwareInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

interface QuerierInterface extends LoggerAwareInterface
{
    /**
     * @deprecated Use factories.
     */
    public function setServiceLocator(ServiceLocatorInterface $services): self;

    public function setSearchEngine(SearchEngineRepresentation $searchEngine): self;

    public function setQuery(Query $query): self;

    /**
     * Process a search query.
     */
    public function query(): Response;

    /**
     * Process a search query for suggestions.
     */
    public function querySuggestions(): Response;

    /**
     * Process a search query for lists, in particular to fill filters.
     *
     * Options (languages, order, limit) may be appended to query args.
     *
     * @return array Key-value pairs for name and label, generally the same.
     */
    public function queryValues(string $field): array;

    /**
     * Process a search query to get all ids of a type, without pagination.
     *
     * This method should be avoided when the total resources is too big for the
     * server, else a memory overflow can occur.
     *
     * It is used in the rare cases where a whole list of ids is needed, in
     * particular to get Mapping features for an item set, and old versions of
     * Bulk Export and Reference.
     *
     * @param string|null $resourceType May be "items", "resources", etc.
     * @param bool $byResourceType Return ids by type when set.
     * @return array List of ids for the resource type. If the resource type is
     * not set, all resources (items, item sets, media) will be mixed, unless
     * the second argument is true.
     */
    public function queryAllResourceIds(?string $resourceType = null, bool $byResourceType = false): array;

    /**
     * Prepare a search query.
     *
     * @return mixed|null The query formatted for this specific search engine.
     * Return null if the query is not processable or without result.
     */
    public function getPreparedQuery();
}
