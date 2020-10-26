<?php declare(strict_types=1);
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Form\BasicForm;

class BasicFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new BasicForm(null, $options);
        return $form;
    }
}
