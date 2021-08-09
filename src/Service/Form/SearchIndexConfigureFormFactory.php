<?php declare(strict_types=1);
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Form\Admin\SearchIndexConfigureForm;

class SearchIndexConfigureFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $translator = $services->get('MvcTranslator');

        $form = new SearchIndexConfigureForm(null, $options);
        $form->setTranslator($translator);
        $form->setApiManager($api);

        return $form;
    }
}
