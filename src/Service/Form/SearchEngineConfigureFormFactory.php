<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\SearchEngineConfigureForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchEngineConfigureFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $translator = $services->get('MvcTranslator');

        $form = new SearchEngineConfigureForm(null, $options ?? []);
        return $form
            ->setApiManager($api)
            ->setTranslator($translator);
    }
}
