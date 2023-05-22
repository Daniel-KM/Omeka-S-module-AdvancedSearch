<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetActives extends AbstractFacet
{
    protected $partial = 'search/facet-actives';

    /**
     * Get complete data about active facets.
     *
     * The options are the search page settings for facets, so contains all names
     * , label and types of all facets.
     */
    protected function prepareActiveFacetData(array $activeFacets, array $options): array
    {
        $isFacetModeDirect = ($options['mode'] ?? '') === 'link';

        // Normally it is useless to use facetLabel() with options.
        /** @var \AdvancedSearch\View\Helper\FacetLabel $facetLabel */
        $facetLabel = $this->getView()->getHelperPluginManager()->get('facetLabel');

        foreach ($activeFacets as $facetField => &$facetValues) {
            $facetFieldLabel = $options['facets'][$facetField]['label'] ?? $facetLabel($facetField);
            foreach ($facetValues as $facetKey => &$facetValue) {
                $facetValueLabel = (string) $this->facetValueLabel($facetField, $facetValue);
                if (!strlen($facetValueLabel)) {
                    unset($activeFacets[$facetField][$facetKey]);
                    continue;
                }

                $facetValueValue = (string) $facetValue;
                $query = $this->queryBase;

                if (!isset($query['facet'][$facetField]) || array_search($facetValueValue, $query['facet'][$facetField]) === false) {
                    continue;
                }

                $values = $query['facet'][$facetField];
                // TODO Remove this filter to keep all active facet values?
                $values = array_filter($values, function ($v) use ($facetValueValue) {
                    return $v !== $facetValueValue;
                });
                $query['facet'][$facetField] = $values;

                $url = $isFacetModeDirect ? $this->urlHelper->__invoke($this->route, $this->params, ['query' => $query]) : '';

                $facetValue = [
                    'value' => $facetValue,
                    'count' => null,
                    'label' => $facetValueLabel,
                    'active' => true,
                    'url' => $url,
                    'fieldLabel' => $facetFieldLabel,
                ];
            }
        }

        return $activeFacets;
    }
}
