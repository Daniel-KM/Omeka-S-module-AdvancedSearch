<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2021
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
namespace AdvancedSearch;

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
    protected $page = 0;

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
     * @var string
     */
    protected $facetOrder = '';

    /**
     * @var array
     */
    protected $facetLanguages = [];

    /**
     * @var array
     */
    protected $activeFacets = [];

    /**
     * @var array
     */
    protected $excludedFields = [];

    /**
     * @var array
     */
    protected $suggestOptions = [
        'suggester' => 0,
        'direct' => false,
        'mode_index' => 'start',
        'mode_search' => 'start',
        'length' => 50,
    ];

    /**
     * @var array
     */
    protected $suggestFields = [];

    /**
     * @var int
     */
    protected $siteId;

    /**
     * The key is always trimmed and it is always a stringed.
     */
    public function setQuery($query): self
    {
        $this->query = trim((string) $query);
        return $this;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @param string[] $resources The types are generally "items" and "item_sets".
     */
    public function setResources(array $resources): self
    {
        $this->resources = $resources;
        return $this;
    }

    /**
     * @param string $resources Generally "items" or "item_sets".
     */
    public function addResource(string $resource): self
    {
        $this->resources[] = $resource;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    public function setIsPublic($isPublic): self
    {
        $this->isPublic = (bool) $isPublic;
        return $this;
    }

    public function getIsPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * @todo Support multi-fields (name).
     * @param array|string $value
     */
    public function addFilter(string $name, $value): self
    {
        $this->filters[$name][] = $value;
        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @todo Support multi-fields (name).
     */
    public function addDateRangeFilter(string $name, string $from, string $to): self
    {
        $this->dateRangeFilters[$name][] = [
            'from' => trim($from),
            'to' => trim($to),
        ];
        return $this;
    }

    public function getDateRangeFilters(): array
    {
        return $this->dateRangeFilters;
    }

    /**
     * Add advanced filters, that work similarly to Omeka ones.
     *
     * Note: Some types and joiners may not be managed by the querier.
     * @todo Support multi-fields (name).
     */
    public function addFilterQuery(string $name, $value, ?string $type = 'in', ?string $join = 'and'): self
    {
        $this->filterQueries[$name][] = [
            'value' => trim((string) $value),
            'type' => trim((string) $type),
            'join' => trim((string) $join),
        ];
        return $this;
    }

    public function getFilterQueries(): array
    {
        return $this->filterQueries;
    }

    /**
     * @param string|null $sort The field and the direction ("asc" or "desc")
     * separated by a space. Null means no sort (default of the search engine).
     */
    public function setSort(?string $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    public function getSort(): ?string
    {
        return $this->sort;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Alias of getLimit().
     * @uses self::getLimit()
     */
    public function getPerPage(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimitPage(?int $page, ?int $rowCount): self
    {
        $this->page = $page > 0 ? $page : 1;
        $this->limit = $rowCount > 0 ? $rowCount : 1;
        $this->offset = $this->limit * ($this->page - 1);
        return $this;
    }

    public function setLimitOffset(?int $offset, ?int $rowCount): self
    {
        $this->offset = $offset >= 0 ? (int) $offset : 0;
        $this->limit = $rowCount > 0 ? $rowCount : 1;
        $this->page = (int) floor($this->offset - 1 / $this->limit);
        return $this;
    }

    public function addFacetFields(array $facetFields): self
    {
        $this->facetFields = $facetFields;
        return $this;
    }

    public function addFacetField(string $facetField): self
    {
        $this->facetFields[] = $facetField;
        return $this;
    }

    /**
     * Get the flat list of fields to use as facet.
     */
    public function getFacetFields(): array
    {
        return $this->facetFields;
    }

    public function setFacetLimit(?int $facetLimit): self
    {
        $this->facetLimit = (int) $facetLimit;
        return $this;
    }

    public function getFacetLimit(): int
    {
        return $this->facetLimit;
    }

    public function setFacetOrder(?string $facetOrder): self
    {
        $this->facetOrder = (string) $facetOrder;
        return $this;
    }

    public function getFacetOrder(): string
    {
        return $this->facetOrder;
    }

    public function setFacetLanguages(array $facetLanguages): self
    {
        $this->facetLanguages = $facetLanguages;
        return $this;
    }

    public function getFacetLanguages(): array
    {
        return $this->facetLanguages;
    }

    public function setActiveFacets(array $activeFacets): self
    {
        $this->activeFacets = $activeFacets;
        return $this;
    }

    public function addActiveFacet(string $name, $value): self
    {
        $this->activeFacets[$name][] = $value;
        return $this;
    }

    public function getActiveFacets(): array
    {
        return $this->activeFacets;
    }

    /**
     * Available options are (internal engine when direct (without index)):
     * - suggester: id of the suggester
     * - direct: direct query without the index (default false)
     * - mode_index: "start" (default) or "contain"
     * - mode_search: "start" (default) or "contain"
     * - length: max size of a string (default 50)
     */
    public function setSuggestOptions(array $suggestOptions): self
    {
        $this->suggestOptions = $suggestOptions;
        return $this;
    }

    public function getSuggestOptions(): array
    {
        return $this->suggestOptions;
    }

    public function setSuggestFields(array $suggestFields): self
    {
        $this->suggestFields = $suggestFields;
        return $this;
    }

    public function getSuggestFields(): array
    {
        return $this->suggestFields;
    }

    /**
     * Exclude fields from main search query, for example to exclude full text.
     * It is used for suggest queries too.
     */
    public function setExcludedFields(array $excludedFields): self
    {
        $this->excludedFields = $excludedFields;
        return $this;
    }

    public function getExcludedFields(): array
    {
        return $this->excludedFields;
    }

    public function setSiteId(?int $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
    }

    public function getSiteId(): ?int
    {
        return $this->siteId;
    }

    /**
     * Check if the query is filled, except public, pagination, sort and facets.
     */
    public function isSearchQuery(): bool
    {
        return $this->getQuery() !== ''
            || $this->getFilters() !== []
            || $this->getDateRangeFilters() !== []
            || $this->getFilterQueries() !== []
        ;
    }

    public function jsonSerialize(): array
    {
        return [
            'query' => $this->getQuery(),
            'resources' => $this->getResources(),
            'is_public' => $this->getIsPublic(),
            'filters' => $this->getFilters(),
            'date_range_filters' => $this->getDateRangeFilters(),
            'filter_queries' => $this->getFilterQueries(),
            'sort' => $this->getSort(),
            'page' => $this->getPage(),
            'offset' => $this->getOffset(),
            'limit' => $this->getLimit(),
            'facet_fields' => $this->getFacetFields(),
            'facet_limit' => $this->getFacetLimit(),
            'facet_languages' => $this->getFacetLanguages(),
            'active_facets' => $this->getActiveFacets(),
            'suggest_options' => $this->getSuggestOptions(),
            'suggest_fields' => $this->getSuggestFields(),
            'excluded_fields' => $this->getExcludedFields(),
            'site_id' => $this->getSiteId(),
        ];
    }
}
