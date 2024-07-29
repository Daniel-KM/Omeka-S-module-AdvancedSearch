<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau 2017-2024
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

namespace AdvancedSearch\Form\Admin;

use AdvancedSearch\FormAdapter\Manager as SearchFormAdapterManager;
use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Api\Manager as ApiManager;
use Omeka\Form\Element as OmekaElement;

class SearchConfigForm extends Form
{
    /**
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * @var SearchFormAdapterManager
     */
    protected $formAdapterManager;

    public function init(): void
    {
        $this
            ->setAttribute('id', 'form-search-config-edit')
            ->add([
                'name' => 'o:name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Name', // @translate
                ],
                'attributes' => [
                    'id' => 'name',
                    'required' => true,
                ],
            ])

            ->add([
                'name' => 'o:slug',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Slug', // @translate
                    'info' => 'The slug to the search form. The site slug will be automatically prepended.',
                ],
                'attributes' => [
                    'id' => 'slug',
                    'required' => true,
                ],
            ])

            ->add([
                'name' => 'o:engine',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Search engine', // @translate
                    'value_options' => $this->getEnginesOptions(),
                    'empty_option' => 'Select a search engine below…', // @translate
                ],
                'attributes' => [
                    'id' => 'engine',
                    'required' => true,
                ],
            ])

            ->add([
                'name' => 'o:form',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Form', // @translate
                    'value_options' => $this->getFormsOptions(),
                    'empty_option' => 'Select a form below…', // @translate
                ],
                'attributes' => [
                    'id' => 'form',
                    'required' => true,
                ],
            ])

            ->add([
                'name' => 'manage_config_default',
                'type' => OmekaElement\SiteSelect::class,
                'options' => [
                    'label' => 'Set as default search page for sites', // @translate
                    'empty_option' => '[No change]', // @translate
                    'info' => 'The page will be made available on all selected sites. This param can be set in each site settings too.', // @translate
                    'prepend_value_options' => [
                        'all' => '[All sites]', // @translate
                        'admin' => 'Admin', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'manage_config_default',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select sites…', // @translate
                ],
            ])
            ->add([
                'name' => 'manage_config_availability',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Availability on sites', // @translate
                    'info' => 'The admin settings are not modified.', // @translate
                    'value_options' => [
                        'disable' => 'Make unavailable in all sites', // @translate
                        'let' => 'Don’t modify', // @translate
                        'enable' => 'Make available in all sites', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'manage_config_availability',
                    'value' => 'let',
                ],
            ])
        ;

        $this
            ->getInputFilter()
            ->add([
                'name' => 'manage_config_default',
                'required' => false,
            ])
        ;
    }

    public function setApiManager(ApiManager $apiManager): self
    {
        $this->apiManager = $apiManager;
        return $this;
    }

    public function setFormAdapterManager(SearchFormAdapterManager $formAdapterManager): self
    {
        $this->formAdapterManager = $formAdapterManager;
        return $this;
    }

    protected function getEnginesOptions(): array
    {
        $options = [];

        $engines = $this->apiManager->search('search_engines')->getContent();
        foreach ($engines as $engine) {
            $options[$engine->id()] =
                sprintf('%s (%s)', $engine->name(), $engine->adapterLabel());
        }

        return $options;
    }

    protected function getFormsOptions(): array
    {
        $options = [];

        $formAdapterNames = $this->formAdapterManager->getRegisteredNames();
        foreach ($formAdapterNames as $name) {
            $formAdapter = $this->formAdapterManager->get($name);
            $options[$name] = $formAdapter->getLabel();
        }

        return $options;
    }
}
