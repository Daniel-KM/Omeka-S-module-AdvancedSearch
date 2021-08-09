<?php declare(strict_types=1);
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Form\SettingsFieldset;

class SettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fieldset = new SettingsFieldset(null, $options);
        $viewHelpers = $services->get('ViewHelperManager');
        $fieldset->setApi($viewHelpers->get('api'));
        return $fieldset;
    }
}
