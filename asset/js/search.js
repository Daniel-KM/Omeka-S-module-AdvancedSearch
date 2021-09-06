/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2021
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

var Search = (function() {
    var self = {};

    self.setViewType = function(viewType) {
        var resourceLists = document.querySelectorAll('.search-results .resource-list');
        for (var i = 0; i < resourceLists.length; i++) {
            var resourceItem = resourceLists[i];
            resourceItem.className = 'resource-list ' + viewType;
        }
        localStorage.setItem('search_view_type', viewType);
    };

    /**
     * Chosen default options.
     * @see https://harvesthq.github.io/chosen/
     */
    self.chosenOptions = {
        allow_single_deselect: true,
        disable_search_threshold: 10,
        width: '100%',
        include_group_label_in_selected: true,
    };

    var transformResult = function(response) {
        // Managed by Solr endpoint.
        // @see https://solr.apache.org/guide/suggester.html#example-usages
        if (response.suggest) {
            const answer = response.suggest[Object.keys(response.suggest)[0]];
            const searchString = answer[Object.keys(answer)[0]];
            return {
                query: searchString,
                suggestions: $.map(answer[searchString].suggestions, function(dataItem) {
                    return { value: dataItem.term, data: dataItem.weight };
                })
            };
        }
        // Managed by module or try the format of jQuery-Autocomplete.
        return response.data ? response.data : response;
    }

    /**
     * @see https://github.com/devbridge/jQuery-Autocomplete
     */
    self.autosuggestOptions = function (searchElement) {
        let serviceUrl = searchElement.data('autosuggest-url');
        if (!serviceUrl || !serviceUrl.length) {
            return null;
        }
        let paramName = searchElement.data('autosuggest-param-name');
        paramName = paramName && paramName.length ? paramName : 'q'
        return {
            serviceUrl: serviceUrl,
            dataType: 'json',
            paramName: paramName,
            transformResult: transformResult,
            onSearchError: function(query, jqXHR, textStatus, errorThrown) {
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    console.log(jqXHR.responseJSON.message);
                } else if (errorThrown.length) {
                    console.log(errorThrown)
                }
            },
        };
    };

    return self;
})();

$(document).ready(function() {

    /* Sort selector links (depending if server of client build) */
    $('.search-sort select').on('change', function(e) {
        // Sort fields don't look like a url.
        e.preventDefault();
        var sort = $(this).val();
        if (sort.substring(0, 6) === 'https:' || sort.substring(0, 5) === 'http:') {
            window.location = sort;
        } else if (sort.substring(0, 1) === '/') {
            window.location = window.location.origin + sort;
        } else {
            var searchParams = new URLSearchParams(window.location.search);
            searchParams.set('sort', $(this).val());
            window.location.search = searchParams.toString();
        }
    });

    $('.search-facets-active a').on('click', function(e) {
        // Reload with the link when there is no button to apply facets.
        if (!$('.apply-facets').length) {
            return true;
        }
        e.preventDefault();
        $(this).closest('li').hide();
        var facetName = $(this).data('facetName');
        var facetValue = $(this).data('facetValue');
        $('.search-facet-item input:checked').each(function() {
            if ($(this).prop('name') === facetName
                && $(this).prop('value') === String(facetValue)
            ) {
                $(this).prop('checked', false);
            }
        });
        $('select.search-facet-items option:selected').each(function() {
            if ($(this).closest('select').prop('name') === facetName
                && $(this).prop('value') === String(facetValue)
            ) {
                $(this).prop('selected', false);
                if ($.isFunction($.fn.chosen)) {
                    $(this).closest('select').trigger('chosen:updated');
                }
            }
        });
    });

    $('.search-facets').on('change', 'input[type=checkbox]', function() {
        if (!$('.apply-facets').length) {
            window.location = $(this).data('url');
        }
    });

    $('.search-facets').on('change', 'select', function() {
        if (!$('.apply-facets').length) {
            // Replace the current select args by new ones.
            let searchParams = new URLSearchParams(document.location.search);
            let selectName = $(this).prop('name');
            searchParams.delete(selectName);
            $(this).val().forEach((element, index) => {
                searchParams.set(selectName.substring(0, selectName.length - 2) + '[' + index + ']', element);
            });
            window.location = window.location.href + searchParams.toString();
        }
    });

    $('.search-view-type-list').on('click', function(e) {
        e.preventDefault();
        Search.setViewType('list');
        $('.search-view-type').removeClass('active');
        $(this).addClass('active');
    });
    $('.search-view-type-grid').on('click', function(e) {
        e.preventDefault();
        Search.setViewType('grid');
        $('.search-view-type').removeClass('active');
        $(this).addClass('active');
    });

    var view_type = localStorage.getItem('search_view_type');
    if (!view_type) {
        view_type = 'list';
    }
    $('.search-view-type-' + view_type).click();

    if ($.isFunction($.fn.autocomplete)) {
        let searchElement = $('.form-search .autosuggest[name=q]');
        let autosuggestOptions = Search.autosuggestOptions(searchElement);
        if (autosuggestOptions) searchElement.autocomplete(autosuggestOptions);
    }

    if ($.isFunction($.fn.chosen)) {
        $('.chosen-select').chosen(Search.chosenOptions);
    }

});
