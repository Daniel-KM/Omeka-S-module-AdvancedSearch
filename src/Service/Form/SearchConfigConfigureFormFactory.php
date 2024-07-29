<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form;

use AdvancedSearch\Form\Admin\SearchConfigConfigureForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchConfigConfigureFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $helpers = $services->get('ViewHelperManager');
        $suggesters = $services->get('Omeka\ApiManager')->search('search_suggesters', [], ['returnScalar' => 'name'])->getContent();
        $form = new SearchConfigConfigureForm(null, $options ?? []);
        return $form
            ->setEscapeHtml($helpers->get('escapeHtml'))
            ->setFormElementManager($services->get('FormElementManager'))
            ->setSuggesters($suggesters)
            ->setThumbnailTypes($services->get('Omeka\File\ThumbnailManager')->getTypes())
            ->setTranslator($services->get('MvcTranslator'))
        ;
    }
}
