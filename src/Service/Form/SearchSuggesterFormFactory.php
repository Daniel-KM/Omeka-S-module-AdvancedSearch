<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\SearchSuggesterForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchSuggesterFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new SearchSuggesterForm(null, $options ?? []))
            ->setApiManager($services->get('Omeka\ApiManager'));
    }
}
