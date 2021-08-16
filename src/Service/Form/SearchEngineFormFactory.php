<?php declare(strict_types=1);
namespace AdvancedSearch\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use AdvancedSearch\Form\Admin\SearchEngineForm;

class SearchEngineFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $searchAdapterManager = $services->get('Search\AdapterManager');

        $form = new SearchEngineForm(null, $options);
        $form->setSearchAdapterManager($searchAdapterManager);
        return $form;
    }
}
