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

        // Normalize scale breakpoints from ArrayTextarea storage (associative
        // array value => position as strings) to a list of [value, position]
        // pairs sorted by value. Resolve "min" / "max" placeholders to the
        // domain extremes.
        $scaleMode = $options['scale_mode'] ?? 'linear';
        if ($scaleMode === 'piecewise' && !empty($options['scale_breakpoints']) && is_array($options['scale_breakpoints'])) {
            $domainMin = is_numeric($options['min'] ?? null) ? (float) $options['min'] : null;
            $domainMax = is_numeric($options['max'] ?? null) ? (float) $options['max'] : null;
            $pairs = [];
            foreach ($options['scale_breakpoints'] as $key => $value) {
                if (is_array($value) && count($value) === 2) {
                    $rawValue = $value[0];
                    $pos = is_numeric($value[1]) ? (float) $value[1] : null;
                } else {
                    $rawValue = $key;
                    $pos = is_numeric($value) ? (float) $value : null;
                }
                if ($pos === null) {
                    continue;
                }
                if ($rawValue === 'min') {
                    if ($domainMin === null) {
                        continue;
                    }
                    $pairs[] = [$domainMin, $pos];
                } elseif ($rawValue === 'max') {
                    if ($domainMax === null) {
                        continue;
                    }
                    $pairs[] = [$domainMax, $pos];
                } elseif (is_numeric($rawValue)) {
                    $pairs[] = [(float) $rawValue, $pos];
                }
            }
            usort($pairs, fn ($a, $b) => $a[0] <=> $b[0]);
            $options['scale_breakpoints'] = $pairs;
            if (count($pairs) < 2) {
                $options['scale_mode'] = 'linear';
                $options['scale_breakpoints'] = [];
            }
        } else {
            $options['scale_mode'] = 'linear';
            $options['scale_breakpoints'] = [];
        }

        // TODO Compute total of a numerical range when empty.
        $total = count($facetValues);

        // The list of values is useless in a range, so just prepare "from" and "to".

        $rangeFrom = ($this->queryBase['facet'][$facetField]['from'] ?? '') === '' ? null : (string) $this->queryBase['facet'][$facetField]['from'];
        $rangeTo = ($this->queryBase['facet'][$facetField]['to'] ?? '') === '' ? null : (string) $this->queryBase['facet'][$facetField]['to'];
        $urlFrom = null;
        $urlTo = null;

        if ($isFacetModeDirect) {
            // Always provide a base url (without current from/to) so the js
            // submit handler can build a new query on first use.
            $queryBaseRange = $this->queryBase;
            unset($queryBaseRange['facet'][$facetField]['from']);
            unset($queryBaseRange['facet'][$facetField]['to']);
            $urlBase = $this->urlHelper->__invoke($this->route, $this->params, ['query' => $queryBaseRange]);
            $urlFrom = $urlBase;
            $urlTo = $urlBase;
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
