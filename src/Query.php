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
     * @var string
     */
    protected $suggestMode = 'start';

    /**
     * @var array
     */
    protected $suggestFields = [];

    /**
     * @var int
     */
    protected $siteId;

    /**
     * @var SiteRepresentation
     */
    protected $site;

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
    public function setResources($resources): self
    {
        $this->resources = $resources;
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

    public function addDateRangeFilter(string $name, string $start, string $end): self
    {
        $this->dateRangeFilters[$name][] = [
            'start' => trim($start),
            'end' => trim($end),
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

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimitPage(?int $searchConfig, ?int $rowCount): self
    {
        $searchConfig = ($searchConfig > 0) ? $searchConfig : 1;
        $rowCount = ($rowCount > 0) ? $rowCount : 1;
        $this->limit = (int) $rowCount;
        $this->offset = (int) $rowCount * ($searchConfig - 1);
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
     * Exclude fields from main search query, for example to exclude full text.
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

    public function setSuggestMode(string $suggestMode): self
    {
        $this->suggestMode = $suggestMode;
        return $this;
    }

    public function getSuggestMode(): string
    {
        return $this->suggestMode;
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

    public function setSiteId(?int $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
    }

    public function getSiteId(): ?int
    {
        return $this->siteId;
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
            'offset' => $this->getOffset(),
            'limit' => $this->getLimit(),
            'facet_fields' => $this->getFacetFields(),
            'facet_limit' => $this->getFacetLimit(),
            'facet_languages' => $this->getFacetLanguages(),
            'active_facets' => $this->getActiveFacets(),
            'excluded_fields' => $this->getExcludedFields(),
            'suggest_mode' => $this->getSuggestMode(),
            'suggest_fields' => $this->getSuggestFields(),
            'site_id' => $this->getSiteId(),
        ];
    }

    /**
     * @deprecated 3.5.8 Use self::setSiteId() instead. Will be removed in 3.6.
     */
    public function setSite(SiteRepresentation $site): self
    {
        $this->site = $site;
        $this->siteId = $site->id();
        return $this;
    }

    /**
     * @deprecated 3.5.8 Use self::getSiteId() instead. Will be removed in 3.6.
     */
    public function getSite(): SiteRepresentation
    {
        return $this->site;
    }
}
