<?php declare(strict_types=1);

namespace AdvancedSearch\Service\ViewHelper;

use AdvancedSearch\View\Helper\EscapeValueOrGetHtml;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EscapeValueOrGetHtmlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new EscapeValueOrGetHtml(
            $services->get('ViewHelperManager')->get('escapeHtml')
        );
    }
}
