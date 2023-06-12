<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\ApiFormConfigFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiFormConfigFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new ApiFormConfigFieldset(null, $options ?? []))
            ->setEasyMeta($services->get('ViewHelperManager')->get(\AdvancedSearch\View\Helper\EasyMeta::class));
    }
}
