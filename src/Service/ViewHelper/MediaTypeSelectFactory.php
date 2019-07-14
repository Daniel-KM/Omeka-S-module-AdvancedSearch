<?php

namespace AdvancedSearchPlus\Service\ViewHelper;

use AdvancedSearchPlus\View\Helper\MediaTypeSelect;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class MediaTypeSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MediaTypeSelect($services->get('FormElementManager'));
    }
}
