<?php declare(strict_types=1);

namespace AdvancedSearch\Form\View\Helper;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Laminas\Form\ElementInterface;
use Laminas\Form\Exception;
use Laminas\Form\View\helper\FormText;

class FormMultiText extends FormText
{
    /**
     * Render a form <input type="text"> element from the provided $element
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof AdvancedSearchElement\MultiText) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Method %1$s requires that the element is of type %2$s', // @translate
                __METHOD__, \AdvancedSearch\Form\Element\MultiText::class
            ));
        }

        $name = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(sprintf(
                '%s requires that the element has an assigned name; none discovered', // @ŧranslate
                __METHOD__
            ));
        }

        $value = $element->getValue();
        if (is_array($value)) {
            // Render at least one input field, even if null.
            if (!count($value)) {
                $value = [null];
            }
        } else {
            $value = [$value];
        }

        $attributes = $element->getAttributes();
        $attributes['name'] = $name . '[]';
        $attributes['type'] = 'text';

        $rendered = '';
        foreach ($value as $val) {
            $attribs = $attributes;
            if (is_null($val)) {
                unset($attribs['value']);
            } else {
                $attribs['value'] = $val;
            }
            $rendered .= sprintf(
                '<input %s%s',
                $this->createAttributesString($attribs),
                $this->getInlineClosingBracket()
            );
        }

        return $rendered;
    }
}
