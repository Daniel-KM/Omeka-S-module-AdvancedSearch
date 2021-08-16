<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\SearchingFormFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchingFormFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $viewHelpers = $services->get('ViewHelperManager');
        $form = new SearchingFormFieldset(null, $options);
        return $form
            ->setApi($viewHelpers->get('api'))
            ->setSiteSetting($viewHelpers->get('siteSetting'));
    }
}
