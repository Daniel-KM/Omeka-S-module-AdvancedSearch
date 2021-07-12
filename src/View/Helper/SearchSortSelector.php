<?php declare(strict_types=1);

namespace Search\View\Helper;

use Laminas\Form\Element\Select;
use Laminas\View\Helper\AbstractHelper;
use Search\Query;

class SearchSortSelector extends AbstractHelper
{
    /**
     * @var string
     */
    protected $partial = 'search/sort-selector';

    /**
     * Option $asSortUrls allows to include the select in the main form or not.
     * When set, a js reloads the page directly.
     * Anyway, the js can rebuild the url from the values.
     *
     * @todo Merge with Omeka SortSelector and SortLink?
     */
    public function __invoke(Query $query, array $sortOptions, $asSortUrls = false, ?string $partial = null): string
    {
        if (empty($sortOptions)) {
            return '';
        }

        /** @deprecated Since 3.5.23.3. Kept for old themes. */
        if (!is_array(reset($sortOptions))) {
            foreach ($sortOptions as $name => &$sortOption) {
                $sortOption = ['name' => $name, 'label' => $sortOption];
            }
            unset($sortOption);
        }

        $select = $asSortUrls
            ? $this->asSortUrls($query, $sortOptions)
            : $this->asForm($query, $sortOptions);

        return $this->getView()->partial($partial ?: $this->partial, [
            'query' => $query,
            'sortOptions' => $sortOptions,
            'sortSelect' => $select,
            'asSortUrls' => $asSortUrls,
        ]);
    }

    protected function asForm(Query $query, array $sortOptions): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $translate = $plugins->get('translate');
        foreach ($sortOptions as &$sortOption) {
            $sortOption = $translate($sortOption['label']);
        }
        unset($sortOption);
        $sortOptions = array_map($translate, $sortOptions);
        return (new Select('sort'))
            ->setValueOptions($sortOptions)
            ->setValue($query->getSort())
            ->setLabel($translate('Sort by'));
    }

    protected function asSortUrls(Query $query, array $sortOptions): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $translate = $plugins->get('translate');
        $serverUrl = $plugins->get('serverUrl');
        $params = $plugins->get('params');

        // Prepare urls directly as values to avoid a click. Use current url for a quick build.
        $currentUrl = strtok($serverUrl(true), '?');
        $currentQuery = $params->fromQuery();
        $currentSort = $query->getSort();
        $sorts = $sortOptions;
        $sortOptions = [];
        $currentSortUrl = null;
        foreach ($sorts as $name => $sortOption) {
            $sortName = $currentUrl . '?' . http_build_query(['sort' => $name] + $currentQuery, '', '&', PHP_QUERY_RFC3986);
            if ($name === $currentSort) {
                $currentSortUrl = $sortName;
            }
            $sortOptions[$sortName] = $translate($sortOption['label']);
        }

        return (new Select('sort'))
            ->setValueOptions($sortOptions)
            ->setValue($currentSortUrl)
            ->setLabel($translate('Sort by'));
    }
}
