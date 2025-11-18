<?php declare(strict_types=1);

namespace AdvancedSearch\Form\View\Helper;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Laminas\Form\ElementInterface;
use Laminas\Form\Exception;
use Laminas\Form\View\Helper\FormInput;

class FormRangeDouble extends FormInput
{
    /**
     * Render a form with two inputs "range" and two inputs "number".
     *
     * The value should be passed as array with keys "from" and "to" or the ones
     * specified in the element.
     *
     * "min" and "max" values are required to compute color.
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof AdvancedSearchElement\RangeDouble) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Method %1$s requires that the element is of type %2$s', // @translate
                __METHOD__, \AdvancedSearch\Form\Element\RangeDouble::class
            ));
        }

        $name = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(sprintf(
                'Method %s requires that the element has an assigned name; none discovered', // @ŧranslate
                __METHOD__
            ));
        }

        $value = $element->getValue();
        if (!is_array($value) && $value !== null) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Method %s requires that the value be an associative array', // @translate
                __METHOD__
            ));
        }

        $view = $this->getView();
        return $view->partial('common/form/range-double', [
            'element' => $element,
        ]);
    }
}
