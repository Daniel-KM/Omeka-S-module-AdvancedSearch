<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SearchingFormFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchingFormFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /**
         * @see \AdvancedSearch\Service\Form\SearchingFormFieldsetFactory
         * Adapted:
         * @see \BlockPlus\Service\Form\SearchFormFieldsetFactory
         * @see \Reference\Service\Form\ReferenceFieldsetFactory
         */

        $configs = [];

        $siteSettings = $services->get('Omeka\Settings\Site');
        $availableSearchConfigs = $siteSettings->get('advancedsearch_configs', []);

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $api = $services->get('Omeka\ApiManager');
        $searchConfigs = $api->search('search_configs', ['id' => $availableSearchConfigs])->getContent();

        foreach ($searchConfigs as $searchConfig) {
            $configs[$searchConfig->id()] = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->slug());
        }

        // Set the main search config first and as default.
        $default = $siteSettings->get('advancedsearch_main_config') ?: reset($availableSearchConfigs);
        if (isset($configs[$default])) {
            $configs = [$default => $configs[$default]] + $configs;
        }

        $form = new SearchingFormFieldset(null, $options ?? []);
        return $form
            ->setSearchConfigs($configs);
    }
}
