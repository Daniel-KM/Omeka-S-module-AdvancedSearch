$(document).ready(function() {

    var searchHtml = `
<form id="search-form-quick" method="GET" action="${typeof searchUrl !== 'undefined' ? searchUrl : ''}">
    <input id="search-q" name="q" placeholder="${Omeka.jsTranslate('Find resources…')}" value="" type="text" ${typeof searchAutosuggestUrl !== 'undefined' ? ' class="autosuggest" data-autosuggest-url="' + searchAutosuggestUrl + '"' : ''}/>
    <button type="submit">${Omeka.jsTranslate('Find')}</button>
</form>`;
    $('#search').after(searchHtml);

    if (typeof $.fn.autocomplete === 'function' && typeof searchAutosuggestUrl !== 'undefined') {
        $('#search-q').autocomplete(Search.autosuggestOptions($('#search-q')));
    }

    /**
     * Add chosen options to standard advanced search form when available.
     */
    if (!$().chosen) {
        return;
    }

    /**
     * @see application/asset/js/chosen-options.js
     */
    var chosenOptions = chosenOptions || {
        allow_single_deselect: true,
        disable_search_threshold: 10,
        width: '100%',
        include_group_label_in_selected: true,
    };
    $('.chosen-select').chosen(chosenOptions);

    $('body.search').find('#resource-templates .value select, #item-sets .value select, #site_id, #owner_id, #datetime-queries .value select')
        .addClass('chosen-select').chosen(chosenOptions);
    $('#property-queries, #resource-class').on('o:value-created', '.value', function(e) {
        $('.chosen-select').chosen(chosenOptions);
    });
    $('#resource-templates, #item-sets').on('o:value-created', '.value', function(e) {
        $(this).find('select').addClass('chosen-select').chosen(chosenOptions);
    });
    $('#datetime-queries').on('o:value-created', '.value', function(e) {
        $(this).find('select').addClass('chosen-select').chosen(chosenOptions);
    });

});
