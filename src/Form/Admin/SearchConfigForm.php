<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau 2017-2026
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
            ->setAttribute('id', 'search-config-edit-form')
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
                'name' => 'o:search_engine',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Search engine', // @translate
                    'value_options' => $this->getSearchEngineOptions(),
                    'empty_option' => 'Select a search engine below…', // @translate
                ],
                'attributes' => [
                    'id' => 'search_engine',
                    'required' => true,
                ],
            ])

            ->add([
                'name' => 'o:form_adapter',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Form', // @translate
                    'value_options' => $this->getFormsOptions(),
                    'empty_option' => 'Select a form below…', // @translate
                ],
                'attributes' => [
                    'id' => 'form_adapter',
                    'required' => true,
                ],
            ])

            ->add([
                'name' => 'manage_config_default',
                'type' => CommonElement\OptionalSiteSelect::class,
                'options' => [
                    'label' => 'Default search page for admin and sites', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'none' => '[No site]', // @translate
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
                'type' => CommonElement\OptionalSiteSelect::class,
                'options' => [
                    'label' => 'Availability on sites', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        // This option is useless, since each site should be
                        // disable individually.
                        // 'disable' => 'Make unavailable in all sites', // @ translate
                        'enable' => 'Make available in all sites', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'manage_config_availability',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select sites…', // @translate
                    'value' => [
                        'enable',
                    ],
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

    protected function getSearchEngineOptions(): array
    {
        $options = [];

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $this->apiManager->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            $options[$searchEngine->id()] =
            sprintf('%s (%s)', $searchEngine->name(), $searchEngine->engineAdapterLabel());
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
