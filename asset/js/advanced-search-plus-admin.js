$(document).ready( function() {

    /**
     * Advanced search
     *
     * Adapted from Omeka application/asset/js/advanced-search.js.
     */
    var values = $('#datetime-queries .value');
    var index = values.length;
    $('#datetime-queries').on('o:value-created', '.value', function(e) {
        var value = $(this);
        value.children(':input').attr('name', function () {
            return this.name.replace(/\[\d\]/, '[' + index + ']');
        });
        index++;
    });

});
