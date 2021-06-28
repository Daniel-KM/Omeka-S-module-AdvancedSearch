<?php declare(strict_types=1);

namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Form\Admin\SearchPageConfigureForm;

class SearchPageConfigureFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new SearchPageConfigureForm(null, $options))
            ->setFormElementManager($services->get('FormElementManager'));
    }
}
