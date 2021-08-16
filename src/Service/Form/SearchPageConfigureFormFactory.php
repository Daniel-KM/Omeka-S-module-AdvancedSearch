<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use AdvancedSearch\Form\Admin\SearchPageConfigureForm;

class SearchPageConfigureFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new SearchPageConfigureForm(null, $options))
            ->setFormElementManager($services->get('FormElementManager'));
    }
}
