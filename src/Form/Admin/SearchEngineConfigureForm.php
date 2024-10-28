<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2024
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

use Laminas\Form\Element;
use Laminas\Form\Form;

class SearchEngineConfigureForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Name', // @translate
                ],
                'attributes' => [
                    'id' => 'o-name',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'resource_types',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Resources indexed and searchable', // @translate
                    'label_attributes' => ['style' => 'display: block'],
                    'value_options' => $this->getResourcesTypes(),
                ],
                'attributes' => [
                    'id' => 'resource_types',
                    'value' => [
                        'items',
                    ],
                ],
            ])
            ->add([
                'name' => 'visibility',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Visibility', // @translate
                    'value_options' => [
                        'all' => 'Public and private', // @translate
                        'public' => 'Public only', // @translate
                        'private' => 'Private only', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'visibility',
                    'value' => 'all',
                ],
            ])
        ;
    }

    /**
     * Get the list of the resource types to search.
     *
     * @todo Get list from engine? See git commit 28b1787.
     */
    protected function getResourcesTypes(): array
    {
        // Set the order for the results: item sets before items.
        return [
            'resources' => 'Resources (mixed)', // @translate
            'item_sets' => 'Item sets',
            'items' => 'Items',
            'media' => 'Media',
            // Value annotations are managed with resources.
            // 'value_annotations' => 'Value annotations',
            'annotations' => 'Annotations',
            // Not managed for now.
            // 'site_pages' => 'Site pages',
        ];
    }
}
