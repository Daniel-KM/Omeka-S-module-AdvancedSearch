<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\Form\FormElementManager;
use Laminas\View\Helper\AbstractHelper;

class QueryInput extends AbstractHelper
{
    /**
     * @var \Laminas\Form\FormElementManager;
     */
    protected $formElementManager;

    public function __construct(
        FormElementManager $formElementManager
    ) {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Get Query input element.
     */
    public function __invoke(): \Omeka\Form\Element\Query
    {
        return $this->formElementManager->get(\Omeka\Form\Element\Query::class);
    }
}
