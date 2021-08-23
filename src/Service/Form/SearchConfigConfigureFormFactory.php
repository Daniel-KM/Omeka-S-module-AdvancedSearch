<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\SearchConfigConfigureForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchConfigConfigureFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $suggesters = $services->get('Omeka\ApiManager')->search('search_suggesters', [], ['returnScalar' => 'name'])->getContent();
        return (new SearchConfigConfigureForm(null, $options))
            ->setFormElementManager($services->get('FormElementManager'))
            ->setSuggesters($suggesters);
    }
}
