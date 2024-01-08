'use strict';

/**
 * Manage the simple search field in admin board.
 */
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

});
