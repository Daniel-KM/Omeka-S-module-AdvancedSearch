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

namespace AdvancedSearch;

use Omeka\Api\Manager as ApiManager;

/**
 * @todo Manage resources as a whole with a global order.
 */
class Response implements \JsonSerializable
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var bool
     */
    protected $isSuccess = false;

    /**
     * @var ?string
     */
    protected $message;

    /**
     * @var int
     */
    protected $currentPage = \Omeka\Stdlib\Paginator::CURRENT_PAGE;

    /**
     * @var int
     */
    protected $perPage = \Omeka\Stdlib\Paginator::PER_PAGE;

    /**
     * @var int
     */
    protected $totalResults = \Omeka\Stdlib\Paginator::TOTAL_COUNT;

    /**
     * @var array
     */
    protected $resourceTotalResults = [];

    /**
     * @var array
     */
    protected $results = [];

    /**
     * List of result ids for all pages, if stored by the querier.
     * @todo Inverse process: manage output as a whole and if needed, order it by type.
     *
     * @var array
     */
    protected $allResourceIdsByResourceType = [];

    /**
     * Active facets are a list of selected facet values by facet.
     * For range facets, the list is a two keys array with "from" and "to".
     *
     * @var array
     */
    protected $activeFacets = [];

    /**
     * @var array
     */
    protected $facetCounts = [];

    /**
     * @var array
     */
    protected $suggestions = [];

    public function setApi(ApiManager $api): self
    {
        $this->api = $api;
        return $this;
    }

    public function setIsSuccess(bool $isSuccess): self
    {
        $this->isSuccess = $isSuccess;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function setMessage($message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getMessage(): ?string
    {
        return (string) $this->message ?: null;
    }

    /**
     * @param int $page
     */
    public function setCurrentPage(?int $page): self
    {
        $this->currentPage = (int) $page;
        return $this;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * @param int $perPage
     */
    public function setPerPage(?int $perPage): self
    {
        $this->perPage = (int) $perPage;
        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @param int $totalResults
     */
    public function setTotalResults(?int $totalResults): self
    {
        $this->totalResults = (int) $totalResults;
        return $this;
    }

    public function getTotalResults(): int
    {
        return $this->totalResults;
    }

    /**
     * @param string $resourceType The resource type ("items", "item_sets"…).
     * @param int $totalResults
     */
    public function setResourceTotalResults(string $resourceType, ?int $totalResults): self
    {
        $this->resourceTotalResults[$resourceType] = (int) $totalResults;
        return $this;
    }

    /**
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @return int|array
     */
    public function getResourceTotalResults(?string $resourceType = null)
    {
        return is_null($resourceType)
            ? $this->resourceTotalResults
            : $this->resourceTotalResults[$resourceType] ?? 0;
    }

    /**
     * Get the list of resource types from results.
     */
    public function getResourceTypes(): array
    {
        return array_keys($this->resourceTotalResults);
    }

    /**
     * Store all results for all resources.
     *
     * @param array $results Results by resource type ("items", "item_sets"…).
     */
    public function setResults(array $results): self
    {
        $this->results = $results;
        return $this;
    }

    /**
     * Store a list of results.
     *
     * @param string $resourceType The resource type ("items", "item_sets"…).
     * @param array $results Each result is an array with "id" as key.
     */
    public function addResults(string $resourceType, array $results): self
    {
        $this->results[$resourceType] = isset($this->results[$resourceType])
            ? array_merge($this->results[$resourceType], array_values($results))
            : array_values($results);
        return $this;
    }

    /**
     * Store a result.
     *
     * @param string $resourceType The resource type ("items", "item_sets"…).
     */
    public function addResult(string $resourceType, array $result): self
    {
        $this->results[$resourceType][] = $result;
        return $this;
    }

    /**
     * Get stored results for a resource type or all resource types.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     */
    public function getResults(?string $resourceType = null): array
    {
        return is_null($resourceType)
            ? $this->results
            : $this->results[$resourceType] ?? [];
    }

    /**
     * Store all results ids for all resources, by type.
     *
     * @internal Currently experimental.
     */
    public function setAllResourceIdsByResourceType(array $idsByResourceType): self
    {
        foreach ($idsByResourceType as &$values) {
            $values = array_values($values);
        }
        $this->allResourceIdsByResourceType = $idsByResourceType;
        return $this;
    }

    /**
     * Store all results ids for a resource type.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @param int[] $ids
     * @internal Currently experimental.
     */
    public function setAllResourceIdsForResourceType(string $resourceType, array $ids): self
    {
        $this->allResourceIdsByResourceType[$resourceType] = array_values($ids);
        return $this;
    }

    /**
     * Get resources ids for a resource type or all types, without pagination.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @param bool $byResourceType Merge ids or not.
     * @return int[]
     * @internal Currently experimental.
     * @todo Return ids directly with array_column() in the response or include it by default.
     */
    public function getResourceIds(?string $resourceType = null, bool $byResourceType = false): array
    {
        if (!count($this->allResourceIdsByResourceType)) {
            foreach (array_keys($this->results) as $resourceType) {
                $this->allResourceIdsByResourceType[$resourceType] = array_column($this->getResults($resourceType), 'id');
            }
        }

        if ($byResourceType && !$resourceType) {
            return $this->allResourceIdsByResourceType;
        }

        return $resourceType
            ? $this->allResourceIdsByResourceType[$resourceType] ?? []
            : array_merge(...array_values($this->allResourceIdsByResourceType));
    }

    /**
     * Get resources for a resource type or all resource types.
     *
     *When the indexation is not up to date, some resources may be removed or
     *privated, so the result may be different from getResults().
     *
     * @todo Create api search for mixed resources in order to keep global order.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]
     * When resources types are set and unique, the key is the id (that is the
     * case with item and item set, not pages).
     */
    public function getResources(?string $resourceType = null): array
    {
        if (!$this->api) {
            return [];
        }

        if (!$resourceType) {
            $resources = [];
            foreach (array_keys($this->results) as $resourceType) {
                $resources = array_replace($resources, $this->getResources($resourceType));
            }
            return $resources;
        }

        // Extract results as a whole to avoid subquery for each resource.
        $ids = array_column($this->getResults($resourceType), 'id', 'id');
        if (!count($ids)) {
            return [];
        }

        // The sort order is unknown, so in order to keep order of results, use
        // the id as key and use array_replace(). This process avoids to do a
        // single read() for each result.
        $resources = [];
        foreach ($this->api->search($resourceType, ['id' => $ids])->getContent() as $resource) {
            $resources[$resource->id()] = $resource;
        }
        return count($resources)
            ? array_replace(array_intersect_key($ids, $resources), $resources)
            : [];
    }

    /**
     * Store a list of active facets.
     *
     * The active facets are the query filters ("filters" is used in solr).
     */
    public function setActiveFacets(array $activeFacetsByName): self
    {
        // Clean keys to simplify merge and other methods.
        $this->activeFacets = array_map(function ($v) {
            if (array_key_exists('from', $v) || array_key_exists('to', $v)) {
                return [
                    'from' => $v['from'] ?? null,
                    'to' => $v['to'] ?? null,
                ];
            }
            return array_values(array_unique($v));
        }, $activeFacetsByName);
        return $this;
    }

    /**
     * Store a list of active facets for a name.
     */
    public function addActiveFacets(string $name, array $activeFacets): self
    {
        if (array_key_exists('from', $activeFacets) || array_key_exists('to', $activeFacets)) {
            $this->activeFacets[$name] = [
                'from' => $activeFacets['from'] ?? null,
                'to' => $activeFacets['to'] ?? null,
            ];
        } else {
            $this->activeFacets[$name] = isset($this->activeFacets[$name])
                ? array_merge($this->activeFacets[$name], array_values($activeFacets))
                : array_values($activeFacets);
            $this->activeFacets[$name] = array_values(array_unique($this->activeFacets[$name]));
        }
        return $this;
    }

    /**
     * Add an active facet for a name.
     */
    public function addActiveFacet(string $name, string $activeFacet): self
    {
        $this->activeFacets[$name][] = $activeFacet;
        $this->activeFacets[$name] = array_values(array_unique($this->activeFacets[$name]));
        return $this;
    }

    /**
     * Add an active facet for a name.
     */
    public function addActiveFacetRange(string $name, ?string $from, ?string $to): self
    {
        if (!is_null($from) && !is_null($to)) {
            $this->activeFacets[$name] = [
                'from' => $from,
                'to' => $to,
            ];
        }
        return $this;
    }

    /**
     * Get the list of active facets.
     */
    public function getActiveFacets(?string $name = null): array
    {
        return is_null($name)
            ? $this->activeFacets
            : $this->activeFacets[$name] ?? [];
    }

    /**
     * Store a list of counts for all facets of all resources.
     *
     * @param array $facetCountsByField Counts by facet, with keys "value" and "count".
     * May contain "from" and "to" for facet range.
     */
    public function setFacetCounts(array $facetCountsByField): self
    {
        $this->facetCounts = $facetCountsByField;
        return $this;
    }

    /**
     * Store a list of counts for a facet.
     *
     * @param string $name
     * @param array $counts List of counts with keys "value" and "count".
     */
    public function addFacetCounts(string $name, array $counts): self
    {
        $this->facetCounts[$name] = isset($this->facetCounts[$name])
            ? array_merge($this->facetCounts[$name], array_values($counts))
            : array_values($counts);
        return $this;
    }

    /**
     * Store the count for a facet.
     */
    public function addFacetCount(string $name, $value, int $count): self
    {
        $this->facetCounts[$name][] = [
            'value' => $value,
            'count' => $count,
        ];
        return $this;
    }

    /**
     * Get all the facet counts or a specific one.
     */
    public function getFacetCounts(?string $name = null): array
    {
        return is_null($name)
            ? $this->facetCounts
            : $this->facetCounts[$name] ?? [];
    }

    public function setSuggestions(array $suggestions): self
    {
        $this->suggestions = $suggestions;
        return $this;
    }

    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function jsonSerialize(): array
    {
        return [
            'success' => $this->isSuccess(),
            'message' => $this->getMessage(),
            'current_page' => $this->getCurrentPage(),
            'per_page' => $this->getPerPage(),
            'total_results' => $this->getTotalResults(),
            'resource_types' => $this->getResourceTypes(),
            'resource_total_results' => $this->getResourceTotalResults(),
            'results' => $this->getResults(),
            'facet_counts' => $this->getFacetCounts(),
            'active_facets' => $this->getActiveFacets(),
            'suggestions' => $this->getSuggestions(),
        ];
    }
}
