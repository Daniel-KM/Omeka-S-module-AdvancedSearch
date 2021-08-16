<?php declare(strict_types=1);
namespace AdvancedSearch\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use AdvancedSearch\Form\Admin\SearchIndexForm;

class SearchIndexFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $searchAdapterManager = $services->get('Search\AdapterManager');

        $form = new SearchIndexForm(null, $options);
        $form->setSearchAdapterManager($searchAdapterManager);
        return $form;
    }
}
