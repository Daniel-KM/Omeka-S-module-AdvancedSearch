<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Render the main search form in place of the quick search of the theme.
 */
class SearchQuickReplacement extends AbstractHelper
{
    /**
     * Get the form that replaces the quick search, or an empty string.
     *
     * A theme that renders its own search form should output this string first
     * and skip its own form when it is not empty:
     *
     * ```php
     * $replacement = $this->searchQuickReplacement();
     * if ($replacement) {
     *     echo $replacement;
     *     return;
     * }
     * ```
     */
    public function __invoke(): string
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();

        if (!$plugins->has('siteSetting')
            || !$plugins->get('siteSetting')('advancedsearch_main_config_replace_quick')
        ) {
            return '';
        }

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $plugins->has('getSearchConfig') ? $view->getSearchConfig() : null;
        if (!$searchConfig) {
            return '';
        }

        // The simple variant keeps the main field, the hidden elements and the
        // button: it replaces a quick search, not the advanced search form,
        // whose filters remain available in the dialog or the search page.
        $html = $searchConfig->renderForm([
            'template' => 'search/search-form-simple',
            'variant' => 'simple',
        ]);

        // Without a form, there is nothing to display, neither the quick search
        // nor a link to filters that do not exist.
        if (!$html) {
            return '';
        }

        $advancedLink = $plugins->get('siteSetting')('advancedsearch_main_config_advanced_link', 'dialog');
        if (!$advancedLink || !$searchConfig->formAdapter()) {
            return $html;
        }

        $escape = $plugins->get('escapeHtml');
        $translate = $plugins->get('translate');

        // The link is a standard link to the search page: without javascript,
        // or when the dialog cannot be loaded, the filters remain reachable.
        // The attribute data-search-form-url turns it into a dialog.
        return $html . sprintf(
            '<a href="%s" class="advanced-search-link"%s data-dialog-heading="%s">%s</a>',
            $escape($searchConfig->siteUrl()),
            $advancedLink === 'dialog'
                ? sprintf(' data-search-form-url="%s"', $escape($searchConfig->formUrl()))
                : '',
            $escape($translate('Advanced search')), // @translate
            $escape($translate('Advanced search')) // @translate
        );
    }
}
