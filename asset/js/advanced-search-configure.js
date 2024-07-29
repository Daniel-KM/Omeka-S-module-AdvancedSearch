'use strict';

$(document).ready(function() {

    const hasChosenSelect = typeof $.fn.chosen === 'function';

    /**
     * Search configure form.
     */

    const $formConfig = $('#form-search-config-configure');

    var SearchConfig = (function() {

        var self = {};

        /**
        * Chosen default options.
        * @see https://harvesthq.github.io/chosen/
        */
        self.chosenOptions = {
            allow_single_deselect: true,
            disable_search_threshold: 10,
            width: '100%',
            include_group_label_in_selected: true,
        };

        self.init = function() {
            self.fieldsetUpdateButtons();
            self.fieldsetUpdateLabels();

            // Move the button plus inside the previous fieldset.
            $formConfig.find('.search-fieldset-plus').each(function(no, button) {
                $(button).appendTo($(button).prev('fieldset'));
            });

            $formConfig.on('click', '.search-fieldset-minus', self.fieldsetRemove);
            $formConfig.on('click', '.search-fieldset-plus', self.fieldsetAppend);
            $formConfig.on('click', '.search-fieldset-up', self.fieldsetMoveUp);
            $formConfig.on('click', '.search-fieldset-down', self.fieldsetMoveDown);
            return self;
        };

        self.fieldsetRemove = function(ev) {
            $(ev.currentTarget).closest('fieldset').remove();
            self.fieldsetUpdateButtons();
            self.fieldsetUpdateLabels();
            return self;
        };

        self.fieldsetAppend = function(ev) {
            const $fieldset = $(ev.currentTarget).closest('fieldset');
            const template = $fieldset.find('> span[data-template]').attr('data-template');
            if (template) {
                var maxIndex = 0;
                $fieldset.find('> fieldset').each(function(no, item) {
                    const fieldsetName = $(item).attr('name');
                    const fieldsetIndex = fieldsetName.replace(/\D+/g, '');
                    maxIndex = Math.max(maxIndex, fieldsetIndex);
                });
                $fieldset.append(template.split('__index__').join(++maxIndex));
                // Move button plus last in the fieldset.
                $(ev.currentTarget).appendTo($fieldset)
                self.fieldsetUpdateButtons();
                self.fieldsetUpdateLabels();
                if (hasChosenSelect) {
                    $fieldset.find('.chosen-select').chosen(self.chosenOptions);
                }
            }
            return self;
        };

        self.fieldsetMoveUp = function(ev) {
            const current = $(ev.currentTarget).closest('fieldset');
            const previous = current.prev('fieldset');
            current.insertBefore(previous);
            self.fieldsetUpdateButtons();
            self.fieldsetUpdateLabels();
            return self;
        };

        self.fieldsetMoveDown = function(ev) {
            const current = $(ev.currentTarget).closest('fieldset');
            const next = current.next('fieldset');
            current.insertAfter(next);
            self.fieldsetUpdateButtons();
            self.fieldsetUpdateLabels();
            return self;
        };

        self.fieldsetUpdateButtons = function() {
            // Remove the field wrapping new buttons.
            $('.search-fieldset-action').each(function(no, button) {
                const field = button.closest('.field');
                if (field) {
                    $(button).insertBefore(field);
                    field.remove();
                }
            });
            // Enable or disable up/down buttons in each fieldset.
            var buttons = $formConfig.find('.search-fieldset-up');
            $formConfig.find('.search-fieldset-up').each(function(index, button) {
                button = $(button);
                const fieldset = button.closest('fieldset');
                if (index <= 0) {
                    button.attr('disabled', 'disabled');
                } else {
                    button.removeAttr('disabled');
                }
            });
            buttons = $formConfig.find('.search-fieldset-down');
            $formConfig.find('.search-fieldset-down').each(function(index, button) {
                button = $(button);
                const fieldset = button.closest('fieldset');
                if (index >= (buttons.length - 1)) {
                    button.attr('disabled', 'disabled');
                } else {
                    button.removeAttr('disabled');
                }
            });
            return self;
        };

        self.fieldsetUpdateLabels = function() {
            $('.form-fieldset-collection[data-label-index] > fieldset').each(function(index, fieldset) {
                fieldset = $(fieldset);
                const labelIndex = fieldset.parent().data('label-index');
                var legend = fieldset.find('> legend');
                if (!legend.length) {
                    fieldset.prepend('<legend></legend>')
                    legend = fieldset.find('> legend');
                }
                legend.text(labelIndex.replace('{index}', index + 1));
            });
            return self;
        };

        return self;

    })();

    SearchConfig.init();

    /**
     * External search engine for api.
     */

    /**
     * Add a button to automap the closest field name for the api mapping.
     */
    $('#metadata')
        .before('<button id="api_mapping_auto" title="Try to map automatically the metadata and the properties that are not mapped yet with the fields of the index">' + Omeka.jsTranslate('Automatic mapping of empty values') + '</button>')

    $('#api_mapping_auto').on('click', function(event) {
        event.preventDefault();
        $('#api_mapping_auto')
            .prop('disabled', true)
            .html(Omeka.jsTranslate('Processing…'));
        $('#metadata select:has(option[value=""]:selected)').each(function() {
            var term = $(this).prop('name').replace('form[' + $(this).closest('fieldset').prop('id') + '][', '').replace(']', '');
            var normTerm = term.replace(':', '_');
            var normTermU = term.replace(':', '_') + '_';
            $(this).find('> option').each(function() {
                var value = this.value;
                if (value === term || value === normTerm || value === normTermU) {
                    $(this).prop('selected', true);
                    $(this).parent().trigger('chosen:updated');
                    // Map first index.
                    return false;
                }
                // Separated in order to check full path first.
                if (value.startsWith(term) || value.startsWith(normTermU)) {
                    $(this).prop('selected', true);
                    $(this).parent().trigger('chosen:updated');
                    // Map first index.
                    return false;
                }
            });
        });
        $('#api_mapping_auto')
            .prop('disabled', false)
            .html(Omeka.jsTranslate('Automatic mapping of empty values'));
    });

});
