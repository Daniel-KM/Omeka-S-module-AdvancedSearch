<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Omeka\View\Helper\Pagination;

/**
 * View helper for rendering pagination via advanced search, managing item set.
 */
class PaginationSearch extends Pagination
{
    /**
     * Unlike pagination, take care of pagination for item set.
     *
     * {@inheritDoc}
     * @see \Omeka\View\Helper\Pagination::getUrl()
     */
    protected function getUrl($page)
    {
        $view = $this->getView();
        $itemSet = $view->vars()->offsetGet('itemSet');
        if (!$itemSet) {
            return parent::getUrl($page);
        }

        $query = $view->params()->fromQuery();

        unset($query['item_set']);

        // Copy of parent method, except url.

        $query['page'] = (int) $page;
        // Do not emit sorts if the corresponding default sort flags are set.
        if (isset($query['sort_by_default'])) {
            unset($query['sort_by']);
        }
        if (isset($query['sort_order_default'])) {
            unset($query['sort_order']);
        }
        // Do not emit default sort flags.
        unset($query['sort_by_default']);
        unset($query['sort_order_default']);
        $options = ['query' => $query];
        if (is_string($this->fragment)) {
            $options['fragment'] = $this->fragment;
        }

        return $view->url('site/item-set', ['item-set-id' => $itemSet->id()], $options, true);
    }

    /**
     * Unlike pagination, take care of pagination for item set.
     *
     * {@inheritDoc}
     * @see \Omeka\View\Helper\Pagination::getPagelessUrl()
     */
    protected function getPagelessUrl()
    {
        $view = $this->getView();
        $itemSet = $view->vars()->offsetGet('itemSet');
        if (!$itemSet) {
            return parent::getPagelessUrl();
        }

        $query = $view->params()->fromQuery();

        unset($query['item_set']);

        // Copy of parent method, except url.

        unset($query['page']);
        $options = ['query' => $query];
        if (is_string($this->fragment)) {
            $options['fragment'] = $this->fragment;
        }

        return $view->url('site/item-set', ['item-set-id' => $itemSet->id()], $options, true);
    }
}
