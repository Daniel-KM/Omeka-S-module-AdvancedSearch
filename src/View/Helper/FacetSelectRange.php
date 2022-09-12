<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetSelectRange extends AbstractFacet
{
    protected $partial = 'search/facet-select-range';

    protected function prepareFacetData(string $facetField, array $facetValues, array $options): array
    {
        $isFacetModeDirect = ($options['mode'] ?? '') === 'link';

        // It is simpler and better to get from/to from the query, because it
        // can manage discrete range.
        $rangeFrom = isset($this->queryBase['facet'][$facetField]['from']) && $this->queryBase['facet'][$facetField]['from'] !== ''
            ? $this->queryBase['facet'][$facetField]['from']
            : null;
        $rangeTo = isset($this->queryBase['facet'][$facetField]['to']) && $this->queryBase['facet'][$facetField]['to'] !== ''
            ? $this->queryBase['facet'][$facetField]['to']
            : null;

        $hasRangeFromOnly = !is_null($rangeFrom) && is_null($rangeTo);
        $hasRangeToOnly = is_null($rangeFrom) && !is_null($rangeTo);
        $hasRangeFull = !is_null($rangeFrom) && !is_null($rangeTo);
        $total = 0;

        foreach ($facetValues as /* $facetIndex => */ &$facetValue) {
            $query = $this->queryBase;
            $active = false;
            $urls = [
                'url' => '',
                'from' => '',
                'to' => '',
            ];

            $facetValueValue = (string) $facetValue['value'];
            $isFrom = $facetValueValue === $rangeFrom;
            $isTo = $facetValueValue === $rangeTo;
            $fromOrTo = $isFrom ? 'from' : ($isTo ? 'to' : null);

            // The facet value is compared against a string (the query args), not a numeric value.
            $facetValueLabel = (string) $this->facetValueLabel($facetField, $facetValueValue);
            if (strlen($facetValueLabel)) {
                if ($hasRangeFromOnly) {
                    $active = ($rangeFrom <=> $facetValueValue) <= 0;
                } elseif ($hasRangeToOnly) {
                    $active = ($facetValueValue <=> $rangeTo) <= 0;
                } elseif ($hasRangeFull) {
                    $active = ($rangeFrom <=> $facetValueValue) <= 0
                        && ($facetValueValue <=> $rangeTo) <= 0;
                }
                if ($active) {
                    $total += $facetValue['count'];
                }
                if ($isFacetModeDirect) {
                    if ($fromOrTo) {
                        // Prepare reset query.
                        $queryFromOrTo = $query;
                        unset($queryFromOrTo['facet'][$facetField][$fromOrTo]);
                        $urls['url'] = $this->urlHelper->__invoke($this->route, $this->params, ['query' => $queryFromOrTo]);
                        $urls[$fromOrTo] = $urls['url'];
                        // Prepare other query.
                        $queryToOrFrom = $query;
                        $toOrFrom = $fromOrTo === 'from' ? 'to' : 'from';
                        $queryToOrFrom['facet'][$facetField][$toOrFrom] = $facetValueValue;
                        $urls[$toOrFrom] = $this->urlHelper->__invoke($this->route, $this->params, ['query' => $queryToOrFrom]);
                    } else {
                        $query['facet'][$facetField]['__from_or_to__'] = $facetValueValue;
                        $urls['url'] = $this->urlHelper->__invoke($this->route, $this->params, ['query' => $query]);
                        $urls['from'] = str_replace('__from_or_to__', 'from', $urls['url']);
                        $urls['to'] = str_replace('__from_or_to__', 'to', $urls['url']);
                    }
                }
            }

            $facetValue['value'] = $facetValueValue;
            $facetValue['label'] = $facetValueLabel;
            $facetValue['active'] = $active;
            $facetValue['url'] = $urls['url'];
            $facetValue['url_from'] = $urls['from'];
            $facetValue['url_to'] = $urls['to'];
            $facetValue['is_from'] = $isFrom;
            $facetValue['is_to'] = $isTo;
        }
        unset($facetValue);

        return [
            'name' => $facetField,
            'facetValues' => $facetValues,
            'options' => $options,
            'from' => $rangeFrom,
            'to' => $rangeTo,
            'total' => $total,
        ];
    }
}
