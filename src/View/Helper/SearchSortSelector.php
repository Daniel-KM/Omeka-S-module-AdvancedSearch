<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Query;
use Laminas\Form\Element\Select;
use Laminas\View\Helper\AbstractHelper;

class SearchSortSelector extends AbstractHelper
{
    /**
     * @var string
     */
    protected $partial = 'search/sort-selector';

    /**
     * Option $asUrl allows to include the select in the main form or not.
     * When set, a js reloads the page directly.
     * Anyway, the js can rebuild the url from the values.
     * @todo To avoid a js, a css select (replace select by ul/li + a) can be created in theme.
     *
     * @todo Merge with Omeka SortSelector and SortLink?
     */
    public function __invoke(Query $query, array $options, $asUrl = false, ?string $partial = null, ?string $label = null): string
    {
        if (!count($options)) {
            return '';
        }

        /* @deprecated Since 3.5.23.3. Kept for old themes. */
        if (!is_array(reset($options))) {
            foreach ($options as $name => &$sortOption) {
                $sortOption = ['name' => $name, 'label' => $sortOption];
            }
            unset($sortOption);
        }

        $select = $asUrl
            ? $this->asUrl($query, $options)
            : $this->asForm($query, $options);

        $view = $this->getView();

        $label = is_null($label)
            ? $view->translate('Sort by') // @translate
            : $label;
        if ($label !== '') {
            $select
                ->setLabel($label);
        }

        return $view->partial($partial ?: $this->partial, [
            'query' => $query,
            'options' => $options,
            'select' => $select,
            'asUrl' => $asUrl,
            // Deprecated since 3.3.6.12.
            'sortOptions' => $options,
            'sortSelect' => $select,
            'asSortUrls' => $asUrl,
        ]);
    }

    protected function asForm(Query $query, array $options): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $translate = $plugins->get('translate');
        foreach ($options as $name => &$sortOption) {
            $sortOption = $sortOption['label'] ? $translate($sortOption['label']) : $name;
        }
        unset($sortOption);
        $options = array_map($translate, $options);
        return (new Select('sort'))
            ->setValueOptions($options)
            ->setValue($query->getSort());
    }

    protected function asUrl(Query $query, array $options): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $params = $plugins->get('params');
        $translate = $plugins->get('translate');
        $serverUrl = $plugins->get('serverUrl');

        // Prepare urls directly as values to avoid a click. Use current url for a quick build.
        $currentUrl = strtok($serverUrl(true), '?');
        $currentQuery = $params->fromQuery();
        $currentSort = $query->getSort();
        $optionsWithUrl = [];
        foreach ($options as $name => $sortOption) {
            $url = $currentUrl . '?' . http_build_query(['page' => 1, 'sort' => $name] + $currentQuery, '', '&', PHP_QUERY_RFC3986);
            $optionsWithUrl[$name] = [
                'value' => $name,
                // The label is automatically translated by Laminas.
                'label' => $sortOption['label'] ?: $name,
                'attributes' => [
                    'data-url' => $url,
                ],
            ];
        }

        return (new Select('sort'))
            ->setValueOptions($optionsWithUrl)
            ->setValue($currentSort);
    }
}
