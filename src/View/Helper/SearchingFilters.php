<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Query;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;

/**
 * View helper for rendering search filters for the advanced search response.
 *
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
        $request = $view->params()->fromQuery();
        $request['__searchConfig'] = $searchConfig;
        $request['__searchQuery'] = $query;

        return $view->searchFilters($template, $request);
    }
}
