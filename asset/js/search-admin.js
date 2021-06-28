$(document).ready(function() {

    /**
     * Add a button to automap the closest field name for the api mapping.
     */
    $('#metadata')
        .before('<button id="api_mapping_auto" title="Try to map automatically the metadata and the properties that are not mapped yet with the fields of the index">' + Omeka.jsTranslate('Automatic mapping of empty values') + '</button>')
    $('#api_mapping_auto').on('click', function(event) {
        event.preventDefault();
        $('#api_mapping_auto').html(Omeka.jsTranslate('Processing…'));
        $('#api_mapping_auto').prop('disabled', true);
        $('#metadata select:has(option[value=""]:selected), #properties select:has(option[value=""]:selected)').each(function() {
            var term = $(this).prop('name').replace('form[' + $(this).closest('fieldset').prop('id') + '][', '').replace(']', '');
            var normTerm = term.replace(':', '_') + '_';
            $(this).find('> option').each(function() {
                if (this.value.startsWith(normTerm)) {
                    $(this).prop('selected', true);
                    $(this).parent().trigger('chosen:updated');
                    // Map first index.
                    return false;
                }
            });
        });
        $('#api_mapping_auto').prop('disabled', false);
        $('#api_mapping_auto').html(Omeka.jsTranslate('Automatic mapping of empty values'));
    });

});
