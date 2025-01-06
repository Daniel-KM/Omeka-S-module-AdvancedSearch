<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

use Common\Form\Element\TraitOptionalElement;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Element\Select;
use Laminas\I18n\Translator\TranslatorAwareTrait;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Manager as ApiManager;

/**
 * The element FieldSelect is derivated from PropertySelect with specific fields.
 * For now, it is the same output with the same options.
 *
 * @todo Manage aliases and labels of properties for each resource template.
 */
class FieldSelect extends Select implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;
    use TraitOptionalElement;
    use TranslatorAwareTrait;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    public function setApiManager(ApiManager $apiManager): self
    {
        $this->api = $apiManager;
        return $this;
    }

    /**
     * @return ApiManager
     */
    public function getApiManager(): ApiManager
    {
        return $this->api;
    }

    /**
     * Get the resource name.
     *
     * @return string
     */
    public function getResourceName()
    {
        return 'properties';
    }

    /**
     * Get value options for properties.
     *
     * If the "apply_templates" option is set, get only the properties of the
     * configured resource templates and include alternate labels, if any.
     * Otherwise get the default value options.
     */
    public function getValueOptions(): array
    {
        $applyTemplates = $this->getOption('apply_templates');
        $applyTemplates = is_array($applyTemplates) ? $applyTemplates : false;
        if (!$applyTemplates) {
            // Use default method.
            return $this->_getValueOptions();
        }
        // Get only the properties of the configured resource templates.
        $valueOptions = [];
        $termAsValue = $this->getOption('term_as_value');
        foreach ($applyTemplates as $templateId) {
            try {
                $template = $this->getApiManager()->read('resource_templates', $templateId)->getContent();
            } catch (NotFoundException $e) {
                continue;
            }
            foreach ($template->resourceTemplateProperties() as $templateProperty) {
                $property = $templateProperty->property();
                if (!isset($valueOptions[$property->id()])) {
                    $valueOptions[$property->id()] = [
                        'label' => $this->getTranslator()->translate($property->label()),
                        'value' => $termAsValue ? $property->term() : $property->id(),
                        'alternate_labels' => [],
                        'attributes' => [
                            'data-term' => $property->term(),
                            'data-property-id' => $property->id(),
                            'title' => $property->term(),
                        ],
                    ];
                }
                $valueOptions[$property->id()]['alternate_labels'][] = $templateProperty->alternateLabel();
            }
        }
        // Include alternate labels, if any.
        foreach ($valueOptions as $propertyId => $option) {
            $altLabels = array_unique(array_filter($valueOptions[$propertyId]['alternate_labels']));
            if ($altLabels) {
                $valueOptions[$propertyId]['label'] = sprintf(
                    '%s (%s)',
                    $valueOptions[$propertyId]['label'],
                    implode(', ', $altLabels)
                );
            }
        }
        // Sort options alphabetically.
        usort($valueOptions, fn ($a, $b) => strcasecmp($a['label'], $b['label']));
        return $valueOptions;
    }

    /**
     * Get default value options for this vocabulary member.
     */
    protected function _getValueOptions(): array
    {
        $events = $this->getEventManager();
        $resourceName = $this->getResourceName();

        $query = $this->getOption('query');
        if (!is_array($query)) {
            $query = [];
        }
        if (!isset($query['sort_by'])) {
            $query['sort_by'] = 'label';
        }
        // Allow handlers to filter the query.
        $args = $events->prepareArgs(['query' => $query]);
        $events->trigger('form.vocab_member_select.query', $this, $args);
        $query = $args['query'];

        $valueOptions = [];
        $response = $this->getApiManager()->search($resourceName, $query);
        $termAsValue = $this->getOption('term_as_value');
        foreach ($response->getContent() as $member) {
            $attributes = ['data-term' => $member->term()];
            if ('properties' === $resourceName) {
                $attributes['data-property-id'] = $member->id();
            } elseif ('resource_classes' === $resourceName) {
                $attributes['data-resource-class-id'] = $member->id();
            }
            $attributes['title'] = $member->term();
            $option = [
                'label' => $member->label(),
                'value' => $termAsValue ? $member->term() : $member->id(),
                'attributes' => $attributes,
            ];
            $vocabulary = $member->vocabulary();
            if (!isset($valueOptions[$vocabulary->prefix()])) {
                $valueOptions[$vocabulary->prefix()] = [
                    'label' => $vocabulary->label(),
                    'options' => [],
                ];
            }
            $valueOptions[$vocabulary->prefix()]['options'][] = $option;
        }
        // Move Dublin Core vocabularies (dcterms & dctype) to the beginning.
        if (isset($valueOptions['dcterms'])) {
            $valueOptions = ['dcterms' => $valueOptions['dcterms']] + $valueOptions;
        }
        if (isset($valueOptions['dctype'])) {
            $valueOptions = ['dctype' => $valueOptions['dctype']] + $valueOptions;
        }

        // Prepend configured value options.
        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }

        // Allow handlers to filter the value options.
        $args = $events->prepareArgs(['valueOptions' => $valueOptions]);
        $events->trigger('form.vocab_member_select.value_options', $this, $args);
        $valueOptions = $args['valueOptions'];

        return $valueOptions;
    }
}
