<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SiteSettingsFieldset;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SiteSettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $searchConfigs = $services->get('Omeka\ApiManager')->search('search_configs')->getContent();
        $valueOptions = [];
        foreach ($searchConfigs as $searchConfig) {
            $labelSearchConfig = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->slug());
            $valueOptions[$searchConfig->id()] = $labelSearchConfig;
        }

        $config = $services->get('Config');
        $listSearchFields = $config['advancedsearch']['search_fields'] ?: [];
        foreach ($listSearchFields as $key => $searchField) {
            $listSearchFields[$key] = $searchField['label'] ?? $key;
        }

        // The item sets and the pages of the site, to fill the pickers of the
        // redirections.
        $itemSets = [];
        $sitePages = [];
        try {
            $site = $services->get('ControllerPluginManager')->get('currentSite')();
            foreach ($site->siteItemSets() as $siteItemSet) {
                $itemSet = $siteItemSet->itemSet();
                // The picker of the editor of pairs appends the id itself.
                $itemSets[$itemSet->id()] = (string) $itemSet->displayTitle();
            }
            foreach ($site->pages() as $page) {
                $sitePages[$page->slug()] = sprintf('%s (/%s)', $page->title(), $page->slug());
            }
        } catch (\Throwable $e) {
            // No current site, for example during an upgrade.
        }

        $fieldset = new SiteSettingsFieldset(null, $options ?? []);
        return $fieldset
            ->setSearchConfigs($valueOptions)
            ->setListSearchFields($listSearchFields)
            ->setItemSets($itemSets)
            ->setSitePages($sitePages)
        ;
    }
}
