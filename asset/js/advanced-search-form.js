/**
 * Manage the improvements of the standard advanced search.
 */

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
     * @see application/asset/js/global.js
     */
    function disableQueryTextInput(queryType) {
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

    $(document).on('change', '.query-type', function () {
         disableQueryTextInput($(this));
    });

    // Updating querying should be done on load too.
    $('#property-queries .query-type').each(function() {
         disableQueryTextInput($(this));
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
        disableQueryTextInput(newValue.find('.query-type'));
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
        disableQueryTextInput(sidebar.find('.query-type'));
    });

});
