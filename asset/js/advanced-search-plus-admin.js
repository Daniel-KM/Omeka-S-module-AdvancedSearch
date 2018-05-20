// Adapted from Omeka application/asset/js/advanced-search.js.
$(document).ready( function() {
    var values = $('#datetime-queries .value');
    var index = values.length;

    // Add a value.
    $('#datetime-queries').on('o:value-created', '.value', function(e) {
        var value = $(this);
        value.children(':input').attr('name', function () {
            return this.name.replace(/\[\d\]/, '[' + index + ']');
        });
        index++;
    });
});
