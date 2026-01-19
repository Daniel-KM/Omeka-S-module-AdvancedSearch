<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetRangeDouble extends AbstractFacet
{
    protected $partial = 'search/facet-range-double';

    /**
     * @param $options "min", "max", "step" are read from "attributes" and appended to options for the template.
     *
     * {@inheritDoc}
     * @see \AdvancedSearch\View\Helper\AbstractFacet::prepareFacetData()
     */
    protected function prepareFacetData(string $facetField, array $facetValues, array $options): array
    {
        $isFacetModeDirect = in_array($options['mode'] ?? null, ['link', 'js']);

        // Get the min/max/step from attributes (like filters) or from facet values.
        // Like any facets: they can be filtered or they can be all.
        $attributes = $options['attributes'] ?? [];

        $options['min'] = ($attributes['min'] ?? $options['min'] ?? '') === '' ? null : (string) ($attributes['min'] ?? $options['min']);
        $options['max'] = ($attributes['max'] ?? $options['max'] ?? '') === '' ? null : (string) ($attributes['max'] ?? $options['max']);
        $options['step'] = empty($attributes['step'] ?? $options['step'] ?? null) ? null : (string) ($attributes['step'] ?? $options['step']);

        // Option "first_digits" (or legacy "integer") extracts year from dates.
        // Enabled by default for RangeDouble facets.
        $formatInteger = ($options['first_digits'] ?? $options['integer'] ?? true) === true
            || in_array($options['first_digits'] ?? $options['integer'] ?? null, [1, '1', 'true'], true);

        if ($formatInteger) {
            $options['min'] = isset($options['min']) ? (int) $options['min'] : null;
            $options['max'] = isset($options['max']) ? (int) $options['max'] : null;
        }

        if (!isset($options['min']) || !isset($options['max'])) {
            $vals = array_column($facetValues, 'value');
            if ($formatInteger) {
                $vals = array_map('intval', $vals);
            }
            $options['min'] ??= min($vals);
            $options['max'] ??= max($vals);
        }

        // TODO Compute total of a numerical range when empty.
        $total = count($facetValues);

        // The list of values is useless in a range, so just prepare "from" and "to".

        $rangeFrom = ($this->queryBase['facet'][$facetField]['from'] ?? '') === '' ? null : (string) $this->queryBase['facet'][$facetField]['from'];
        $rangeTo = ($this->queryBase['facet'][$facetField]['to'] ?? '') === '' ? null : (string) $this->queryBase['facet'][$facetField]['to'];
        $urlFrom = null;
        $urlTo = null;

        if ($isFacetModeDirect) {
            if ($rangeFrom !== null) {
                $queryFromOrTo = $this->queryBase;
                unset($queryFromOrTo['facet'][$facetField]['from']);
                $urlFrom = $this->urlHelper->__invoke($this->route, $this->params, ['query' => $queryFromOrTo]);
            }
            if ($rangeTo !== null) {
                $queryFromOrTo = $this->queryBase;
                unset($queryFromOrTo['facet'][$facetField]['to']);
                $urlTo = $this->urlHelper->__invoke($this->route, $this->params, ['query' => $queryFromOrTo]);
            }
        }

        return [
            'name' => $facetField,
            'facetValues' => $facetValues,
            'options' => $options,
            'from' => $rangeFrom,
            'to' => $rangeTo,
            'fromUrl' => $urlFrom,
            'toUrl' => $urlTo,
            'total' => $total,
        ];
    }
}
