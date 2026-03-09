'use strict';

/**
 * Manage the improvements of the standard advanced search.
 *
 * Improve methods "disableQueryTextInput" and "cleanSearchQuery" of Omeka.
 *
 * @see application/asset/js/global.js.
 */

/**
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
     *
     * @see application/asset/js/global.js Omeka.disableQueryTextInput()
     */
    Omeka.handleQueryTextInput = function(queryType) {
        queryType = queryType ? queryType : $(this);
        const typeQuery = queryType.val();
        const isTypeWithoutText = ['ex', 'nex', 'exs', 'nexs', 'exm', 'nexm', 'lex', 'nlex', 'tpl', 'ntpl', 'tpr', 'ntpr', 'tpu', 'ntpu', 'dup', 'ndup', 'dupl', 'ndupl', 'dupt', 'ndupt', 'duptl', 'nduptl', 'dupv', 'ndupv', 'dupvl', 'ndupvl', 'dupvt', 'ndupvt', 'dupvtl', 'ndupvtl', 'dupr', 'ndupr', 'duprl', 'nduprl', 'duprt', 'nduprt', 'duprtl', 'nduprtl', 'dupu', 'ndupu', 'dupul', 'ndupul', 'duput', 'nduput', 'duputl', 'nduputl'].includes(typeQuery);
        const isTypeSubQuery = ['resq', 'nresq', 'lkq', 'nlkq'].includes(typeQuery);
        const isTypeDataType = ['dt', 'ndt', 'dtp', 'ndtp'].includes(typeQuery);
        const isTypeMainType = ['tp', 'ntp'].includes(typeQuery);
        const queryTextInput = queryType.siblings('.query-text:not(.query-data-type):not(.query-text-data-type):not(.query-main-type)');
        const queryTextSubQuery = queryType.closest('.value').find('.sub-query .query-form-query');
        const queryTextDataType = queryType.siblings('.query-data-type, .query-text-data-type');
        const queryTextMainType = queryType.siblings('.query-main-type');
        queryTextInput.prop('disabled', isTypeWithoutText || isTypeSubQuery || isTypeDataType || isTypeMainType);
        queryTextSubQuery.prop('disabled', !isTypeSubQuery);
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

    Omeka.disableQueryTextInput = function() {
        Omeka.handleQueryTextInput($(this));
    }

    Omeka.cleanFormSearchQuery = function(form) {
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
        const fieldQueryTypeWithText = ['eq', 'neq', 'in', 'nin', 'sw', 'nsw', 'ew', 'new', 'near', 'nnear', 'ma', 'nma', 'lt', 'lte', 'gte', 'gt', '<', '≤', '≥', '>', 'yreq', 'nyreq', 'yrlt', 'yrlte', 'yrgte', 'yrgt', 'list', 'nlist', 'res', 'nres', 'resq', 'nresq', 'lres', 'nlres', 'lkq', 'nlkq', 'dt', 'ndt', 'dtp', 'ndtp', 'tp', 'ntp'];

        // First pass: remove all inputs with empty value, submit buttons, and
        // fake inputs. Note: "0" is a valid value.
        form.find(':input[name]:not(:disabled)').each(function() {
            const input = $(this);
            if (input.is('[type="submit"]')
                || inputFakes.includes(input.attr('name'))
                || input.val() === ''
            ) {
                input.prop('name', '');
            }
        });

        // Second pass: remove grouped inputs whose text/val is now empty
        // (property, filter, datetime, geo, mapping, numeric, sort).
        form.find(":input[name]:not([name='']):not(:disabled)").each(function() {
            const input = $(this);
            const inputName = input.attr('name');
            var match;
            // Module Data Type Geometry.
            if (['geo[around][x]', 'geo[around][y]', 'geo[around][latitude]', 'geo[around][longitude]', 'geo[around][radius]'].includes(inputName)) {
                const xInput = form.find('[name="geo[around][x]"]');
                const yInput = form.find('[name="geo[around][y]"]');
                const hasPosition = $.isNumeric(xInput.val()) && $.isNumeric(yInput.val());
                const latitudeInput = form.find('[name="geo[around][latitude]"]');
                const longitudeInput = form.find('[name="geo[around][longitude]"]');
                const hasCoordinates = $.isNumeric(latitudeInput.val()) && $.isNumeric(longitudeInput.val());
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
            // Core properties: clean group when text is empty.
            else if (match = inputName.match(/property\[(\d+)\]\[type\]/)) {
                const i = match[1];
                if (!form.find(`[name="property[${i}][text]"]`).length) {
                    const type = input.val();
                    if (['eq', 'neq', 'in', 'nin', 'sw', 'nsw', 'ew', 'new', 'res', 'nres', 'dt', 'ndt'].includes(type)) {
                        form.find(`[name="property[${i}][joiner]"]`).prop('name', '');
                        form.find(`[name="property[${i}][property]"]`).prop('name', '');
                        input.prop('name', '');
                    }
                }
            }
            // Module Advanced Search filters.
            else if (match = inputName.match(/filter\[(\d+)\]\[type\]/)) {
                const i = match[1];
                if (!form.find(`[name="filter[${i}][val]"]`).length) {
                    const type = input.val();
                    if (fieldQueryTypeWithText.includes(type)) {
                        form.find(`[name="filter[${i}][join]"]`).prop('name', '');
                        form.find(`[name="filter[${i}][field]"]`).prop('name', '');
                        input.prop('name', '');
                    }
                }
            }
            // Datetime queries.
            else if (match = inputName.match(/datetime\[(\d+)\]\[type\]/)) {
                const i = match[1];
                if (!form.find(`[name="datetime[${i}][val]"]`).length) {
                    const type = input.val();
                    if (!['ex', 'nex'].includes(type)) {
                        form.find(`[name="datetime[${i}][join]"]`).prop('name', '');
                        form.find(`[name="datetime[${i}][field]"]`).prop('name', '');
                        input.prop('name', '');
                    }
                }
            }
            // Module Mapping.
            else if (['mapping_address', 'mapping_radius', 'mapping_radius_unit'].includes(inputName)) {
                const address = form.find('[name="mapping_address"]').val();
                const radius = form.find('[name="mapping_radius"]').val();
                if (!address || !radius || !parseFloat(radius)) {
                    form.find('[name="mapping_address"]').prop('name', '');
                    form.find('[name="mapping_radius"]').prop('name', '');
                    form.find('[name="mapping_radius_unit"]').prop('name', '');
                }
            }
            // Module Numeric Data Types.
            else if (match = inputName.match(/numeric\[(ts\]\[gte|ts\]\[lte|dur\]\[gt|dur\]\[lt|ivl|int\]\[gt|int\]\[lt)\]\[(pid|val)\]/)) {
                const numericType = match[1];
                const pidOrVal = match[2] === 'pid' ? 'val' : 'pid';
                form.find(`[name="numeric[${numericType}][${pidOrVal}]"]`).prop('name', '');
                input.prop('name', '');
            }
            // Empty sort order without sort field.
            else if (inputName === 'sort_order') {
                if (!form.find('[name="sort_by"]').length) {
                    input.prop('name', '');
                }
            }
        });

    };

    /**
     * Handle negative item set search  when js is removed from template.
     *
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
        $('#advanced-search').find('#filter-queries .value select, #property-queries .value select, #resource-class .value select, #resource-templates .value select, #item-sets .value select, #datetime-queries .value select, select#site_id, select#owner_id')
            .addClass('chosen-select');
        $('#advanced-search select.chosen-select').chosen(chosenOptions);
        $('#advanced-search select.chosen-select option[value=""][selected]').prop('selected',  false).parent().trigger('chosen:updated');

        $(document).on('o:value-created', '#filter-queries .value, #resource-class .value, #resource-templates .value, #item-sets .value, #datetime-queries .value', function(e) {
            const newValue = $(this);
            newValue.find('select').chosen('destroy');
            newValue.find('.chosen-container').remove();
            newValue.find('select').addClass('chosen-select').chosen(chosenOptions);
        });
    }

    /**
     * Handle clearing fields on new filter multi-value.
     *
     * @see application/asset/js/advanced-search.js.
     */
    $(document).on('o:value-created', '#filter-queries .value', function(e) {
        // In advanced-search.js, "children" is used, but it is not possible here,
        // because a div is inserted to manage sub-query form.
        // Furthermore, there is a hidden input.
        const newValue = $(this);
        const isSidebar = newValue.parents('.sidebar').length > 0;
        if (isSidebar) {
            newValue.find('.query-type option[value="resq"]').remove();
            newValue.find('.query-type option[value="nresq"]').remove();
            newValue.find('.query-type option[value="lkq"]').remove();
            newValue.find('.query-type option[value="nlkq"]').remove();
            newValue.find('.query-form-element').remove();
        } else {
            newValue.find('.query-form-element').attr('data-query', '').hide();
            newValue.find('.query-form-element input[type="hidden"]').val(null);
            newValue.find('.query-form-element .search-filters').empty().html(Omeka.jsTranslate('[Edit below]'));
        }
        newValue.children().children('input[type="text"]').val(null);
        newValue.children().children('select').prop('selectedIndex', 0);
        newValue.children().children('.query-filter').find('option:selected').prop('selected', false);
        Omeka.handleQueryTextInput(newValue.find('.query-type'));
        if (hasChosenSelect) {
            if (isSidebar) {
                newValue.children().children('select.chosen-select').chosen(chosenOptionsSidebar);
            }
            newValue.children().children('select.chosen-select').trigger('chosen:updated');
        }

        $('#filter-queries').data('filter-search-index', $('#filter-queries').data('filter-search-index') + 1);

        newValue.find(':input').attr('name', function () {
            return this.name.replace(/\[\d\]/, '[' + $('#filter-queries').data('filter-search-index') + ']');
        });

    });

    /**
     * Copy of resource-selector for sidebar (not loaded in search form).
     *
     * @see application/asset/js/resource-selector.js
     */
    $(document).on('o:sidebar-content-loaded', '#query-sidebar-edit', function(e) {
        // Don't allow sub-sub-queries for now.
        const sidebar = $(this);
        sidebar.find('.query-type option[value="resq"]').remove();
        sidebar.find('.query-type option[value="nresq"]').remove();
        sidebar.find('.query-type option[value="kq"]').remove();
        sidebar.find('.query-type option[value="nlkq"]').remove();
        sidebar.find('.query-form-element').remove();
        if (hasChosenSelect) {
            sidebar.find('select.item-set-select-type, select#site_id, select#owner_id').addClass('chosen-select');
            sidebar.find('select.chosen-select').chosen(chosenOptionsSidebar);
            sidebar.find('select.chosen-select option[value=""][selected]').prop('selected',  false).parent().trigger('chosen:updated');
            sidebar.find('select.chosen-select').trigger('chosen:update');
        }
        Omeka.handleQueryTextInput(sidebar.find('.query-type'));
    });

    /**
     * Handle the query type for filters (extended types: resq, exs, dup*, etc.).
     * Replaces the core handler which only knows basic types (eq, in, ex, dt…).
     *
     * @see application/asset/js/advanced-search.js
     * @see application/asset/js/global.js
     * @see application/asset/js/query-form.js
     */
    $(document).off('change', '.query-type');
    $(document).on('change', '.query-type', function () {
         Omeka.handleQueryTextInput($(this));
    });

    // The core submit handler (Omeka.cleanSearchQuery) handles properties.
    // Append the module handler for filters and extras.
    $(document).on('submit', '#advanced-search', function(e) {
        Omeka.cleanFormSearchQuery($(this));
    });

    /**
     * Handle preparation of the advanced search form for filter part on load.
     */
    $('.query-type').each(function() {
        Omeka.handleQueryTextInput($(this));
    });

    /**
     * Init standard advanced search.
     *
     * @see application/view/common/advanced-search.phtml
     */

    // Index of filter search values.
    $('#filter-queries').data('filter-search-index', $('#filter-queries .value').length - 1);

    /**
     * Autosuggest on filter values using api of module Reference.
     *
     * Whitelist/blacklist filtering: a field gets autosuggest if it
     * is in the whitelist (or whitelist is "all") AND not in the
     * blacklist.
     */
    var $filterQueries = $('#filter-queries');
    var filterAutosuggestUrl = $filterQueries.data('autosuggest-url');
    if (filterAutosuggestUrl && typeof $.fn.autocomplete === 'function') {
        var autosuggestAll = $filterQueries.data('autosuggest-all') === 1
            || $filterQueries.data('autosuggest-all') === '1';
        var autosuggestWhitelist = $filterQueries.data('autosuggest-whitelist') || [];
        var autosuggestBlacklist = $filterQueries.data('autosuggest-blacklist') || [];

        var isFieldAllowed = function(field) {
            if (autosuggestBlacklist.indexOf(field) !== -1) {
                return false;
            }
            return autosuggestAll || autosuggestWhitelist.indexOf(field) !== -1;
        };

        var initFilterAutosuggest = function(value) {
            var input = value.find('input.query-text');
            if (!input.length) return;
            // Destroy previous instance.
            if (input.data('autocomplete')) {
                input.autocomplete('dispose');
            }
            // Get selected field(s) from the property select.
            var fieldSelect = value.find('.query-property');
            var fields = fieldSelect.val();
            if (!fields || (Array.isArray(fields) && !fields.length)) {
                return;
            }
            if (!Array.isArray(fields)) {
                fields = [fields];
            }
            // Filter by whitelist/blacklist.
            var allowed = [];
            $.each(fields, function(i, f) {
                if (isFieldAllowed(f)) allowed.push(f);
            });
            if (!allowed.length) return;
            // Build base URL with metadata fields.
            var params = [];
            $.each(allowed, function(i, f) {
                params.push('metadata[]=' + encodeURIComponent(f));
            });
            params.push('option[per_page]=25');
            var baseUrl = filterAutosuggestUrl + '?' + params.join('&');
            input.autocomplete({
                serviceUrl: baseUrl,
                dataType: 'json',
                paramName: 'option[filters][begin][]',
                minChars: 2,
                transformResult: function(response) {
                    var suggestions = [];
                    $.each(response, function(field, data) {
                        var refs = data['o:references'];
                        if (!Array.isArray(refs)) return;
                        $.each(refs, function(i, ref) {
                            if (ref.val) {
                                suggestions.push({
                                    value: ref.val,
                                    data: ref.total || 1,
                                });
                            }
                        });
                    });
                    return { suggestions: suggestions };
                },
            });
            input.attr('autocomplete', 'off');
        };

        // Init on field change.
        $(document).on('change', '#filter-queries .query-property', function() {
            initFilterAutosuggest($(this).closest('.value'));
        });

        // Init on new value created.
        $(document).on('o:value-created', '#filter-queries .value', function() {
            initFilterAutosuggest($(this));
        });

        // Init on page load for existing filters.
        $('#filter-queries .value').each(function() {
            initFilterAutosuggest($(this));
        });
    }

});
