<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SiteSettingsFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SiteSettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fieldset = new SiteSettingsFieldset(null, $options);
        $viewHelpers = $services->get('ViewHelperManager');
        $config = $services->get('Config');
        return $fieldset
            ->setApi($viewHelpers->get('api'))
            ->setSetting($viewHelpers->get('setting'))
            ->setDefaultSearchFields($config['advancedsearch']['search_fields'] ?: []);
    }
}
