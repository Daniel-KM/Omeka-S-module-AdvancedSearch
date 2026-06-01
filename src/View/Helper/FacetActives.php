<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetActives extends AbstractFacet
{
    protected $partial = 'search/facet-actives';

    /**
     * Get complete data about active facets.
     *
     * @param array $options Unlike AbstractFacet, options are the full config
     * settings for facets, so contains the common options of facets and all
     * specific settings, label and types of all facets.
     *
     * @todo Separate FacetActives from AbstractFacet?
     */
    protected function prepareActiveFacetData(array $activeFacets, array $options): array
    {
        // $isFacetModeDirect = in_array($options['mode'] ?? null, ['link',
        // 'js']);

        // Prepend the "Refine search" query as an active facet so users can see
        // and clear it like any other filter.
        $refineValue = isset($this->queryBase['refine']) ? trim((string) $this->queryBase['refine']) : '';
        if ($refineValue !== '') {
            $refineQuery = $this->queryBase;
            unset($refineQuery['refine']);
            $refineLabel = !empty($options['label_refine'])
                ? (string) $this->translate->__invoke($options['label_refine'])
                : (string) $this->translate->__invoke('Refine search'); // @translate
            $activeFacets = ['refine' => ['' => [
                'value' => $refineValue,
                'count' => null,
                'label' => $refineValue,
                'active' => true,
                'url' => $this->urlHelper->__invoke($this->route, $this->params, ['query' => $refineQuery]),
                'fieldLabel' => $refineLabel,
            ]]] + $activeFacets;
        }

        foreach ($activeFacets as $facetName => &$facetValues) {
            if ($facetName === 'refine') {
                continue;
            }
            $facetFieldLabel = $options['facets'][$facetName]['label'] ?? $facetName;
            $valueLabels = \AdvancedSearch\Stdlib\SearchResources::resolveValueLabels(
                $options['facets'][$facetName] ?? [],
                $this->api
            );
            foreach ($facetValues as $facetKey => &$facetValue) {
                $facetValueValue = (string) $facetValue;
                if (array_key_exists($facetValueValue, $valueLabels) && $valueLabels[$facetValueValue] !== '') {
                    $facetValueLabel = (string) $this->translate->__invoke($valueLabels[$facetValueValue]);
                } else {
                    $facetValueLabel = (string) $this->facetValueLabel($facetName, $facetValue);
                }
                if (!strlen($facetValueLabel)) {
                    unset($activeFacets[$facetName][$facetKey]);
                    continue;
                }

                $facetValueValue = (string) $facetValue;
                $query = $this->queryBase;

                if (!isset($query['facet'][$facetName])
                    || array_search($facetValueValue, $query['facet'][$facetName]) === false
                ) {
                    unset($activeFacets[$facetName][$facetKey]);
                    continue;
                }

                $currentValues = $query['facet'][$facetName];

                // TODO Remove this filter to keep all active facet values?
                // Manage special filters with string keys, like Select range
                // with from/to. In that case, remove the specific key.
                $firstKey = key($currentValues);
                if (!is_numeric($firstKey)) {
                    $newValues = $currentValues;
                    unset($newValues[$facetKey]);
                    $newValues = array_filter($newValues, fn ($v) => $v !== null && $v !== '');
                } else {
                    $newValues = array_diff($currentValues, [$facetValueValue]);
                }

                $query['facet'][$facetName] = $newValues;

                // Set url in all cases, even when not used (not direct mode).
                $url = $this->urlHelper->__invoke($this->route, $this->params, ['query' => $query]);

                $facetValue = [
                    'value' => $facetValue,
                    'count' => null,
                    'label' => $facetValueLabel,
                    'active' => true,
                    'url' => $url,
                    'fieldLabel' => $facetFieldLabel,
                ];
            }
            unset($facetValue);
        }
        unset($facetValues);

        return $activeFacets;
    }
}
