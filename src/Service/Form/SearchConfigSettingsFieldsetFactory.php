<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\SearchConfigSettingsFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchConfigSettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $fieldset = new SearchConfigSettingsFieldset(null, $options ?? []);
        $fieldset
            ->setApiManager($services->get('Omeka\ApiManager'))
            ->setFormAdapterManager($services->get('AdvancedSearch\FormAdapterManager'));
        return $fieldset;
    }
}
