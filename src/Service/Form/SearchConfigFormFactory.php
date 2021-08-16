<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\SearchConfigForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new SearchConfigForm(null, $options))
            ->setApiManager($services->get('Omeka\ApiManager'))
            ->setFormAdapterManager($services->get('AdvancedSearch\FormAdapterManager'));
    }
}
