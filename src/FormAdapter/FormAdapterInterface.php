<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2024
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

namespace AdvancedSearch\FormAdapter;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;

interface FormAdapterInterface
{
    public function getLabel(): string;

    public function setSearchConfig(?SearchConfigRepresentation $searchConfig): \AdvancedSearch\FormAdapter\FormAdapterInterface;

    public function getConfigFormClass(): ?string;

    /**
     * Get the search form.
     *
     * @param array $options Options are same than renderForm() except template.
     *   Default keys:
     *   - skip_form_action (bool): Don't set form action, so use the current page.
     *   - skip_partial_headers (bool): Skip partial headers.
     *   Other options are passed to the partial.
     */
    public function getForm(array $options = []): ?\Laminas\Form\Form;

    /**
     * The form class to use to build the search form, if any.
     */
    public function getFormClass(): ?string;

    /**
     * Optional partial to allow to prepare the rendering of the form.
     *
     * The partial will no be output: it is used to append css and js.
     */
    public function getFormPartialHeaders(): ?string;

    public function getFormPartial(): ?string;

    /**
     * Render the form.
     *
     * @param array $options Options are same than getForm() except template.
     *   Default keys:
     *   - template (string): Use a specific template instead of the default one.
     *   This is the template of the form, not the main template of the search page.
     *   - skip_form_action (bool): Don't set form action, so use the current page.
     *   - skip_partial_headers (bool): Skip partial headers.
     *   Other options are passed to the partial.
     * @return string Html form.
     */
    public function renderForm(array $options = []): string;

    /**
     * Convert a user query from a form into a search query via a form mapping.
     *
     * The mapping between the query arguments that comes from a form and the
     * fields managed by the index is set via the form settings of the config.
     *
     * @param array $request The user query formatted by the form.
     * @param array $formSettings The specific settings of the form page.
     * @return \AdvancedSearch\Query The normalized query of the module Search.
     */
    public function toQuery(array $request, array $formSettings): \AdvancedSearch\Query;
}
