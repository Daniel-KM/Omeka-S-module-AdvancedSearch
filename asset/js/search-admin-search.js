$(document).ready(function() {

    var searchHtml = `
<form id="search-form-quick" method="GET" action="${searchUrl}">
    <input id="search-q" name="q" placeholder="${Omeka.jsTranslate('Find resources…')}" value="" type="text" ${typeof searchAutosuggestUrl !== 'undefined' ? ' class="autosuggest" data-autosuggest-url="' + searchAutosuggestUrl + '"' : ''}/>
    <button type="submit">${Omeka.jsTranslate('Find')}</button>
</form>`;
    $('#search').after(searchHtml);

    if ($.isFunction($.fn.autocomplete) && typeof searchAutosuggestUrl !== 'undefined') {
        $('#search-q').autocomplete(Search.autosuggestOptions($('#search-q')));
    }

});
