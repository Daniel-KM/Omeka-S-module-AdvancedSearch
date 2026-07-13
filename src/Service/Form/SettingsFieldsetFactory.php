<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SettingsFieldset;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SettingsFieldsetFactory implements FactoryInterface
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
        // Any search config points to an engine, which is the only thing the
        // api redirection needs since the query is normalized generically.
        $apiOptions = $valueOptions;

        $config = $services->get('Config');

        $listSearchFields = $config['advancedsearch']['search_fields'] ?: [];
        foreach ($listSearchFields as $key => $searchField) {
            $listSearchFields[$key] = $searchField['label'] ?? $key;
        }

        $fieldset = new SettingsFieldset(null, $options ?? []);
        return $fieldset
            ->setSearchConfigs($valueOptions)
            ->setSearchConfigsApi($apiOptions)
            ->setListSearchFields($listSearchFields)
        ;
    }
}
