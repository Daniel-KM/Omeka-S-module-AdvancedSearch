<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\Form\ElementInterface;
use Omeka\Form\View\Helper\FormQuery;

class FormQueryDelegator extends FormQuery
{
    /**
     * @var \Omeka\Form\View\Helper\FormQuery
     */
    protected $formQuery;

    public function __construct(FormQuery $formQuery)
    {
        $this->formQuery = $formQuery;
    }

    public function render(ElementInterface $element)
    {
        $view = $this->getView();
        $view->headLink()->appendStylesheet($view->assetUrl('css/advanced-search-form.css', 'AdvancedSearch'));
        $view->headScript()->appendFile($view->assetUrl('js/advanced-search-form.js', 'AdvancedSearch'));
        return parent::render($element);
    }
}
