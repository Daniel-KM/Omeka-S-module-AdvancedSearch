<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetSelectRange extends AbstractFacet
{
    protected $partial = 'search/facet-select-range';

    protected function prepareFacetData(string $facetField, array $facetValues, array $options): array
    {
        $isFacetModeDirect = in_array($options['mode'] ?? null, ['link', 'js']);

        // Get min/max from attributes (like filters).
        $attributes = $options['attributes'] ?? [];

        // Option "first_digits" (or legacy "integer") extracts year from dates.
        // Enabled by default for SelectRange facets.
        $formatInteger = ($options['first_digits'] ?? $options['integer'] ?? true) === true
            || in_array($options['first_digits'] ?? $options['integer'] ?? null, [1, '1', 'true'], true);

        // Convert facet values to integers (years) if first_digits is enabled.
        if ($formatInteger) {
            foreach ($facetValues as &$facetValue) {
                $facetValue['value'] = (int) $facetValue['value'];
            }
            unset($facetValue);
        }

        // It is simpler and better to get from/to from the query, because it
        // can manage discrete range. Check attributes first, then legacy options.
        $rangeFrom = $this->queryBase['facet'][$facetField]['from'] ?? $attributes['min'] ?? $options['min'] ?? null;
        $rangeFrom = $rangeFrom === '' ? null : $rangeFrom;
        $rangeTo = $this->queryBase['facet'][$facetField]['to'] ?? $attributes['max'] ?? $options['max'] ?? null;
        $rangeTo = $rangeTo === '' ? null : $rangeTo;

        $firstValue = count($facetValues) ? reset($facetValues) : null;

        if ($rangeFrom === null && $rangeTo === null) {
            $hasRangeFromOnly = false;
            $hasRangeToOnly = false;
            $hasRangeFull = false;
            $isNumericRange = is_numeric($firstValue);
        } elseif ($rangeTo === null) {
            $hasRangeFromOnly = true;
            $hasRangeToOnly = false;
            $hasRangeFull = false;
            $isNumericRange = is_numeric($rangeFrom);
        } elseif ($rangeFrom === null) {
            $hasRangeFromOnly = false;
            $hasRangeToOnly = true;
            $hasRangeFull = false;
            $isNumericRange = is_numeric($rangeTo);
        } else {
            $hasRangeFromOnly = false;
            $hasRangeToOnly = false;
            $hasRangeFull = true;
            $isNumericRange = is_numeric($rangeFrom) && is_numeric($rangeTo);
        }

        $total = 0;

        // Contains all values, with one active for "from" and one for "to".
        foreach ($facetValues as &$facetValue) {
            $query = $this->queryBase;
            $active = false;
            $urls = [
                'url' => '',
                'from' => '',
                'to' => '',
            ];

            $facetValueValue = (string) ($facetValue['value'] ?? '');
            $isFrom = $facetValueValue === $rangeFrom;
            $isTo = $facetValueValue === $rangeTo;
            $fromOrTo = $isFrom ? 'from' : ($isTo ? 'to' : null);

            $facetValueLabel = (string) $this->facetValueLabel($facetField, $facetValueValue);
            if (strlen($facetValueLabel)) {
                // Manage discrete values.
                // TODO Check if all this process is still needed.
                if ($isNumericRange) {
                    // For simplicity, use float to sort any number, even it is
                    // an integer in most of the cases.
                    if ($hasRangeFromOnly) {
                        $active = ((float) $rangeFrom <=> (float) $facetValueValue) <= 0;
                    } elseif ($hasRangeToOnly) {
                        $active = ((float) $facetValueValue <=> (float) $rangeTo) <= 0;
                    } elseif ($hasRangeFull) {
                        $active = ((float) $rangeFrom <=> (float) $facetValueValue) <= 0
                            && ((float) $facetValueValue <=> (float) $rangeTo) <= 0;
                    }
                } else {
                    // The facet value is compared against a string (the query
                    // args), not a numeric value.
                    if ($hasRangeFromOnly) {
                        $active = ($rangeFrom <=> $facetValueValue) <= 0;
                    } elseif ($hasRangeToOnly) {
                        $active = ($facetValueValue <=> $rangeTo) <= 0;
                    } elseif ($hasRangeFull) {
                        $active = ($rangeFrom <=> $facetValueValue) <= 0
                            && ($facetValueValue <=> $rangeTo) <= 0;
                    }
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
                        $urls['from'] = strtr($urls['url'], ['__from_or_to__' => 'from']);
                        $urls['to'] = strtr($urls['url'], ['__from_or_to__' => 'to']);
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
            'total' => $total,
        ];
    }
}
