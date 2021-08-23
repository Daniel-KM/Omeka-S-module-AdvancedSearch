<?php declare(strict_types=1);

namespace AdvancedSearch\Service\ViewHelper;

use AdvancedSearch\View\Helper\SearchEngineConfirm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchEngineConfirmFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SearchEngineConfirm($services->get('FormElementManager'));
    }
}
