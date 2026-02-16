<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\SearchSuggesterForm;
use Interop\Container\ContainerInterface;
use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchSuggesterFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = (new SearchSuggesterForm(null, $options ?? []))
            ->setApiManager($services->get('Omeka\ApiManager'));

        // Inject event manager with shared event manager for module hooks.
        $sharedEventManager = $services->get('SharedEventManager');
        $eventManager = new EventManager($sharedEventManager);
        $eventManager->setIdentifiers([
            SearchSuggesterForm::class,
            get_class($form),
        ]);
        $form->setEventManager($eventManager);

        return $form;
    }
}
