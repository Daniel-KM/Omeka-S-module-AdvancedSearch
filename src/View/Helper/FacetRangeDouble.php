<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetRangeDouble extends AbstractFacet
{
    protected $partial = 'search/facet-range-double';

    /**
     * @param $options "min", "max", "step" are automatically appended.
     *
     * {@inheritDoc}
     * @see \AdvancedSearch\View\Helper\AbstractFacet::prepareFacetData()
     */
    protected function prepareFacetData(string $facetField, array $facetValues, array $options): array
    {
        $isFacetModeDirect = ($options['mode'] ?? '') === 'link';

        $options['min'] = ($options['min'] ?? '') === '' ? null : (string) $options['min'];
        $options['max'] = ($options['max'] ?? '') === '' ? null : (string) $options['max'];
        $options['step'] = empty($options['step']) ? null : (string) $options['step'];

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
