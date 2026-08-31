<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;

/**
 * Elements shared between the filter and the facet fieldsets.
 *
 * The elements are identical in both fieldsets, except the id, the types that
 * display them and some informative texts, passed as arguments. The texts are
 * translated by the caller.
 */
trait TraitSharedFieldsetElements
{
    /**
     * Add the elements "value_labels_table" and "value_labels".
     */
    protected function addValueLabelsElements(callable $tr, string $idPrefix, string $filterTypes, string $valueLabelsInfo): self
    {
        $this
            ->add([
                'name' => 'value_labels_table',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Value labels: table source', // @translate
                    'info' => 'Optional slug or id of a table (module Table) used as the base code / label mapping. Inline "Value labels" below override the table entries when both are defined.', // @translate
                ],
                'attributes' => [
                    'id' => $idPrefix . 'value_labels_table',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'data-filter-types' => $filterTypes,
                    'required' => false,
                    'placeholder' => 'my-table-slug',
                ],
            ])
            ->add([
                'name' => 'value_labels',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Value labels', // @translate
                    'info' => $valueLabelsInfo,
                    'as_key_value' => true,
                    'pairs_editor' => [
                        'key_label' => $tr('Value'), // @translate
                        'value_label' => $tr('Label'), // @translate
                        'sortable' => false,
                    ],
                ],
                'attributes' => [
                    'id' => $idPrefix . 'value_labels',
                    'data-advanced-section' => $tr('Values'), // @translate
                    'data-filter-types' => $filterTypes,
                    'required' => false,
                    'rows' => 3,
                    'placeholder' => <<<'TXT'
                        1 = Only with image
                        0 = Without image
                        TXT,
                ],
            ]);
        return $this;
    }

    /**
     * Add the elements "min", "max", "step" and "first_digits".
     */
    protected function addBoundsElements(callable $tr, string $idPrefix, string $filterTypes): self
    {
        $this
            ->add([
                'name' => 'min',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Minimum', // @translate
                ],
                'attributes' => [
                    'id' => $idPrefix . 'min',
                    'data-filter-types' => $filterTypes,
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-inline' => 'bounds',
                    'required' => false,
                    'step' => 'any',
                ],
            ])
            ->add([
                'name' => 'max',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Maximum', // @translate
                ],
                'attributes' => [
                    'id' => $idPrefix . 'max',
                    'data-filter-types' => $filterTypes,
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-inline' => 'bounds',
                    'required' => false,
                    'step' => 'any',
                ],
            ])
            ->add([
                'name' => 'step',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Step', // @translate
                ],
                'attributes' => [
                    'id' => $idPrefix . 'step',
                    'data-filter-types' => $filterTypes,
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-inline' => 'bounds',
                    'required' => false,
                    'step' => 'any',
                    'min' => '0',
                ],
            ])
            ->add([
                'name' => 'first_digits',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Extract the first digits of the values (year of a date)', // @translate
                ],
                'attributes' => [
                    'id' => $idPrefix . 'first_digits',
                    'data-filter-types' => $filterTypes,
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'required' => false,
                    'value' => '1',
                ],
            ]);
        return $this;
    }

    /**
     * Add the elements "scale_mode", "scale_breakpoints", "scale_show_ticks".
     *
     * Mode "linear" is the default and ignores breakpoints. Mode "piecewise"
     * requires at least two breakpoints with values and positions strictly
     * increasing from 0 to 100.
     */
    protected function addScaleElements(callable $tr, string $idPrefix, string $filterTypes): self
    {
        $this
            ->add([
                'name' => 'scale_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Slider scale (RangeDouble)', // @translate
                    'value_options' => [
                        'linear' => 'Linear', // @translate
                        'log' => 'Logarithmic', // @translate
                        'piecewise' => 'Piecewise (with breakpoints)', // @translate
                        'auto' => 'Auto (quartiles from data)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => $idPrefix . 'scale_mode',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-filter-types' => $filterTypes,
                    'value' => 'linear',
                ],
            ])
            ->add([
                'name' => 'scale_breakpoints',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Scale breakpoints', // @translate
                    'info' => 'One pair per line: value = position. Position is a percentage between 0 and 100.', // @translate
                    'as_key_value' => true,
                    'pairs_editor' => [
                        'key_label' => $tr('Value'), // @translate
                        'value_label' => $tr('Position (%)'), // @translate
                        'value_type' => 'number',
                    ],
                ],
                'attributes' => [
                    'id' => $idPrefix . 'scale_breakpoints',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-filter-types' => $filterTypes,
                    'required' => false,
                    'rows' => 5,
                    'placeholder' => <<<TXT
                        min = 0
                        1 = 20
                        1789 = 50
                        max = 100
                        TXT,
                ],
            ])
            ->add([
                'name' => 'scale_show_ticks',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display ticks at breakpoints', // @translate
                ],
                'attributes' => [
                    'id' => $idPrefix . 'scale_show_ticks',
                    'data-advanced-section' => $tr('Slider'), // @translate
                    'data-filter-types' => $filterTypes,
                ],
            ]);
        return $this;
    }

    /**
     * Add the free options element (ini format).
     */
    protected function addOptionsElement(callable $tr, string $id, string $info): self
    {
        $this
            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'options',
                'options' => [
                    'label' => 'Options', // @translate
                    'info' => $info,
                    'ini_typed_mode' => true,
                    'pairs_editor' => [
                        'key_label' => $tr('Option'), // @translate
                        'value_label' => $tr('Value'), // @translate
                        'sortable' => false,
                    ],
                ],
                'attributes' => [
                    'id' => $id,
                    'data-advanced-section' => $tr('Advanced'), // @translate
                    'required' => false,
                    'placeholder' => '',
                ],
            ]);
        return $this;
    }

    /**
     * Add the free html attributes element.
     */
    protected function addAttributesElement(callable $tr, string $id, string $info): self
    {
        $this
            ->add([
                'type' => CommonElement\ArrayTextarea::class,
                'name' => 'attributes',
                'options' => [
                    'label' => 'Html attributes', // @translate
                    'info' => $info,
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'pairs_editor' => [
                        'key_label' => $tr('Attribute'), // @translate
                        'value_label' => $tr('Value'), // @translate
                    ],
                ],
                'attributes' => [
                    'id' => $id,
                    'data-advanced-section' => $tr('Advanced'), // @translate
                    'required' => false,
                    'placeholder' => '',
                ],
            ]);
        return $this;
    }
}
