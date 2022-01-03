$(document).ready(function() {

    // Disable query text according to some query types without values.
    // See global.js.
    function disableQueryTextInput(queryType) {
        var queryText = queryType.siblings('.query-text');
        queryText.prop('disabled',
            ['ex', 'nex', 'lex', 'nlex'].indexOf(queryType.val()) !== -1);
    };
    $(document).on('change', '.query-type', function () {
         disableQueryTextInput($(this));
    });
    // Updating querying should be done on load too.
    $('#property-queries .query-type').each(function() {
         disableQueryTextInput($(this));
    });

});
