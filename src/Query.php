<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2026
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

use AdvancedSearch\Querier\QuerierInterface;
use JsonSerializable;

/**
 * @todo Replace by the solarium query, that manages everything and can be used by mysql too!
 */
class Query implements JsonSerializable
{
    /**
     * @var \AdvancedSearch\Querier\QuerierInterface
     */
    protected $querier;

    /**
     * @var string
     */
    protected $query = '';

    /**
     * @var string
     */
    protected $queryDefaultField = '';

    /**
     * @var string
     */
    protected $queryRefine = '';

    /**
     * @var string[]
     */
    protected $resourceTypes = [];

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @var array
     */
    protected $fieldBoosts = [];

    /**
     * @var array
     */
    protected $fieldsQueryArgs = [];

    /**
     * @var bool
     */
    protected $isPublic = true;

    /**
     * @deprecated Will be removed in a future version.
     */
    protected $recordOrFullText = 'all';

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var array
     */
    protected $filtersRange = [];

    /**
     * @var array
     */
    protected $filtersQuery = [];

    /**
     * @var array
     */
    protected $filtersQueryHidden = [];

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
     * @var array
     */
    protected $activeFacets = [];

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
     * @var array
     */
    protected $excludedFields = [];

    /**
     * @var int
     */
    protected $siteId;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * The querier allows to do some requests directly, lately or on demand.
     *
     * The querier should be the prepared one, with the prepared query stored.
     */
    public function setQuerier(?QuerierInterface $querier): self
    {
        $this->querier = $querier;
        return $this;
    }

    /**
     * Get the querier used to prepare this query, with prepared data stored.
     */
    public function getQuerier(): ?QuerierInterface
    {
        return $this->querier;
    }

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
     * The query default field should be a string and is always trimmed.
     */
    public function setQueryDefaultField($queryDefaultField): self
    {
        $this->queryDefaultField = trim((string) $queryDefaultField);
        return $this;
    }

    public function getQueryDefaultField(): string
    {
        return $this->queryDefaultField;
    }

    /**
     * The query used to refine should be stringable and is always trimmed.
     *
     * This type of query may be used with the facets.
     */
    public function setQueryRefine($queryRefine): self
    {
        $this->queryRefine = trim((string) $queryRefine);
        return $this;
    }

