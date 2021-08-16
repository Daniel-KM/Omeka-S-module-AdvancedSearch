$(document).ready( function() {

    console.log($().chosen);
    if (!$().chosen) {
        return;
    }
    console.log('ok');

    // See application/asset/js/chosen-options.js.
    var chosenOptions = chosenOptions || {
        allow_single_deselect: true,
        disable_advancedsearch_threshold: 10,
        width: '100%',
        include_group_label_in_selected: true,
    };

    $('.chosen-select').chosen(chosenOptions);

    $('#resource-templates .value select, #item-sets .value select, #site_id, #owner_id, #datetime-queries .value select').addClass('chosen-select').chosen(chosenOptions);
    $('#property-queries, #resource-class').on('o:value-created', '.value', function(e) {
        $('.chosen-select').chosen(chosenOptions);
    });
    $('#resource-templates, #item-sets').on('o:value-created', '.value', function(e) {
        $(this).find('select').addClass('chosen-select').chosen(chosenOptions);
    });

    /**
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
        $(this).find('select').addClass('chosen-select').chosen(chosenOptions);
    });

});
