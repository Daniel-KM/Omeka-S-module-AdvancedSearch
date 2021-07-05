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

namespace Search;

class Response implements \JsonSerializable
{
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
    protected $totalResults = 0;

    /**
     * @var array
     */
    protected $resourceTotalResults = [];

    /**
     * @var array
     */
    protected $results = [];

    /**
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
    public function getResults(string $resourceType = null): array
    {
        return is_null($resourceType)
            ? $this->results
            : $this->results[$resourceType] ?? [];
    }

    /**
     * Store a list of active facets.
     *
     * The active facets are the query filters ("filters" is used in solr).
     */
    public function setActiveFacets(array $activeFacetsByName): self
    {
        $this->activeFacets = array_map(function ($v) {
            return array_values(array_unique($v));
        }, $activeFacetsByName);
        return $this;
    }

    /**
     * Store a list of active facets for a name.
     */
    public function addActiveFacets(string $name, array $activeFacets): self
    {
        $this->activeFacets[$name] = isset($this->activeFacets[$name])
            ? array_merge($this->activeFacets[$name], array_values($activeFacets))
            : array_values($activeFacets);
        $this->activeFacets[$name] = array_values(array_unique($this->activeFacets[$name]));
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
            'totalResults' => $this->getTotalResults(),
            'resourceTotalResults' => $this->getResourceTotalResults(),
            'results' => $this->getResults(),
            'facetCounts' => $this->getFacetCounts(),
            'suggestions' => $this->getSuggestions(),
        ];
    }
}
