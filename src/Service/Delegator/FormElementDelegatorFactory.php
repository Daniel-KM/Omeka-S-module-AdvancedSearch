<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Delegator;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

class FormElementDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name, callable $callback, array $options = null)
    {
        /** @var \Laminas\Form\View\Helper\FormElement $formElement */
        $formElement = $callback();
        return $formElement
            ->addType('note', 'formNote')
            ->addClass(\AdvancedSearch\Form\Element\MultiText::class, 'formMultiText')
        ;
    }
}
