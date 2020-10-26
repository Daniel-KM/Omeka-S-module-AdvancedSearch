<?php
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Search\Form\Admin\SearchPageForm;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchPageFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new SearchPageForm(null, $options);
        return $form
            ->setApiManager($services->get('Omeka\ApiManager'))
            ->setFormAdapterManager($services->get('Search\FormAdapterManager'));
    }
}
