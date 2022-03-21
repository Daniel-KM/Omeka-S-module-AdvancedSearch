<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Query;
use Laminas\View\Helper\AbstractHelper;

/**
 * View helper for rendering search filters for the advanced search response.
 */
class SearchingFilters extends AbstractHelper
{
    /**
     * Render filters from advanced search query.
     *
     * Wrapper on core helper searchFilters() in order to append the current
     * config. It allows to get specific arguments used by the form.
     *
     * @todo Should use the form adapter (but only main form is really used).
     * @see \AdvancedSearch\FormAdapter\AbstractFormAdapter
     *
     * @uses \Omeka\View\Helper\SearchFilters
     */
    public function __invoke(SearchConfigRepresentation $searchConfig, Query $query, array $options = []): string
    {
        $view = $this->getView();
        $template = $options['template'] ?? null;

        // TODO Use the managed query to get a clean query.
        $params = $view->params();
        $request = $params->fromQuery();

        // Don't display the current item set argument on item set page.
        $currentItemSet = (int) $view->params()->fromRoute('item-set-id');
        if ($currentItemSet) {
            foreach ($request as $key => $value) {
                // TODO Use the form adapter to get the real arg for the item set.
                if ($value && $key === 'item_set_id' || $key === 'item_set') {
                    if (is_array($value)) {
                        // Check if this is not a sub array (item_set[id][]).
                        $first = reset($value);
                        if (is_array($first)) {
                            $value = $first;
                        }
                        $pos = array_search($currentItemSet, $value);
                        if ($pos !== false) {
                            if (count($request[$key]) <= 1) {
                                unset($request[$key]);
                            } else {
                                unset($request[$key][$pos]);
                            }
                        }
                    } elseif ((int) $value === $currentItemSet) {
                        unset($request[$key]);
                    }
                    break;
                }
            }
        }

        $request['__searchConfig'] = $searchConfig;
        $request['__searchQuery'] = $query;

        return $view->searchFilters($template, $request);
    }
}
