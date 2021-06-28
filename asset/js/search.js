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
        var resourceLists = document.querySelectorAll('div.resource-list');
        for (var i = 0; i < resourceLists.length; i++) {
            var resourceItem = resourceLists[i];
            resourceItem.className = 'resource-list ' + viewType;
        }
        localStorage.setItem('search_view_type', viewType);
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
        $('.search-facet-item input').each(function() {
            if ($(this).prop('name') === facetName
                && $(this).prop('value') === String(facetValue)
            ) {
                $(this).prop('checked', false);
            }
        });
    });

    $('.search-facets').on('change', 'input[type=checkbox]', function() {
        if (!$('.apply-facets').length) {
            window.location = $(this).data('url');
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

    if ($.isFunction($.fn.chosen)) {
        /**
         * Chosen default options.
         * @see https://harvesthq.github.io/chosen/
         */
        var chosenOptions = {
            allow_single_deselect: true,
            disable_search_threshold: 10,
            width: '100%',
            include_group_label_in_selected: true,
        };
        $('.chosen-select').chosen(chosenOptions);
    }

});
