<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Laminas\Form\Factory;
use Laminas\Form\FormElementManager;
use Laminas\View\Helper\AbstractHelper;

/**
 * Render a html Select with all fields.
 */
class FieldSelect extends AbstractHelper
{
    /**
     * @var \Laminas\Form\FormElementManager
     */
    protected $formElementManager;

    public function __construct(FormElementManager $formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Render a html Select with all fields.
     */
    public function __invoke(array $spec = []): string
    {
        $spec['type'] = AdvancedSearchElement\FieldSelect::class;
        if (!isset($spec['options']['empty_option'])) {
            $spec['options']['empty_option'] = 'Select metadata…'; // @translate
        }
        $factory = new Factory($this->formElementManager);
        $element = $factory->createElement($spec);
        return $this->getView()->formSelect($element);
    }
}
