<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SettingsFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fieldset = new SettingsFieldset(null, $options);
        $viewHelpers = $services->get('ViewHelperManager');
        return $fieldset
            ->setApi($viewHelpers->get('api'))
            ->setSetting($viewHelpers->get('setting'));
    }
}
