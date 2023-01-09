<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SettingsFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $searchConfigs = $services->get('Omeka\ApiManager')->search('search_configs')->getContent();
        $valueOptions = [];
        $apiOptions = [];
        foreach ($searchConfigs as $searchConfig) {
            $labelSearchConfig = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->path());
            $valueOptions[$searchConfig->id()] = $labelSearchConfig;
            if ($searchConfig->formAdapter() instanceof \AdvancedSearch\FormAdapter\ApiFormAdapter) {
                $apiOptions[$searchConfig->id()] = $labelSearchConfig;
            }
        }
        $fieldset = new SettingsFieldset(null, $options ?? []);
        return $fieldset
            ->setSearchConfigs($valueOptions)
            ->setSearchConfigsApi($apiOptions)
            ->setRestrictUsedTerms((bool) $services->get('Omeka\Settings')->get('advancedsearch_restrict_used_terms'));
    }
}
