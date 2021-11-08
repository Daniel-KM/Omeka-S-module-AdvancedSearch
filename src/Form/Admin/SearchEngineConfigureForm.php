<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2021
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
use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\I18n\Translator\TranslatorAwareTrait;

class SearchEngineConfigureForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    protected $apiManager;

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
                'name' => 'resources',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Resources indexed', // @translate
                    'value_options' => $this->getResourcesOptions(),
                ],
                'attributes' => [
                    'id' => 'resources',
                    'value' => ['items'],
                ],
            ]);
    }

    /**
     * Get the list of resource types.
     *
     * @todo There may be other resources types to index for search: media, external resources types. See git history of this file.
     *
     * @return array
     */
    protected function getResourcesOptions(): array
    {
        return [
            'items' => 'Items',
            'item_sets' => 'Item sets',
        ];
    }

    public function setApiManager($apiManager): self
    {
        $this->apiManager = $apiManager;
        return $this;
    }
}
