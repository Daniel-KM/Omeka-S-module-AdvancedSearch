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

namespace AdvancedSearch;

use JsonSerializable;
use Omeka\Api\Manager as ApiManager;

/**
 * @todo Manage resources as a whole with a global order.
 *
 * @todo Finalize restructuration with late binding: this is the response that calls the querier on demand, not the querier that fill everything early.
 */
class Response implements JsonSerializable
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \AdvancedSearch\Query
     */
    protected $query;

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
     * @var string[]
     */
    protected $resourceTypes = [];

    /**
     * @var array
     */
    protected $resourceTotalResults = [];

    /**
     * The value is null if not filled early.
     *
     * @var array|null
     */
    protected $results = null;

    /**
     * The value is null if not filled early.
     *
     * @var array|null
     */
    protected $resources = null;

    /**
     * The value is null if not filled early.
     *
     * @var array|null
     */
    protected $resourcesByType = null;

    /**
     * List of result ids for all pages, if stored by the querier, else computed
     * on request.
     *
     * The value is null if not filled early.
     *
     * @todo Inverse process: manage output as a whole as id => type and if needed, order it by type.
     * But:
     * Because the search engine may be able to search resources and pages that
     * use a different series of ids, the key includes the resource name: items/51 = 51.
     *
     * @var array|null
     *
     * @deprecated The list may be too much big for big bases or overflow.
     */
    protected $allResourceIdsByResourceType = null;

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

    public function setQuery(Query $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function getQuery(): ?Query
    {
        return $this->query;
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
     * @param string[] $resourceTypes The types are generally "items" and
     *   "item_sets". Empty array means no resource type, so any searchable
     *   resources.
     */
    public function setResourceTypes(array $resourceTypes): self
    {
        $this->resourceTypes = $resourceTypes;
        return $this;
    }

    /**
     * @return string[] May be empty when resource types are mixed.
     */
    public function getResourceTypes(): array
    {
        return $this->resourceTypes;
    }

    /**
     * @deprecated Determined from the list of resource types.
     */
    public function setByResourceType(bool $byResourceType): self
    {
        return $this;
    }

    public function getByResourceType(): bool
    {
        return count($this->resourceTypes) > 1;
    }

    /**
     * @param array $resourceTotalResults All total results by resource type.
     */
    public function setAllResourceTotalResults(array $resourceTotalResults): self
    {
        $this->resourceTotalResults = $resourceTotalResults;
        return $this;
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
        return $resourceType === null
            ? $this->resourceTotalResults
            : $this->resourceTotalResults[$resourceType] ?? 0;
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
        $this->results ??= [];
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
        $this->results ??= [];
        $this->results[$resourceType][] = $result;
        return $this;
    }

    /**
     * Get stored results for a resource type or all resource types.
     *
     * The results may be prepared lately from the list of resources.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @return array Array by resource type containing the list of values.
     *   Each value is an array with at least the key id.
     */
    public function getResults(?string $resourceType = null): array
    {
        if ($this->results === null) {
            $this->prepareResults();
        }

        return $resourceType === null
            ? $this->results
            : $this->results[$resourceType] ?? [];
    }

    protected function prepareResults(): self
    {
        $this->results = [];

        if ($this->resources !== null) {
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
            foreach ($this->resources as $resource) {
                $this->results[$resource->resourceName()][] = ['id' => $resource->id()];
            }
            return $this;
        } elseif ($this->resourcesByType !== null) {
            foreach ($this->resourcesByType as $resourceType => $resources) foreach ($resources as $resource) {
                $this->results[$resourceType][] = ['id' => $resource->id()];
            }
            return $this;
        }

        // TODO Prepare results with querier if useful (so the querier does nothing early).

        return $this;
    }

    /**
     * Store all results ids for all resources, by type.
     *
     * @internal Currently experimental.
     *
     * @deprecated The list may be too much big for big bases or overflow. Get query with searchQuery() and process.
     */
    public function setAllResourceIdsByResourceType(array $idsByResourceType): self
    {
        foreach ($idsByResourceType as &$values) {
            $values = array_values($values);
        }
        unset($values);
        $this->allResourceIdsByResourceType = $idsByResourceType;
        return $this;
    }

    /**
     * Store all results ids for a resource type.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @param int[] $ids
     *
     * @internal Currently experimental.
     *
     * @deprecated The list may be too much big for big bases or overflow. Get query with searchQuery() and process.
     */
    public function setAllResourceIdsForResourceType(string $resourceType, array $ids): self
    {
        $this->allResourceIdsByResourceType ??= [];
        $this->allResourceIdsByResourceType[$resourceType] = array_values($ids);
        return $this;
    }

    /**
     * Get resources ids for a resource type or all types, without pagination.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @param bool $byResourceType Merge ids or not.
     * @return int[]
     *
     * @todo Return ids directly with array_column() in the response or include it by default.
     *
     * @internal Currently experimental.
     *
     * @deprecated The list may be too much big for big bases or overflow.
     * Instead, get the query with $response->getQuery()->getQuerier()->getPreparedQuery()
     * and process it.
     */
    public function getAllResourceIds(?string $resourceType = null, bool $byResourceType = false): array
    {
        if ($this->allResourceIdsByResourceType === null) {
            $this->allResourceIdsByResourceType = [];
            if (!$this->query) {
                return [];
            }
            $querier = $this->query->getQuerier();
            if (!$querier) {
                return [];
            }
            $this->allResourceIdsByResourceType = $querier->queryAllResourceIds(null, true);
        }

        if ($byResourceType && !$resourceType) {
            return $this->allResourceIdsByResourceType;
        }

        return $resourceType
            ? $this->allResourceIdsByResourceType[$resourceType] ?? []
            : array_merge(...array_values($this->allResourceIdsByResourceType));
    }

    /**
     * Store mixed resources.
     */
    public function setResources(array $resources): self
    {
        $this->resources = $resources;
        return $this;
    }

    /**
     * Store resources by type.
     *
     * Use resources null to unset and null/null to reset.
     */
    public function setResourcesForType(?string $resourceType, ?array $resources): self
    {
        if ($resourceType === null) {
            if ($resources === null) {
                $this->resourcesByType = [];
            } else {
                unset($this->resourcesByType[$resourceType]);
            }
        } else {
            $this->resourcesByType[$resourceType] = $resources;
        }
        return $this;
    }

    /**
     * Get resources for a resource type or all resource types.
     *
     * The resources can be prepared early by the querier.
     *
     * @todo The results may not have the id as key.
     */
    public function getResources(?string $resourceType = null, bool $flat = false): array
    {
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        if ($resourceType) {
            if (isset($this->resourcesByType[$resourceType])) {
                return $this->resourcesByType[$resourceType];
            } elseif (isset($this->resources)) {
                $result = [];
                foreach ($this->resources as $resource) {
                    if ($resource->resourceName() === $resourceType) {
                        $result[$resource->id()] = $resource;
                    }
                }
                return $result;
            }
        } elseif ($flat) {
            if (isset($this->resources)) {
                return $this->resources;
            } elseif (isset($this->resourcesByType)) {
                $results = [];
                foreach ($this->resourcesByType as $resources) foreach ($resources as $resource) {
                    $results[$resource->id()] = $resource;
                }
                return $results;
            }
        } else {
            if (isset($this->resourcesByType)) {
                return $this->resourcesByType;
            } elseif (isset($this->resources)) {
                $result = [];
                foreach ($this->resources as $resource) {
                    $result[$resource->resourceName()][$resource->id()] = $resource;
                }
                return $result;
            }
        }

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
        foreach ($this->api->search($resourceType, ['id' => array_keys($ids)])->getContent() as $resource) {
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
        if ($from !== null && $to !== null) {
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
        return $name === null
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
        return $name === null
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
            'by_resource_type' => $this->getByResourceType(),
            'resource_total_results' => $this->getResourceTotalResults(),
            'results' => $this->getResults(),
            'facet_counts' => $this->getFacetCounts(),
            'active_facets' => $this->getActiveFacets(),
            'suggestions' => $this->getSuggestions(),
        ];
    }
}
