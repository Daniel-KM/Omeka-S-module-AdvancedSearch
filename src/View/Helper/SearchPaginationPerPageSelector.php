<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Query;
use Laminas\Form\Element\Select;
use Laminas\View\Helper\AbstractHelper;

class SearchPaginationPerPageSelector extends AbstractHelper
{
    /**
     * @var string
     */
    protected $partial = 'search/pagination-per-page-selector';

    /**
     * Option $asUrls allows to include the select in the main form or not.
     * When set, a js reloads the page directly.
     * Anyway, the js can rebuild the url from the values.
     * @todo To avoid a js, a css select (replace select by ul/li + a) can be created in theme.
     */
    public function __invoke(Query $query, array $options, ?bool $asUrl = false, ?string $partial = null): string
    {
        if (!count($options)) {
            return '';
        }

        // Don't display unmanaged per page from the query, but set the default
        // Omeka per page.
        $plugins = $this->getView()->getHelperPluginManager();
        $status = $plugins->get('status');
        $setting = $plugins->get('setting');
        $defaultPerPage = (int) $setting('pagination_per_page', 25) ?: 25;
        if ($status->isSiteRequest()) {
            $siteSetting = $plugins->get('siteSetting');
            $defaultPerPage = (int) $siteSetting('pagination_per_page') ?: $defaultPerPage;
        }
        if (count($options) === 1 && (int) key($options) === $defaultPerPage) {
            return '';
        }

        $options = array_replace([$defaultPerPage => sprintf('Results by %d', $defaultPerPage)], $options); // @translate
        ksort($options);

        $select = $asUrl
            ? $this->asUrl($query, $options, $defaultPerPage)
            : $this->asForm($query, $options, $defaultPerPage);

        return $this->getView()->partial($partial ?: $this->partial, [
            'query' => $query,
            'select' => $select,
            'options' => $options,
            'asUrl' => $asUrl,
        ]);
    }

    protected function asForm(Query $query, array $options, int $defaultPerPage): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $translate = $plugins->get('translate');
        $options = array_map($translate, $options);
        return (new Select('per_page'))
            ->setValueOptions($options)
            ->setValue($query->getPerPage() ?: $defaultPerPage)
            ->setLabel($translate('Per page')); // @translate
    }

    protected function asUrl(Query $query, array $options, int $defaultPerPage): Select
    {
        $plugins = $this->getView()->getHelperPluginManager();
        $params = $plugins->get('params');
        $translate = $plugins->get('translate');
        $serverUrl = $plugins->get('serverUrl');

        // Prepare urls directly as values to avoid a click. Use current url for a quick build.
        $currentUrl = strtok($serverUrl(true), '?');
        $currentQuery = $params->fromQuery();
        $currentPerPage = (int) $query->getPerPage() ?: $defaultPerPage;
        $optionsWithUrl = [];
        foreach ($options as $perPage => $label) {
            $perPage = (int) $perPage;
            $url = $currentUrl . '?' . http_build_query(['page' => 1, 'per_page' => $perPage] + $currentQuery, '', '&', PHP_QUERY_RFC3986);
            $optionsWithUrl[$perPage] = [
                'value' => $perPage,
                // The label is automatically translated by Laminas.
                'label' => $label,
                'attributes' => [
                    'data-url' => $url,
                ],
            ];
        }

        return (new Select('per_page'))
            ->setValueOptions($optionsWithUrl)
            ->setValue($currentPerPage)
            ->setLabel($translate('Per page')); // @translate
    }
}
