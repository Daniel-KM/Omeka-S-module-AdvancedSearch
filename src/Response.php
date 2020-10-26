<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2020
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
    protected $facetCounts = [];

    /**
     * @param int $totalResults
     */
    public function setTotalResults($totalResults)
    {
        $this->totalResults = (int) $totalResults;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalResults()
    {
        return $this->totalResults;
    }

    /**
     * @param string $resourceType The resource type ("items", "item_sets"…).
     * @param int $totalResults
     */
    public function setResourceTotalResults($resourceType, $totalResults)
    {
        $this->resourceTotalResults[$resourceType] = (int) $totalResults;
        return $this;
    }

    /**
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @return int|array
     */
    public function getResourceTotalResults($resourceType = null)
    {
        if (is_null($resourceType)) {
            return $this->resourceTotalResults;
        }
        return isset($this->resourceTotalResults[$resourceType])
            ? $this->resourceTotalResults[$resourceType]
            : 0;
    }

    /**
     * Store all results for all resources.
     *
     * @param array $results Results by resource type ("items", "item_sets"…).
     * @return self
     */
    public function setResults(array $results)
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
    public function addResults($resourceType, $results)
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
     * @param array $result
     */
    public function addResult($resourceType, $result)
    {
        $this->results[$resourceType][] = $result;
        return $this;
    }

    /**
     * Get stored results for a resource type or all resource types.
     *
     * @param string|null $resourceType The resource type ("items", "item_sets"…).
     * @return array
     */
    public function getResults($resourceType = null)
    {
        if (is_null($resourceType)) {
            return $this->results;
        }
        return isset($this->results[$resourceType])
            ? $this->results[$resourceType]
            : [];
    }

    /**
     * Store a list of counts for all facets of all resources.
     *
     * @param array $facetCounts Counts by facet, with keys "value" and "count".
     * @return self
     */
    public function setFacetCounts(array $facetCounts)
    {
        $this->facetCounts = $facetCounts;
        return $this;
    }

    /**
     * Store a list of counts for a facet.
     *
     * @param string $name
     * @param array $counts List of counts with keys "value" and "count".
     */
    public function addFacetCounts($name, $counts)
    {
        $this->facetCounts[$name] = isset($this->facetCounts[$name])
            ? array_merge($this->facetCounts[$name], array_values($counts))
            : array_values($counts);
        return $this;
    }

    /**
     * Store the count for a facet.
     *
     * @param string $name
     * @param string $value
     * @param int $count
     */
    public function addFacetCount($name, $value, $count)
    {
        $this->facetCounts[$name][] = [
            'value' => $value,
            'count' => $count,
        ];
        return $this;
    }

    /**
     * Get all the facet counts or a specific one.
     *
     * @param string|null $name
     * @return array
     */
    public function getFacetCounts($name = null)
    {
        if (is_null($name)) {
            return $this->facetCounts;
        }
        return isset($this->facetCounts[$name])
            ? $this->facetCounts[$name]
            : [];
    }

    public function jsonSerialize()
    {
        return [
            'totalResults' => $this->getTotalResults(),
            'resourceTotalResults' => $this->getResourceTotalResults(),
            'results' => $this->getResults(),
            'facetCounts' => $this->getFacetCounts(),
        ];
    }
}