    public function getQueryRefine(): string
    {
        return $this->queryRefine;
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
     * @param string $resourceType Generally "items" or "item_sets".
     */
    public function addResourceType(string $resourceType): self
    {
        $this->resourceTypes[] = $resourceType;
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
     * Allow to manage one or multiple indexes with one alias name.
     *
     * Aliases allows to manage aggregated fields.
     *
     *  With internal engine, the fields are a list of properties.
     *  With Solr, aliases allow to avoid to use native index names directly.
     *  Solr doesn't need aggregated fields: all indexes can be built with
     *  multiple fields. Nevertheless, Solr may need multiple indexes (facet,
     *  sort, etc.) for the same metadata.
     *
     * @param array $aliases Associative array where the key is the alias name
     * and the value is an array containing three keys for name, label, and
     * fields. The fields are a list of native indexes.
     */
    public function setAliases(array $aliases): self
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * Set an alias, overriding the existing alias with the same name.
     */
    public function setAlias(string $alias, array $data): self
    {
        $this->aliases[$alias] = $data;
        return $this;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function getAlias(string $alias): ?array
    {
        return $this->aliases[$alias] ?? null;
    }

    /**
     * Increase or decrease the importance of fields
     *
     * @param array $fieldBoosts Boost is a positive float multiplier, by index.
     */
    public function setFieldBoosts(array $fieldBoosts): self
    {
        $this->fieldBoosts = $fieldBoosts;
        return $this;
    }

    public function getFieldBoosts(): array
    {
        return $this->fieldBoosts;
    }

    /**
     * Allow to manage a list of simple query arguments with a specific query.
     *
     * For example "author[]=Bossuet&author[]=Rabelais" can be expanded to:
     * ```
     *  filter => [[
     *      join => and,
     *      field => [
     *          dcterms:creator,
     *          dcterms:contributor,
     *      ],
     *      type => res,
     *      val => [
     *          Bossuet,
     *          Rabelais,
     *       ],
     *  ]]
     * ```
     * The default expansion is: join = and, type = eq.
     */
    public function setFieldsQueryArgs(array $fieldsQueryArgs): self
    {
        $this->fieldsQueryArgs = $fieldsQueryArgs;
        return $this;
    }

    public function getFieldsQueryArgs(): array
    {
        return $this->fieldsQueryArgs;
    }

    public function getFieldQueryArgs(string $field): ?array
    {
        return $this->fieldsQueryArgs[$field] ?? null;
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
     * @deprecated Will be removed in a future version: use a standard filter or excluded fields.
     */
    public function setRecordOrFullText(?string $recordOrFullText): self
    {
        $this->recordOrFullText = $recordOrFullText === 'record' ? 'record' : 'all';
        return $this;
    }

    /**
     * @deprecated Will be removed in a future version: use a standard filter or excluded fields.
     */
    public function getRecordOrFullText(): string
    {
        return $this->recordOrFullText;
    }

    /**
     * @param array|string $value
     * @deprecated Use addFilterQuery().
     */
    public function addFilter(string $name, $value): self
    {
        $this->filters[$name][] = $value;
        return $this;
    }

    /**
     * @return array
     * @deprecated Use getFiltersQuery().
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @todo Support multi-fields (name).
     * @todo Merge addFilterRange with addFilterQuery().
     */
    public function addFilterRange(string $name, string $from, string $to): self
    {
        $this->filtersRange[$name][] = [
            'from' => trim($from),
            'to' => trim($to),
        ];
        return $this;
    }

    public function getFiltersRange(): array
    {
        return $this->filtersRange;
    }

    /**
     * Add advanced filters, that work similarly to "filter" in SearchResources.
     *
     * Unlike SearchResources, the list is grouped by field first.
     *
     * @todo Manage filters like the SearchResources (with compatibility for old themes)?
     *
     * Note: Some types and joiners may not be managed by the querier.
     * @todo Support multi-fields (name) (but useless with aliases).
     * @todo Add other keys: except, datatype (but useless with indexes and aliases). Only for internal?
     */
    public function addFilterQuery(string $name, $val, ?string $type = 'in', ?string $join = 'and'): self
    {
        $this->filtersQuery[$name][] = [
            'join' => trim((string) $join),
            'type' => trim((string) $type),
            'val' => is_array($val) ? array_map('trim', array_map('strval', $val)) : trim((string) $val),
        ];
        return $this;
    }

    public function getFiltersQuery(): array
    {
        return $this->filtersQuery;
    }

    public function setFiltersQueryHidden(array $filtersQueryHidden): self
    {
        $this->filtersQueryHidden = $filtersQueryHidden;
        return $this;
    }

    public function getFiltersQueryHidden(): array
    {
        return $this->filtersQueryHidden;
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
     * - field: the index to use
     * - label
     * - type: Checkbox, Select, RangeDouble, Thesaurus, Tree, etc.
     * - order
     * - limit
     * - languages
     * - data_types
     * - main_types
     * - values
     * - display_count
     * - start for range facets
     * - end for range facets
     * - step for range facets
     * - thesaurus for thesaurus facets.
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
    public function addFacet(string $facetName, array $options = []): self
    {
        $this->facets[$facetName] = $options;
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
    public function getFacet(string $facetName): ?array
    {
        return $this->facets[$facetName] ?? null;
    }

    public function setActiveFacets(array $activeFacets): self
    {
        $this->activeFacets = $activeFacets;
        return $this;
    }

    public function addActiveFacet(string $facetName, $value): self
    {
        $this->activeFacets[$facetName][] = $value;
        return $this;
    }

    public function addActiveFacetRange(string $facetName, $from, $to): self
    {
        $this->activeFacets[$facetName]['from'] = $from === '' ? null : $from;
        $this->activeFacets[$facetName]['to'] = $to === '' ? null : $to;
        return $this;
    }

    public function getActiveFacets(): array
    {
        return $this->activeFacets;
    }

    public function getActiveFacet(string $facetName): ?array
    {
        return $this->activeFacets[$facetName] ?? null;
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
     * @todo Clarify if excluded fields should be set separately for filters and facets.
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
     * @experimental Used to pass the display list mode for facets and the mode
     * the fulltext search of the internal engine.
     */
    public function setOption(string $key, $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
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
            && $this->getFiltersRange() === []
            && $this->getFiltersQuery() === []
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
            || $this->getFiltersRange() !== []
            || $this->getFiltersQuery() !== []
        ;
    }

    public function jsonSerialize(): array
    {
        return [
            'query' => $this->getQuery(),
            'query_refine' => $this->getQueryRefine(),
            'query_default_field' => $this->getQueryDefaultField(),
            'resource_types' => $this->getResourceTypes(),
            'by_resource_type' => $this->getByResourceType(),
            'aliases' => $this->getAliases(),
            'fields_query_args' => $this->getFieldsQueryArgs(),
            'is_public' => $this->getIsPublic(),
            'filters' => $this->getFilters(),
            'filters_range' => $this->getFiltersRange(),
            'filters_query' => $this->getFiltersQuery(),
            'filters_query_hidden' => $this->getFiltersQueryHidden(),
            'sort' => $this->getSort(),
            'page' => $this->getPage(),
            'offset' => $this->getOffset(),
            'limit' => $this->getLimit(),
            'facets' => $this->getFacets(),
            'active_facets' => $this->getActiveFacets(),
            'suggest_options' => $this->getSuggestOptions(),
            'suggest_fields' => $this->getSuggestFields(),
            'excluded_fields' => $this->getExcludedFields(),
            'site_id' => $this->getSiteId(),
            'options' => $this->options,
        ];
    }
}
