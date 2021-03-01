<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2020
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/ or
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

use Omeka\Api\Representation\SiteRepresentation;

class Query implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $query = '';

    /**
     * @var string[]
     */
    protected $resources = [];

    /**
     * @var bool
     */
    protected $isPublic = true;

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var array
     */
    protected $dateRangeFilters = [];

    /**
     * @var array
     */
    protected $filterQueries = [];

    /**
     * @var string|null
     */
    protected $sort = '';

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var array
     */
    protected $facetFields = [];

    /**
     * @var int
     */
    protected $facetLimit = 0;

    /**
     * @var array
     */
    protected $facetLanguages = [];

    /**
     * @var array
     */
    protected $excludedFields = [];

    /**
     * @var int
     */
    protected $siteId;

    /**
     * @var SiteRepresentation
     */
    protected $site;

    /**
     * The key is always trimmed.
     *
     * @param string $query
     * @return self
     */
    public function setQuery($query)
    {
        $this->query = trim((string) $query);
        return $this;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string[] $resources The resource types are "items" and "item_sets".
     * @return self
     */
    public function setResources($resources)
    {
        $this->resources = $resources;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * @param array $isPublic
     * @return self
     */
    public function setIsPublic($isPublic)
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsPublic()
    {
        return $this->isPublic;
    }

    /**
     * @param string $name
     * @param array|string $value
     * @return self
     */
    public function addFilter($name, $value)
    {
        $this->filters[$name][] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param string $name
     * @param string $start
     * @param string $end
     * @return self
     */
    public function addDateRangeFilter($name, $start, $end)
    {
        $this->dateRangeFilters[$name][] = [
            'start' => trim($start),
            'end' => trim($end),
        ];
        return $this;
    }

    /**
     * @return array
     */
    public function getDateRangeFilters()
    {
        return $this->dateRangeFilters;
    }

    /**
     * Add advanced filters, that work similarly to Omeka ones.
     *
     * Note: Some types and joiners may not be managed by the querier.
     *
     * @param string $name
     * @param string $value
     * @param string $type
     * @param string $joiner
     * @return self
     */
    public function addFilterQuery($name, $value, $type = 'in', $joiner = 'and')
    {
        $this->filterQueries[$name][] = [
            'value' => trim((string) $value),
            'type' => trim((string) $type),
            'joiner' => trim((string) $joiner),
        ];
        return $this;
    }

    /**
     * @return array
     */
    public function getFilterQueries()
    {
        return $this->filterQueries;
    }

    /**
     * @param string|null $sort The field and the direction ("asc" or "desc")
     * separated by a space. Null means no sort (default of the search engine).
     * @return self
     */
    public function setSort($sort)
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * @return string|null The field and the direction ("asc" or "desc")
     * separated by a space. Null means no sort (default of the search engine).
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $page
     * @param int $rowCount
     * @return self
     */
    public function setLimitPage($page, $rowCount)
    {
        $page = ($page > 0) ? $page : 1;
        $rowCount = ($rowCount > 0) ? $rowCount : 1;
        $this->limit = (int) $rowCount;
        $this->offset = (int) $rowCount * ($page - 1);
        return $this;
    }

    /**
     * @param string $field
     * @return self
     */
    public function addFacetField($field)
    {
        $this->facetFields[] = $field;
        return $this;
    }

    /**
     * Get the flat list of fields to use as facet.
     *
     * @return array
     */
    public function getFacetFields()
    {
        return $this->facetFields;
    }

    /**
     * @param int $facetLimit
     * @return self
     */
    public function setFacetLimit($facetLimit)
    {
        $this->facetLimit = (int) $facetLimit;
        return $this;
    }

    /**
     * @return int
     */
    public function getFacetLimit()
    {
        return $this->facetLimit;
    }

    /**
     * @param array $facetLanguages
     * @return \Search\Query
     */
    public function setFacetLanguages(array $facetLanguages)
    {
        $this->facetLanguages = $facetLanguages;
        return $this;
    }

    /**
     * @return array
     */
    public function getFacetLanguages()
    {
        return $this->facetLanguages;
    }

    /**
     * Exclude fields from main search query, for example to exclude full text.
     *
     * @param array $excludedFields
     * @return self
     */
    public function setExcludedFields(array $excludedFields)
    {
        $this->excludedFields = $excludedFields;
        return $this;
    }

    /**
     * @return array
     */
    public function getExcludedFields()
    {
        return $this->excludedFields;
    }

    /**
     * @param int $siteId
     * @return self
     */
    public function setSiteId($siteId)
    {
        $this->siteId = $siteId;
        return $this;
    }

    /**
     * @return int
     */
    public function getSiteId()
    {
        return $this->siteId;
    }

    public function jsonSerialize()
    {
        return [
            'query' => $this->getQuery(),
            'resources' => $this->getResources(),
            'is_public' => $this->getIsPublic(),
            'filters' => $this->getFilters(),
            'date_range_filters' => $this->getDateRangeFilters(),
            'filter_queries' => $this->getFilterQueries(),
            'sort' => $this->getSort(),
            'offset' => $this->getOffset(),
            'limit' => $this->getLimit(),
            'facet_fields' => $this->getFacetFields(),
            'facet_limit' => $this->getFacetLimit(),
            'facet_languages' => $this->getFacetLanguages(),
            'excluded_fields' => $this->getExcludedFields(),
            'site_id' => $this->getSiteId(),
        ];
    }

    /**
     * @deprecated 3.5.8 Use self::setSiteId() instead. Will be removed in 3.6.
     * @param SiteRepresentation $site
     * @return self
     */
    public function setSite(SiteRepresentation $site)
    {
        $this->site = $site;
        $this->siteId = $site->id();
        return $this;
    }

    /**
     * @deprecated 3.5.8 Use self::getSiteId() instead. Will be removed in 3.6.
     * @return \Omeka\Api\Representation\SiteRepresentation
     */
    public function getSite()
    {
        return $this->site;
    }
}
