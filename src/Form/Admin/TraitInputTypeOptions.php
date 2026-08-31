<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

/**
 * Build the value options of the select of the types of input with a class
 * by type, used to display an icon and a preview in the admin form.
 */
trait TraitInputTypeOptions
{
    /**
     * @param array $types Associative list of type => label, with optional
     *   groups (label + options).
     */
    protected function inputTypeOptions(array $types): array
    {
        $result = [];
        foreach ($types as $type => $label) {
            if (is_array($label)) {
                $result[$type] = [
                    'label' => $label['label'],
                    'options' => $this->inputTypeOptions($label['options']),
                ];
                continue;
            }
            $result[$type] = [
                'value' => $type,
                'label' => $label,
                'attributes' => [
                    'class' => 'input-type input-type-' . strtolower($type),
                ],
            ];
        }
        return $result;
    }
}
