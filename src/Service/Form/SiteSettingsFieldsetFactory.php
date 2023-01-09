<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SiteSettingsFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SiteSettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $searchConfigs = $services->get('Omeka\ApiManager')->search('search_configs')->getContent();
        $valueOptions = [];
        foreach ($searchConfigs as $searchConfig) {
            $labelSearchConfig = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->path());
            $valueOptions[$searchConfig->id()] = $labelSearchConfig;
        }
        $siteSettings = $services->get('Omeka\Settings\Site');
        $fieldset = new SiteSettingsFieldset(null, $options ?? []);
        $config = $services->get('Config');
        return $fieldset
            ->setSettings($siteSettings)
            ->setSearchConfigs($valueOptions)
            ->setDefaultSearchFields($config['advancedsearch']['search_fields'] ?: []);
    }
}
