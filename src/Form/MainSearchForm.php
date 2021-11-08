<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau 2018-2021
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace AdvancedSearch\Form;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Laminas\Form\Element;
use Laminas\Form\ElementInterface;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Form\Element as OmekaElement;
use Omeka\View\Helper\Setting;
use Reference\Mvc\Controller\Plugin\References;

class MainSearchForm extends Form
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var SiteRepresentation
     */
    protected $site;

    /**
     * @var Setting
     */
    protected $siteSetting;

    /**
     * @var \Laminas\Form\FormElementManager\FormElementManagerV3Polyfill
     */
    protected $formElementManager;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Reference\Mvc\Controller\Plugin\References|null
     */
    protected $references;

    /**
     * @var array
     */
    protected $formSettings = [];

    /**
     * @var array
     */
    protected $listInputFilters = [];

    public function init(): void
    {
        // The id is different from the Omeka search to avoid issues in js. The
        // css should be adapted.
        $this
            ->setAttributes([
                'id' => 'form-search',
                'class' => 'form-search',
            ]);

        // Omeka adds a csrf automatically in \Omeka\Form\Initializer\Csrf.
        // Remove the csrf, because it is useless for a search form and the url
        // is not copiable (see the core search form that doesn't use it).
        foreach ($this->getElements() as $element) {
            $name = $element->getName();
            if (substr($name, -4) === 'csrf') {
                $this->remove($name);
                break;
            }
        }
        unset($name);

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $this->formSettings = $searchConfig ? $searchConfig->settings() : [];

        // The main query is always the first element and submit the last one.
        // TODO Allow to order and to skip "q" (include it as a standard filter).

        $this
            ->add([
                'name' => 'q',
                'type' => Element\Search::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'q',
                    'data-type-field' => 'q',
                ],
            ])
        ;

        $autoSuggestUrl = $this->formSettings['autosuggest']['url'] ?? null;
        if (!$autoSuggestUrl) {
            $suggester = $this->formSettings['autosuggest']['suggester'] ?? null;
            if ($suggester) {
                // TODO Use url helper.
                $autoSuggestUrl = $this->basePath . ($this->site ? '/s/' . $this->site->slug() : '/admin') . '/' . ($searchConfig ? $searchConfig->path() : 'search') . '/suggest';
            }
        }
        if ($autoSuggestUrl) {
            $elementQ = $this->get('q')
                ->setAttribute('class', 'autosuggest')
                ->setAttribute('data-autosuggest-url', $autoSuggestUrl);
            if (empty($suggester) && !empty($this->formSettings['autosuggest']['url_param_name'])) {
                $elementQ
                    ->setAttribute('data-autosuggest-param-name', $this->formSettings['autosuggest']['url_param_name']);
            }
        }

        foreach ($this->formSettings['form']['filters'] ?? [] as $filter) {
            if (empty($filter['field'])) {
                continue;
            }
            if (!isset($filter['type'])) {
                $filter['type'] = '';
            }
            if (!isset($filter['options'])) {
                $filter['options'] = [];
            }

            $field = $filter['field'];
            $type = $filter['type'];
            if (!isset($filter['label'])) {
                $filter['label'] = '';
            }

            $element = null;

            // Manage exceptions.
            // TODO These exception should be removed: the search engine will manage them.
            // TODO Add special types for these special inputs. Or use them only with name "field".
            // TODO In fact, they are standard field with autosuggestion, so it will be fixed when autosuggestion (or short list) will be added.
            switch ($field) {
                case 'is_public':
                    $element = $this->searchIsPublic($filter);
                    if ($element) {
                        $this->add($element);
                    }
                    continue 2;
                case 'owner/o:id':
                    $element = $this->searchOwner($filter);
                    if ($element) {
                        $this->add($element);
                    }
                    continue 2;
                case 'resource_class/o:id':
                    $element = $this->searchResourceClass($filter);
                    if ($element) {
                        $this->add($element);
                    }
                    continue 2;
                case 'resource_template/o:id':
                    $element = $this->searchResourceTemplate($filter);
                    if ($element) {
                        $this->add($element);
                    }
                    continue 2;
                case 'item_set/o:id':
                    $element = $this->searchItemSet($filter);
                    if ($element) {
                        $this->add($element);
                    }
                    continue 2;
                default:
                    break;
            }

            $method = 'search' . $type;
            $element = method_exists($this, $method)
                ? $this->$method($filter)
                : $this->searchElement($filter);
            if ($element) {
                $this
                    ->add($element);
            }
        }

        $this
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Search', // @translate
                    'label_attributes' => [
                        'class' => 'search-submit',
                    ],
                ],
                'attributes' => [
                    'id' => 'submit',
                    'type' => 'submit',
                    'class' => 'search-submit',
                ],
            ])
        ;

        $this->appendInputFilters();
    }

    /**
     * Add a default input element, represented as a text input.
     */
    protected function searchElement(array $filter): ?ElementInterface
    {
        $element = new Element($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', $filter['type'])
        ;
        return $element;
    }

    protected function searchAdvanced(array $filter): ?ElementInterface
    {
        if (empty($filter['max_number'])) {
            return null;
        }

        $filter['search_config'] = $this->getOption('search_config');
        $advanced = $this->formElementManager->get(SearchFilter\Advanced::class, $filter);
        if (!$advanced->count()) {
            return null;
        }

        $element = new Element\Collection('filter');
        $element
            ->setLabel((string) $filter['label'])
            ->setAttribute('data-field-type', 'filter')
            ->setOptions([
                'label' => $filter['label'],
                'count' => $filter['max_number'],
                'should_create_template' => true,
                'allow_add' => true,
                'target_element' => $advanced,
                'required' => false,
            ])
            ->setAttributes([
                'id' => 'search-filters',
            ])
        ;

        return $element;
    }

    protected function searchCheckbox(array $filter): ?ElementInterface
    {
        $element = new Element\Checkbox($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', 'checkbox')
        ;
        if (!empty($filter['options']) && count($filter['options']) === 2) {
            $element->setOptions([
                'unchecked_value' => $filter['options'][0],
                'checked_value' => $filter['options'][1],
            ]);
        }
        return $element;
    }

    protected function searchDateRange(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset($filter['field']);
        $fieldset
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', 'daterange')
            ->add([
                'name' => 'from',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'From', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'YYYY', // @translate
                ],
            ])
            ->add([
                'name' => 'to',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'To', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'YYYY', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    protected function searchHidden(array $filter): ?ElementInterface
    {
        $value = $filter['options'] === [] ? '' : reset($filter['options']);
        $element = new Element\Hidden($filter['field']);
        $element
            ->setValue($value);
        return $element;
    }

    protected function searchMultiCheckbox(array $filter): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $element = new AdvancedSearchElement\OptionalMultiCheckbox($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', 'multicheckbox')
            ->setValueOptions($valueOptions)
        ;
        return $element;
    }

    protected function searchMultiSelect(array $filter): ?ElementInterface
    {
        $filter['attributes']['multiple'] = true;
        return $this->searchSelect($filter, 'multiselect');
    }

    protected function searchMultiSelectFlat(array $filter): ?ElementInterface
    {
        $filter['attributes']['multiple'] = true;
        return $this->searchSelectFlat($filter, 'multiselectflat');
    }

    /**
     * Simplify the creation of repeatable text input fields.
     */
    protected function searchMultiText(array $filter): ?ElementInterface
    {
        $element = new AdvancedSearchElement\MultiText($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', 'multitext')
        ;
        return $element;
    }

    protected function searchNumber(array $filter): ?ElementInterface
    {
        $element = new Element\Number($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', 'number')
        ;
        return $element;
    }

    protected function searchRadio(array $filter): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $element = new AdvancedSearchElement\OptionalRadio($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', 'radio')
            ->setValueOptions($valueOptions)
        ;
        return $element;
    }

    protected function searchSelect(array $filter, $fieldType = 'select'): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $valueOptions = ['' => ''] + $valueOptions;

        $attributes = $filter['attributes'] ?? [];
        $attributes['class'] = $attributes['class'] ?? 'chosen-select';
        $attributes['placeholder'] = $attributes['placeholder'] ?? '';
        $attributes['data-placeholder'] = $attributes['data-placeholder'] ?? ' ';

        $element = new AdvancedSearchElement\OptionalSelect($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', $fieldType)
            ->setOptions([
                'value_options' => $valueOptions,
                'empty_option' => '',
            ])
            ->setAttributes($attributes)
        ;
        return $element;
    }

    protected function searchSelectFlat(array $filter): ?ElementInterface
    {
        return $this->searchSelect($filter, 'selectflat');
    }

    protected function searchText(array $filter): ?ElementInterface
    {
        $element = new Element\Text($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('data-field-type', 'text')
        ;
        return $element;
    }

    protected function searchIsPublic(array $filter): ?ElementInterface
    {
        if ($filter['field'] === 'is_public'
            && empty($this->formSettings['resource_fields']['is_public'])
        ) {
            return null;
        }

        $element = new Element\Checkbox('is_public');
        $element
            ->setAttributes([
                'id' => 'search-is-public',
                'data-field-type', 'checkbox',
            ])
            ->setOptions([
                'label' => $filter['label'], // @translate
            ])
        ;

        return $element;
    }

    protected function searchOwner(array $filter): ?ElementInterface
    {
        if ($filter['field'] === 'owner/o:id'
            && empty($this->formSettings['resource_fields']['owner/o:id'])
        ) {
            return null;
        }

        $fieldset = new Fieldset('owner');
        $fieldset
            ->setAttributes([
                'id' => 'search-owners',
                'data-field-type', 'owner',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? AdvancedSearchElement\OptionalMultiCheckbox::class
                    : AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->getOwnerOptions(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-owner-id',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    // End users understand "collections" more than "item sets".
                    'data-placeholder' => 'Select owners…', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    protected function searchResourceClass(array $filter): ?ElementInterface
    {
        if ($filter['field'] === 'resource_class/o:id'
            && empty($this->formSettings['resource_fields']['resource_class/o:id'])
        ) {
            return null;
        }

        // For an unknown reason, the ResourceClassSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('class');
        $fieldset->setAttributes([
            'id' => 'search-classes',
            'data-field-type', 'class',
        ]);

        /** @var \Omeka\Form\Element\ResourceClassSelect $element */
        $element = $this->formElementManager->get(OmekaElement\ResourceClassSelect::class);
        $element
            ->setOptions([
                'label' => $filter['label'], // @translate
                'term_as_value' => true,
                'empty_option' => '',
                // TODO Manage list of resource classes by site.
                'used_terms' => true,
                'query' => ['used' => true],
                'disable_group_by_vocabulary' => $filter['type'] === 'SelectFlat',
            ]);

        /* @deprecated (Omeka v3.1): use option "disable_group_by_vocabulary" */
        if ($filter['type'] === 'SelectFlat'
            && version_compare(\Omeka\Module::VERSION, '3.1', '<')
        ) {
            $valueOptions = $element->getValueOptions();
            $result = [];
            foreach ($valueOptions as $name => $vocabulary) {
                if (!is_array($vocabulary)) {
                    $result[$name] = $vocabulary;
                    continue;
                }
                if (empty($vocabulary['options'])) {
                    $result[$vocabulary['value']] = $vocabulary['label'];
                    continue;
                }
                foreach ($vocabulary['options'] as $term) {
                    $result[$term['value']] = $term['label'];
                }
            }
            natcasesort($result);
            $element = new AdvancedSearchElement\OptionalSelect;
            $element
                ->setOptions([
                    'label' => $filter['label'], // @translate
                    'empty_option' => '',
                    'value_options' => $result,
                ]);
        }

        $element
            ->setName('id')
            ->setAttributes([
                'id' => 'search-class-id',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select classes…', // @translate
            ]);

        $fieldset
            ->add($element);

        $this->listInputFilters[] = 'resource_classes';

        return $fieldset;
    }

    protected function searchResourceTemplate(array $filter): ?ElementInterface
    {
        if ($filter['field'] === 'resource_template/o:id'
            && empty($this->formSettings['resource_fields']['resource_template/o:id'])
        ) {
            return null;
        }

        // For an unknown reason, the ResourceTemplateSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('template');
        $fieldset->setAttributes([
            'id' => 'search-templates',
            'data-field-type', 'template',
        ]);

        /** @var \Omeka\Form\Element\ResourceTemplateSelect $element */
        $element = $this->formElementManager->get(OmekaElement\ResourceTemplateSelect::class);
        $element
            ->setName('id')
            ->setOptions([
                'label' => $filter['label'], // @translate
                'empty_option' => '',
                'disable_group_by_owner' => true,
                'used' => true,
                'query' => ['used' => true],
            ])
            ->setAttributes([
                'id' => 'search-template-id',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select templates…', // @translate
            ]);

        $hasValues = false;
        if ($this->siteSetting && $this->siteSetting->__invoke('advancedsearch_restrict_templates', false)) {
            $values = $this->siteSetting->__invoke('advancedsearch_apply_templates', []);
            if ($values) {
                $values = array_intersect_key($element->getValueOptions(), array_flip($values));
                $hasValues = (bool) $values;
                if ($hasValues) {
                    $fieldset
                        ->add([
                            'name' => 'id',
                            'type' => AdvancedSearchElement\OptionalSelect::class,
                            'options' => [
                                'label' => $filter['label'], // @translate
                                'value_options' => $values,
                                'empty_option' => '',
                            ],
                            'attributes' => [
                                'id' => 'search-template-id',
                                'multiple' => true,
                                'class' => 'chosen-select',
                                'data-placeholder' => 'Select templates…', // @translate
                            ],
                        ])
                    ;
                }
            }
        }

        if (!$hasValues) {
            $fieldset
                ->add($element);
        }

        $this->listInputFilters[] = 'resource_templates';

        return $fieldset;
    }

    protected function searchItemSet(array $filter): ?ElementInterface
    {
        if ($filter['field'] === 'item_set/o:id'
            && empty($this->formSettings['resource_fields']['item_set/o:id'])
        ) {
            return null;
        }

        $fieldset = new Fieldset('item_set');
        $fieldset
            ->setAttributes([
                'id' => 'search-item-sets',
                'data-field-type', 'itemset',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? AdvancedSearchElement\OptionalMultiCheckbox::class
                    : AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->getItemSetsOptions($filter['type'] !== 'MultiCheckbox'),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-item-set-id',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    // End users understand "collections" more than "item sets".
                    'data-placeholder' => 'Select collections…', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    /**
     * Add input filters.
     *
     * It is recommended to use an optional element to avoid input filters: this
     * is a search form, everything is generally optional.
     */
    protected function appendInputFilters(): Form
    {
        $inputFilter = $this->getInputFilter();
        if (in_array('resource_classes', $this->listInputFilters)) {
            $inputFilter
                ->get('class')
                ->add([
                    'name' => 'id',
                    'required' => false,
                ]);
        }
        if (in_array('resource_templates', $this->listInputFilters)) {
            $inputFilter
                ->get('template')
                ->add([
                    'name' => 'id',
                    'required' => false,
                ]);
        }
        return $this;
    }

    /**
     * @todo Improve DataTextarea or explode options here with a ":".
     */
    protected function prepareValueOptions($filter): array
    {
        $options = $filter['options'];
        if ($options === null || $options === [] || $options === '') {
            if (preg_match('~[\w-]+:[\w-]+~', $filter['field'])) {
                return $this->listValuesForProperty($filter['field']);
            }
            return [];
        }
        if (is_string($options)) {
            $options = array_filter(array_map('trim', explode($options)), 'strlen');
        } elseif (!is_array($options)) {
            return [(string) $options => $options];
        }
        // Avoid issue with duplicates.
        $options = array_filter(array_keys(array_flip($options)), 'strlen');
        return array_combine($options, $options);
    }

    /**
     * Get an associative list of all unique values of a property.
     *
     * @todo Use the real search engine, not the internal one.
     * @todo Use a suggester for big lists.
     * @todo Support any resources, not only item.
     */
    protected function listValuesForProperty(string $field): array
    {
        if ($this->references) {
            $list = $this->references->__invoke(
                [$field],
                $this->site ? ['site_id' => $this->site->id()] : [],
                ['output' => 'associative']
            )->list();
            $list = array_keys(reset($list)['o:references']);
        } else {
            // Simplified from References::listDataForProperty().
            $vocabulary = $this->entityManager->getRepository(\Omeka\Entity\Vocabulary::class)->findOneBy(['prefix' => strtok($field, ':')]);
            if (!$vocabulary) {
                return [];
            }
            $property = $this->entityManager->getRepository(\Omeka\Entity\Property::class)->findOneBy(['vocabulary' => $vocabulary->getId(), 'localName' => strtok(':')]);
            if (!$property) {
                return [];
            }
            $qb = $this->entityManager->createQueryBuilder();
            $expr = $qb->expr();
            $qb
                ->select('COALESCE(value.value, valueResource.title, value.uri) AS val')
                ->from(\Omeka\Entity\Value::class, 'value')
                // This join allow to check visibility automatically too.
                ->innerJoin(\Omeka\Entity\Item::class, 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
                // The values should be distinct for each type.
                ->leftJoin(\Omeka\Entity\Item::class, 'valueResource', Join::WITH, $expr->eq('value.valueResource', 'valueResource'))
                ->andWhere($expr->eq('value.property', ':property'))
                ->setParameter('property', $property->getId())
                ->groupBy('val')
                ->orderBy('val', 'asc')
            ;
            $list = array_column($qb->getQuery()->getScalarResult(), 'val', 'val');
            // Fix false empty duplicate or values without title.
            $list = array_keys(array_flip($list));
            unset($list['']);
        }
        return array_combine($list, $list);
    }

    protected function getItemSetsOptions($byOwner = false): array
    {
        $select = $this->formElementManager->get(\Omeka\Form\Element\ItemSetSelect::class, []);
        if ($this->site) {
            $select->setOptions([
                'query' => ['site_id' => $this->site->id(), 'sort_by' => 'dcterms:title', 'sort_order' => 'asc'],
                'disable_group_by_owner' => true,
            ]);
            // By default, sort is case sensitive. So use a case insensitive sort.
            $valueOptions = $select->getValueOptions();
            natcasesort($valueOptions);
        } else {
            $select->setOptions([
                'query' => ['sort_by' => 'dcterms:title', 'sort_order' => 'asc'],
                'disable_group_by_owner' => !$byOwner,
            ]);
            $valueOptions = $select->getValueOptions();
        }
        return $valueOptions;
    }

    protected function getOwnerOptions(): array
    {
        $select = $this->formElementManager->get(\Omeka\Form\Element\UserSelect::class, []);
        return $select->getValueOptions();
    }

    public function setBasePath(string $basePath): Form
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function setSite(?SiteRepresentation $site): Form
    {
        $this->site = $site;
        return $this;
    }

    public function setSiteSetting(?Setting $siteSetting = null): Form
    {
        $this->siteSetting = $siteSetting;
        return $this;
    }

    public function setFormElementManager($formElementManager): Form
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }

    public function setEntityManager(EntityManager $entityManager): Form
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    public function setReferences(?References $references): Form
    {
        $this->references = $references;
        return $this;
    }
}
