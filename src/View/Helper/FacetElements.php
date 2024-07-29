<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class FacetElements extends AbstractHelper
{
    /**
     * Create one facet as link, checkbox, select, etc. according to options.
     *
     * @param string|array $facetField Field name or null for active facets.
     * @param array $facetValues Each facet value has two keys: value and count.
     * May have more data for specific facets, like facet range.
     * @param array $options Search config settings for the current facet, that
     * should contain the main mode and, for some types of facets, the type and
     * the label.
     * @param bool $asData Return an array instead of the partial.
     * @return string|array
     */
    public function __invoke(?string $facetField, array $facetValues, array $options = [], bool $asData = false)
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();

        // The type may be missing: the default is to use a checkbox.
        // Facet checkbox can be used in any case anyway, the js checks it.
        $isFacetModeButton = ($options['mode'] ?? null) !== 'link';
        $facetType = $options['type'] ?? null;

        // TODO Use match when Omeka will force php 8.
        switch ($facetType) {
            default:
            case 'Checkbox':
            case 'Link':
                $facetElements = $isFacetModeButton ? $plugins->get('facetCheckboxes') : $plugins->get('facetLinks');
                break;
            case 'RangeDouble':
                $facetElements = $plugins->get('facetRangeDouble');
                break;
            case 'Select':
                $facetElements = $plugins->get('facetSelect');
                break;
            case 'SelectRange':
                $facetElements = $plugins->get('facetSelectRange');
                break;
            case 'Thesaurus':
            case 'Tree':
                $facetElements = $isFacetModeButton ? $plugins->get('facetCheckboxesTree') : $plugins->get('facetLinksTree');
                break;
        }

        return $facetElements($facetField, $facetValues, $options, $asData);
    }
}
