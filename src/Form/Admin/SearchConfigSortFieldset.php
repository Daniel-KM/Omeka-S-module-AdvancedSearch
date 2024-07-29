<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;

class SearchConfigSortFieldset extends Fieldset implements InputFilterProviderInterface
{
    public function init(): void
    {
        // TODO The preparation of the sort select may be moved to js?

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $translate = $searchConfig
            ? $searchConfig->getServiceLocator()->get('ViewHelperManager')->get('translate')
            : fn($v) => $v;

        $directionLabels = [
            'asc' => $translate('ascendant'), // @ŧranslate
            'desc' => $translate('descendant'), // @ŧranslate
        ];

        // These fields may be overridden by the available fields.
        $availableFields = $this->getAvailableSortFields();

        $sortFromField = function($label, $name) use($translate, $directionLabels): string {
            $labelDirectionPos = mb_strrpos($label, ' ');
            $labelNoDirection = $labelDirectionPos ? trim(mb_substr($label, 0, $labelDirectionPos)) : $label;
            $labelDirection = $labelDirectionPos ? trim(mb_substr($label, $labelDirectionPos), ' ()') : ($name === 'relevance desc' ? '' : $directionLabels['asc']);
            return sprintf('%1$s %2$s (%3$s)', $translate($labelNoDirection), $labelDirection, $name);
        };

        $labelFromField = function($label) use($translate, $directionLabels): string {
            $labelDirectionPos = mb_strrpos($label, ' ');
            $labelNoDirection = $labelDirectionPos ? trim(mb_substr($label, 0, $labelDirectionPos)) : $label;
            $labelDirection = $labelDirectionPos ? trim(mb_substr($label, $labelDirectionPos), ' ()') : $directionLabels['asc'];
            return sprintf('%1$s (%2$s)', $translate($labelNoDirection), $labelDirection);
        };

        // Prepare default label as data for js.
        $sortFields = [];
        foreach ($availableFields as $name => $labelOrGroup) {
            if (!is_array($labelOrGroup)) {
                $sortFields[] = [
                    'value' => $name,
                    'label' => $sortFromField($labelOrGroup, $name),
                    'attributes' => [
                        'data-label-default' => $labelFromField($labelOrGroup), // @translate
                    ],
                ];
                continue;
            }
            // Manage grouped fields.
            $sortFields[$name] = $labelOrGroup;
            $options = [];
            foreach ($labelOrGroup['options'] ?? [] as $optionName => $optionLabel) {
                $options[] = [
                    'value' => $optionName,
                    'label' => $sortFromField($optionLabel, $optionName),
                    'attributes' => [
                        'data-label-default' => $labelFromField($optionLabel), // @translate
                    ],
                ];
            }
            $sortFields[$name]['options'] = $options;
        }

        $this
            ->setAttribute('id', 'form-search-config-sort')
            ->setAttribute('class', 'form-fieldset-element form-search-config-sort')
            ->setName('sort')

            ->add([
                'name' => 'name',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Sort field', // @translate
                    'info' =>'The field is an index available in the search engine. The internal search engine supports property terms and aggregated fields (date, author, etc).', // @translate
                    'value_options' => $sortFields,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'form_sort_name',
                    'required' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Set order…', // @translate
                ],
            ])
            ->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'id' => 'form_sort_label',
                    'required' => false,
                ],
            ])

            ->add([
                'name' => 'minus',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'search-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-delete.
                    'class' => 'search-fieldset-action search-fieldset-minus fa fa-minus remove-value button',
                    'aria-label' => 'Remove this sort option', // @translate
                ],
            ])
            ->add([
                'name' => 'up',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'search-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-delete.
                    'class' => 'search-fieldset-action search-fieldset-up fa fa-arrow-up button',
                    'aria-label' => 'Move this sort option up', // @translate
                ],
            ])
            ->add([
                'name' => 'down',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'search-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-delete.
                    'class' => 'search-fieldset-action search-fieldset-down fa fa-arrow-down button',
                    'aria-label' => 'Move this sort option down', // @translate
                ],
            ])
        ;
    }

    /**
     * This method is required when a fieldset is used as a collection, else the
     * data are not filtered and not returned with getData().
     *
     * {@inheritDoc}
     * @see \Laminas\InputFilter\InputFilterProviderInterface::getInputFilterSpecification()
     */
    public function getInputFilterSpecification()
    {
        return [
            'name' => [
                'required' => false,
            ],
        ];
    }

    protected function getAvailableSortFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        if (!$searchConfig) {
            return [];
        }

        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine ? $searchEngine->adapter() : null;
        if (empty($searchAdapter)) {
            return [];
        }

        $searchAdapter->setSearchEngine($searchEngine);
        return $searchAdapter->getAvailableSortFieldsForSelect();
    }
}
