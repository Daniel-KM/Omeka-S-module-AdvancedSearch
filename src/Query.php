<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2022
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

/**
 * @todo Replace by the solarium query, that manages everything and can be used by mysql too!
 */
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
     * @var array
     */
    protected $hiddenQueryFilters = [];

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
    protected $facets = [];

    /**
     * @var int
     * @deprecated Use individual facet array. Will be removed in a future version.
     */
    protected $facetLimit = 0;

    /**
     * @var string
     * @deprecated Use individual facet array. Will be removed in a future version.
     */
    protected $facetOrder = '';

    /**
     * @var array
     * @deprecated Use individual facet array. Will be removed in a future version.
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
     * The query should be stringable and is always trimmed.
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

    public function setHiddenQueryFilters(array $hiddenQueryFilters): self
    {
        $this->hiddenQueryFilters = $hiddenQueryFilters;
        return $this;
    }

    public function getHiddenQueryFilters(): array
    {
        return $this->hiddenQueryFilters;
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

    /**
     * Add facet fields and params.
     *
     * @param array $facetFields Key is the field name and values are the
     * details of the facet:
     * - field: the name
     * - type: Checkbox, Select, SelectRange
     * - order
     * - limit
     * - languages
     * - start for range facets
     * - end for range facets
     * - etc.
     * Other keys may be managed via module Search Solr, but not internal sql.
     * No check is done here.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/faceting.html
     */
    public function setFacets(array $facetFields): self
    {
        $this->facets = $facetFields;
        return $this;
    }

    /**
     * Add a facet with its name.
     *
     * It will override a facet with the same name.
     * The option should contain the key "field" with the name.
     * No check is done here.
     */
    public function addFacet(string $facetField, array $options = []): self
    {
        $this->facets[$facetField] = $options;
        return $this;
    }

    /**
     * Get the list of fields and options to use as facet.
     */
    public function getFacets(): array
    {
        return $this->facets;
    }

    /**
     * Get options to use for a facet.
     */
    public function getFacet(string $facetField): ?array
    {
        return $this->facets[$facetField] ?? null;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function addFacetFields(array $facetFields): self
    {
        $this->facets = [];
        foreach ($facetFields as $facetField) {
            $facet = [
                'field' => $facetField,
                'limit' => $this->facetLimit,
                'order' => $this->facetOrder,
                'languages' => $this->facetLanguages,
            ];
            $this->facets[$facetField] = $facet;
        }
        return $this;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function addFacetField(string $facetField): self
    {
        $facet = [
            'field' => $facetField,
            'limit' => $this->facetLimit,
            'order' => $this->facetOrder,
            'languages' => $this->facetLanguages,
        ];
        $this->facets[$facetField] = $facet;
        return $this;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function getFacetFields(): array
    {
        return array_column($this->facets, 'field');
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function setFacetLimit(?int $facetLimit): self
    {
        $this->facetLimit = (int) $facetLimit;
        return $this;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function getFacetLimit(): int
    {
        return $this->facetLimit;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function setFacetOrder(?string $facetOrder): self
    {
        $this->facetOrder = (string) $facetOrder;
        return $this;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function getFacetOrder(): string
    {
        return $this->facetOrder;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function setFacetLanguages(array $facetLanguages): self
    {
        $this->facetLanguages = $facetLanguages;
        return $this;
    }

    /**
     * @deprecated Use facet fields array. Will be removed in a future version.
     */
    public function getFacetLanguages(): array
    {
        return $this->facetLanguages;
    }

    public function setActiveFacets(array $activeFacets): self
    {
        $this->activeFacets = $activeFacets;
        return $this;
    }

    public function addActiveFacet(string $facetField, $value): self
    {
        $this->activeFacets[$facetField][] = $value;
        return $this;
    }

    public function addActiveFacetRange(string $facetField, $from, $to): self
    {
        $this->activeFacets[$facetField]['from'] = $from === '' ? null : $from;
        $this->activeFacets[$facetField]['to'] = $to === '' ? null : $to;
        return $this;
    }

    public function getActiveFacets(): array
    {
        return $this->activeFacets;
    }

    public function getActiveFacet(string $facetField): ?array
    {
        return $this->activeFacets[$facetField] ?? null;
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
     * Check for a simple browse: no query, no filters and no facets.
     *
     * This is not the inverse of isSearchQuery(): unlike isSearchQuery(),
     * facets are taken into a account.
     */
    public function isBrowse(): bool
    {
        return $this->getQuery() === ''
            && $this->getFilters() === []
            && $this->getDateRangeFilters() === []
            && $this->getFilterQueries() === []
            && $this->getActiveFacets() === []
        ;
    }

    /**
     * Check if the query is filled, except public, pagination, sort and filters.
     *
     * This is not the inverse of isBrowse(): unlike isBrowse(), facets are not
     * taken into a account.
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
            'hidden_query_filters' => $this->getHiddenQueryFilters(),
            'sort' => $this->getSort(),
            'page' => $this->getPage(),
            'offset' => $this->getOffset(),
            'limit' => $this->getLimit(),
            'facets' => $this->getFacets(),
            // Deprecated "facet_fields", "facet_limit", "facet_languages".
            'facet_fields' => $this->getFacetFields(),
            'facet_limit' => $this->getFacetLimit(),
            'facet_languages' => $this->getFacetLanguages(),
            'active_facets' => $this->getActiveFacets(),
            'suggest_options' => $this->getSuggestOptions(),
            'suggest_fields' => $this->getSuggestFields(),
            'excluded_fields' => $this->getExcludedFields(),
            'site_id' => $this->getSiteId(),
            'deprecated' => [
                'facet_fields',
                'facet_limit',
                'facet_languages',
            ],
        ];
    }
}
