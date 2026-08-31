'use strict';

/**
 * Manage the simple search field in admin board.
 */
$(document).ready(function() {

    var searchHtml = `
<form id="search-form-quick" method="GET" action="${typeof searchUrl !== 'undefined' ? searchUrl : ''}">
    <input id="search-q" name="q" placeholder="${Omeka.jsTranslate('Find resources…')}" value="" type="text" ${typeof searchAutosuggestUrl !== 'undefined' ? ' class="autosuggest" data-autosuggest-url="' + searchAutosuggestUrl + '"' + (typeof searchAutosuggestFillInput !== 'undefined' && searchAutosuggestFillInput ? ' data-autosuggest-fill-input="1"' : '') : ''}/>
    <button type="submit">${Omeka.jsTranslate('Find')}</button>
</form>`;

    // The search page is added below the quick search of Omeka, or it replaces
    // it, keeping the container and its styles.
    if (typeof searchReplaceQuick !== 'undefined' && searchReplaceQuick) {
        $('#search').html(searchHtml);
    } else {
        $('#search').after(searchHtml);
    }

    if (typeof $.fn.autocomplete === 'function' && typeof searchAutosuggestUrl !== 'undefined') {
        $('#search-q').autocomplete(Search.autosuggestOptions($('#search-q')));
    }

});
