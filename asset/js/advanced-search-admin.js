'use strict';

/**
 * Manage the simple search field in admin board.
 */
$(document).ready(function() {

    // A link to the advanced search, next to the submit, like the button of the
    // core for its own advanced options: no visible text, only a title and an
    // aria-label. Without javascript or dialog, it is a link to the search page.
    var advancedHtml = '';
    if (typeof searchAdvancedLink !== 'undefined' && searchAdvancedLink) {
        const advancedLabel = Omeka.jsTranslate('Advanced search');
        const advancedUrl = typeof searchAdvancedUrl !== 'undefined' ? searchAdvancedUrl : '';
        const formUrl = searchAdvancedLink === 'dialog' && typeof searchAdvancedFormUrl !== 'undefined'
            ? ` data-search-form-url="${searchAdvancedFormUrl}"`
            : '';
        advancedHtml = `<a id="search-advanced" class="advanced-search-link button" href="${advancedUrl}"`
            + `${formUrl} data-dialog-heading="${advancedLabel}"`
            + ` title="${advancedLabel}" aria-label="${advancedLabel}"></a>`;
    }

    var searchHtml = `
<form id="search-form-quick" class="${advancedHtml ? 'has-advanced' : ''}" method="GET" action="${typeof searchUrl !== 'undefined' ? searchUrl : ''}">
    <input id="search-q" name="q" placeholder="${Omeka.jsTranslate('Find resources…')}" value="" type="text" ${typeof searchAutosuggestUrl !== 'undefined' ? ' class="autosuggest" data-autosuggest-url="' + searchAutosuggestUrl + '"' + (typeof searchAutosuggestFillInput !== 'undefined' && searchAutosuggestFillInput ? ' data-autosuggest-fill-input="1"' : '') : ''}/>
    ${advancedHtml}<button type="submit">${Omeka.jsTranslate('Find')}</button>
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
