<?php declare(strict_types=1);

namespace AdvancedSearch\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class SearchingForm implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Advanced Search (module)'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
            'media',
            'item_sets',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        $plugins = $view->getHelperPluginManager();
        $getSearchConfig = $plugins->get('getSearchConfig');

        $searchConfig = $getSearchConfig(null, $resource->resourceName());
        if (!$searchConfig) {
            return '';
        }

        $siteSetting = $plugins->get('siteSetting');

        $options = [];

        if ($resource instanceof \Omeka\Api\Representation\ItemRepresentation) {
            $template = $siteSetting('advancedsearch_items_template_form');
            if ($template) {
                $options['template'] = $template;
            }
        } elseif ($resource instanceof \Omeka\Api\Representation\ItemSetRepresentation) {
            $options['itemSet'] = $resource;
            $options['skip_form_action'] = empty($siteSetting('advancedsearch_item_sets_scope'));
            $template = $siteSetting('advancedsearch_item_sets_template_form');
            if ($template) {
                $options['template'] = $template;
            }
        } elseif ($resource instanceof \Omeka\Api\Representation\MediaRepresentation) {
            $template = $siteSetting('advancedsearch_media_template_form');
            if ($template) {
                $options['template'] = $template;
            }
        }

        return $view->partial('common/resource-page-block-layout/searching-form', [
            'resource' => $resource,
            'searchConfig' => $searchConfig,
            'options' => $options,
        ]);
    }
}
