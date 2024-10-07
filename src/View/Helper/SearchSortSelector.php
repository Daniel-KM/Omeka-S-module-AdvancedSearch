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
     * Create the search sort selector for the advanced search results.
     *
     * @todo Merge with Omeka SortSelector and SortLink?
     *
     * @var array|bool $params If boolean, the option is "$asUrl" and next
     *   arguments are used. If array, may be:
     *   - as_url (bool): when set, a js reloads the page directly. Anyway, the
     *     js can rebuild the url from the values.
     *   - label (string|null): the label of the select.
     *   - template (string): the partial to use.
     * @var string $partial Deprecated: use $params['template'].
     * @var string $label Deprecated: use $params['label'].
     *
     * @todo To avoid a js, a css select (replace select by ul/li + a) can be created in theme.
     */
    public function __invoke(Query $query, array $valueOptions, $params = [], ?string $partial = null, ?string $label = null): string
    {
        if (!count($valueOptions)) {
            return '';
        }

        if (is_array($params)) {
            $params += [
                'label' => $label,
                'as_url' => false,
                'template' => $partial,
            ];
        } else {
            $params = [
                'label' => $label,
                'as_url' => (bool) $params,
                'template' => $partial,
            ];
        }

        $view = $this->getView();

        $select = $params['as_url']
            ? $this->asUrl($query, $valueOptions)
            : $this->asForm($query, $valueOptions);

        $label = $params['label'] ?? null;
        $select
            ->setLabel($label);

        return $view->partial($params['template'] ?: $this->partial, [
            'query' => $query,
            'select' => $select,
            'params' => $params,
        ]);
    }

    protected function asForm(Query $query, array $valueOptions): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $translate = $plugins->get('translate');
        foreach ($valueOptions as $name => &$sortOption) {
            $sortOption = $sortOption['label'] ? $translate($sortOption['label']) : $name;
        }
        unset($sortOption);
        $valueOptions = array_map($translate, $valueOptions);
        return (new Select('sort'))
            ->setValueOptions($valueOptions)
            ->setValue($query->getSort());
    }

    protected function asUrl(Query $query, array $valueOptions): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $params = $plugins->get('params');
        $serverUrl = $plugins->get('serverUrl');

        // Prepare urls directly as values to avoid a click. Use current url for a quick build.
        $currentUrl = strtok($serverUrl(true), '?');
        $currentQuery = $params->fromQuery();
        $currentSort = $query->getSort();
        $valueOptionsWithUrl = [];
        foreach ($valueOptions as $name => $sortOption) {
            $url = $currentUrl . '?' . http_build_query(['page' => 1, 'sort' => $name] + $currentQuery, '', '&', PHP_QUERY_RFC3986);
            $valueOptionsWithUrl[$name] = [
                'value' => $name,
                // The label is automatically translated by Laminas.
                'label' => $sortOption['label'] ?: $name,
                'attributes' => [
                    'data-url' => $url,
                ],
            ];
        }

        return (new Select('sort'))
            ->setValueOptions($valueOptionsWithUrl)
            ->setValue($currentSort);
    }
}
