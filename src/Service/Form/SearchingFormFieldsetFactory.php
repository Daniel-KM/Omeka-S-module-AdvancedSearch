<?php declare(strict_types=1);
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Form\SearchingFormFieldset;

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
