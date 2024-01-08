'use strict';

/**
 * Manage the improvements of the standard advanced search.
 */

/**
 * Improve methods "disableQueryTextInput" and "cleanSearchQuery"
 * of Omeka from application/asset/js/global.js.
 * In some themes or pages, the js variable Omeka may be missing.
 */
var Omeka = Omeka || {
    jsTranslate: function(str) { return str; },
};

$(document).ready(function() {

    const hasChosenSelect = typeof $.fn.chosen === 'function';

    /**
     * Add chosen-select when possible.
     */
    if (hasChosenSelect) {
        /**
         * @see application/asset/js/chosen-options.js
         */
        var chosenOptions = chosenOptions || {
            allow_single_deselect: true,
            disable_search_threshold: 10,
            width: '100%',
            include_group_label_in_selected: true,
        };
        // Group labels are too long for sidebar selects.
        var chosenOptionsSidebar = chosenOptions;
        chosenOptionsSidebar.include_group_label_in_selected = false;
    }

    /**
     * Handle query text according to some query types.
     * @see application/asset/js/global.js Omeka.disableQueryTextInput()
     */
    Omeka.handleQueryTextInput = function(queryType) {
        queryType = queryType ? queryType : $(this);
        const typeQuery = queryType.val();
        const isTypeWithoutText = ['ex', 'nex', 'exs', 'nexs', 'exm', 'nexm', 'lex', 'nlex', 'tpl', 'ntpl', 'tpr', 'ntpr', 'tpu', 'ntpu'].includes(typeQuery);
        const isTypeSubQuery = ['resq', 'nresq', 'lkq', 'nlkq'].includes(typeQuery);
        const isTypeDataType = ['dtp', 'ndtp'].includes(typeQuery);
        const isTypeMainType = ['tp', 'ntp'].includes(typeQuery);
        const queryTextInput = queryType.siblings('.query-text:not(.query-data-type):not(.query-main-type)');
        const queryTextDataType = queryType.siblings('.query-data-type');
        const queryTextMainType = queryType.siblings('.query-main-type');
        queryTextInput.prop('disabled', isTypeWithoutText || isTypeSubQuery || isTypeDataType || isTypeMainType);
        queryTextDataType.prop('disabled', !isTypeDataType);
        queryTextMainType.prop('disabled', !isTypeMainType);
        if (hasChosenSelect) {
            queryTextDataType.chosen('destroy');
            queryTextDataType.find('+ .chosen-container').remove();
            queryTextDataType.chosen(chosenOptions);
            queryTextMainType.chosen('destroy');
            queryTextMainType.find('+ .chosen-container').remove();
            queryTextMainType.chosen(chosenOptions);
        }
    };

    Omeka.cleanFormSearchQuery = function(form) {
        const inputNames = [
            'fulltext_search',
            'resource_class_id[]',
            'resource_template_id[]',
            'item_set_id[]',
            'not_item_set_id[]',
            'site_id',
            'owner_id',
            'media_type',
            'sort_by',
            'sort_order',
            'is_public',
            'has_media',
            'id',
            // Modules.
            // Access.
            'access',
            // Advanced Search.
            'has_original',
            'has_thumbnails',
            // Data Type Geometry.
            'geo[box]',
            'geo[zone]',
            'geo[mapbox]',
            'geo[area]',
            // Mapping.
            'has_markers',
        ];
        const inputFakes = [
            // Data Type Geometry.
            'geo[mode]',
            // Numeric Data Types
             'numeric-toggle-time-checkbox',
            'year',
            'month',
            'day',
            'hour',
            'minute',
            'second',
            'offset',
            'years',
            'months',
            'days',
            'hours',
            'minutes',
            'seconds',
            'integer',
        ];
        const propertyQueryTypeWithText = ['eq', 'neq', 'in', 'nin', 'res', 'nres', 'resq', 'nresq', 'list', 'nlist', 'sw', 'nsw', 'ew', 'new', 'near', 'nnear', 'lres', 'nlres', 'lkq', 'nlkq', 'dtp', 'ndtp', 'tp', 'ntp', 'gt', 'gte', 'lte', 'lt'];
        form.find(":input[name]:not([name='']):not(:disabled)").each(function(index) {
            const input = $(this);
            const inputName = input.attr('name');
            const inputValue = input.val();
            var match;
            if (inputFakes.includes(inputName)) {
                input.prop('name', '');
            }
            // Module Data Type Geometry.
            else if (['geo[around][x]', 'geo[around][y]', 'geo[around][latitude]', 'geo[around][longitude]', 'geo[around][radius]'].includes(inputName)) {
                const xInput = form.find('[name="geo[around][x]"]');
                const yInput = form.find('[name="geo[around][y]"]');
                const hasPosition = $.isNumeric(xInput.val()) && $.isNumeric(yInput.val()) ;
                const latitudeInput = form.find('[name="geo[around][latitude]"]');
                const longitudeInput = form.find('[name="geo[around][longitude]"]');
                const hasCoordinates = $.isNumeric(latitudeInput.val()) && $.isNumeric(longitudeInput.val()) ;
                const radiusInput = form.find('[name="geo[around][radius]"]');
                const unitInput = form.find('[name="geo[around][unit]"]');
                const hasRadius = radiusInput.val() !== '';
                if (hasRadius) {
                    if (!hasPosition) {
                        xInput.prop('name', '');
                        yInput.prop('name', '');
                    }
                    if (!hasCoordinates) {
                        latitudeInput.prop('name', '');
                        longitudeInput.prop('name', '');
                        unitInput.prop('name', '');
                    }
                    if (!hasPosition && !hasCoordinates) {
                        radiusInput.prop('name', '');
                        unitInput.prop('name', '');
                    }
                } else {
                    xInput.prop('name', '');
                    yInput.prop('name', '');
                    latitudeInput.prop('name', '');
                    longitudeInput.prop('name', '');
                    radiusInput.prop('name', '');
                    unitInput.prop('name', '');
                }
            }
            // Empty values.
            else if (inputName && '' === inputValue) {
                if (inputNames.includes(inputName)) {
                    input.prop('name', '');
                } else if (match = inputName.match(/property\[(\d+)\]\[text\]/)) {
                    const propertyType = form.find(`[name="property[${match[1]}][type]"]`);
                    if (propertyQueryTypeWithText.includes(propertyType.val())) {
                        form.find(`[name="property[${match[1]}][joiner]"]`).prop('name', '');
                        form.find(`[name="property[${match[1]}][property]"]`).prop('name', '');
                        form.find(`[name="property[${match[1]}][text]"]`).prop('name', '');
                        propertyType.prop('name', '');
                    }
                }
                // Module Advanced Search.
                else if (match = inputName.match(/datetime\[(\d+)\]\[(field|type|value)\]/)) {
                    form.find(`[name="datetime[${match[1]}][joiner]"]`).prop('name', '');
                    form.find(`[name="datetime[${match[1]}][field]"]`).prop('name', '');
                    form.find(`[name="datetime[${match[1]}][type]"]`).prop('name', '');
                    form.find(`[name="datetime[${match[1]}][value]"]`).prop('name', '');
                }
                // Module Mapping.
                else if (['mapping_address', 'mapping_radius', 'mapping_radius_unit'].includes(inputName)) {
                    const address = form.find('[name="mapping_address"]').val();
                    const radius = form.find('[name="mapping_radius"]').val();
                    if (!address || address.trim() === '' || !radius || !parseFloat(radius)) {
                        form.find('[name="mapping_address"]').prop('name', '');
                        form.find('[name="mapping_radius"]').prop('name', '');
                        form.find('[name="mapping_radius_unit"]').prop('name', '');
                    }
                }
                // Module Numeric Data Types.
                else if (match = inputName.match(/numeric\[(ts\]\[gte|ts\]\[lte|dur\]\[gt|dur\]\[lt|ivl|int\]\[gt|int\]\[lt)\]\[(pid|val)\]/)) {
                    const pidOrVal = match[2] === 'pid' ? 'val' : 'pid';
                    const pidOrValInput = form.find(`[name="numeric[${match[1]}][${pidOrVal}]"]`);
                    input.prop('name', '');
                    pidOrValInput.prop('name', '');
                }
            }
            // Empty order.
            else if (inputName === 'sort_order') {
                const sortByInput = form.find('[name="sort_by"]');
                const sortBy = sortByInput.val();
                if (!sortBy || sortBy.trim() === '') {
                    sortByInput.prop('name', '');
                    input.prop('name', '');
                }
            }
        });
    };

    /**
     * Handle negative item set search  when js is removed from template.
     * @see application/view/common/advanced-search/item-sets.phtml
     * @see Pull request "fix/advanced_search_templates" on https://github.com/omeka/omeka-s
     */
    $(document).on('change', '#advanced-search .item-set-select-type', function() {
        const typeSelect = $(this);
        const itemSetSelect = typeSelect.closest('.value').find('.item-set-select');
        if ('not_in' === typeSelect.val()) {
            itemSetSelect.attr('name', 'not_item_set_id[]');
        } else {
            itemSetSelect.attr('name', 'item_set_id[]');
        }
    });

    /**
     * Add chosen-select when possible.
     */
    if (hasChosenSelect) {
        $('#advanced-search').find('#property-queries .value select, #resource-class .value select, #resource-templates .value select, #item-sets .value select, #datetime-queries .value select, select#site_id, select#owner_id')
            .addClass('chosen-select');
        $('#advanced-search select.chosen-select').chosen(chosenOptions);
        $('#advanced-search select.chosen-select option[value=""][selected]').prop('selected',  false).parent().trigger('chosen:updated');

        $(document).on('o:value-created', '#property-queries .value, #resource-class .value, #resource-templates .value, #item-sets .value, #datetime-queries .value', function(e) {
            const newValue = $(this);
            newValue.find('select').chosen('destroy');
            newValue.find('.chosen-container').remove();
            newValue.find('select').addClass('chosen-select').chosen(chosenOptions);
        });
    }

    // Skip core functions (global.js), since is is improved above.
    $(document).off('change', '.query-type');
    $(document).on('change', '.query-type', function () {
         Omeka.handleQueryTextInput($(this));
    });

    // Updating querying should be done on load too.
    $('#property-queries .query-type').each(function() {
         Omeka.handleQueryTextInput($(this));
    });

    /**
     * Handle clearing fields on new property multi-value.
     * @see application/asset/js/advanced-search.js.
     */
    $(document).on('o:value-created', '#property-queries .value', function(e) {
        // In advanced-search.js, "children" is used, but it is not possible here,
        // because a div is inserted to manage sub-query form.
        // Furthermore, there is a hidden input.
        const newValue = $(this);
        const isSidebar = newValue.parents('.sidebar').length > 0;
        if (isSidebar) {
            newValue.find(".query-type option[value='resq']").remove();
            newValue.find(".query-type option[value='nresq']").remove();
            newValue.find(".query-type option[value='lkq']").remove();
            newValue.find(".query-type option[value='nlkq']").remove();
            newValue.find('.query-form-element').remove();
        } else {
            newValue.find('.query-form-element').attr('data-query', '').hide();
            newValue.find('.query-form-element input[type="hidden"]').val(null);
            newValue.find('.query-form-element .search-filters').empty().html(Omeka.jsTranslate('[Edit below]'));
        }
        newValue.children().children('input[type="text"]').val(null);
        newValue.children().children('select').prop('selectedIndex', 0);
        newValue.children().children('.query-property').find('option:selected').prop('selected', false);
        Omeka.handleQueryTextInput(newValue.find('.query-type'));
        if (hasChosenSelect) {
            if (isSidebar) {
                newValue.children().children('select.chosen-select').chosen(chosenOptionsSidebar);
            }
            newValue.children().children('select.chosen-select').trigger('chosen:updated');
        }
        --Omeka.propertySearchIndex;
        newValue.find(':input').attr('name', function () {
            return this.name.replace(/\[\d\]/, '[' + Omeka.propertySearchIndex + ']');
        });
        ++Omeka.propertySearchIndex;
    });

    /**
     * Copy of resource-selector for sidebar (not loaded in search form).
     * @see application/asset/js/resource-selector.js
     */
    $(document).on('o:sidebar-content-loaded', '#query-sidebar-edit', function(e) {
        // Don't allow sub-sub-queries for now.
        const sidebar = $(this);
        sidebar.find(".query-type option[value='resq']").remove();
        sidebar.find(".query-type option[value='nresq']").remove();
        sidebar.find(".query-type option[value='lkq']").remove();
        sidebar.find(".query-type option[value='nlkq']").remove();
        sidebar.find('.query-form-element').remove();
        if (hasChosenSelect) {
            sidebar.find('select.item-set-select-type, select#site_id, select#owner_id').addClass('chosen-select');
            sidebar.find('select.chosen-select').chosen(chosenOptionsSidebar);
            sidebar.find('select.chosen-select option[value=""][selected]').prop('selected',  false).parent().trigger('chosen:updated');
            sidebar.find('select.chosen-select').trigger('chosen:update');
        }
        Omeka.handleQueryTextInput(sidebar.find('.query-type'));
    });

    // Clean the query before submitting the form.
    $(document).off('submit', '#advanced-search');
    $(document).on('submit', '#advanced-search', function(e) {
        Omeka.cleanFormSearchQuery($(this));
    });

});
