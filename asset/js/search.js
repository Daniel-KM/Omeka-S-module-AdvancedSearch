/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2020
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

    self.objectFromQueryString = function(str) {
        var params = {};
        str
            .replace(/(^\?)/, '')
            .split("&")
            .filter(function(element) { return element !== '' })
            .forEach(function(n) {
                n = n.split('=');
                var name = decodeURIComponent(n[0]);
                if (!params.hasOwnProperty(name)) {
                    params[name] = decodeURIComponent(n[1]);
                } else {
                    if (!Array.isArray(params[name])) {
                        params[name] = [params[name]];
                    }
                    params[name].push(decodeURIComponent(n[1]));
                }
            });

        return params;
    };

    self.queryStringFromObject = function(obj) {
        return Object.keys(obj).map(function(name) {
            if (Array.isArray(obj[name])) {
                return obj[name].map(function(value) {
                    return name + '=' + value;
                }).join('&');
            } else {
                return name + '=' + obj[name];
            }
        }).join('&');
    };

    self.sortBy = function(sort) {
        var params = Search.objectFromQueryString(document.location.search);
        params['sort'] = sort;
        window.location.search = '?' + Search.queryStringFromObject(params);
    };

    self.facetFor = function(url) {
        window.location = url;
    };

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

    $('.search-results-sort select').on('change', function() {
        Search.sortBy($(this).val());
    });

    $('.search-facets').on('change', 'input[type=checkbox]', function() {
        Search.facetFor($(this).data('url'));
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

    /* Sort selector links */
    $('.sort-by-order-urls').on('change', function(e) {
        e.preventDefault();
        window.location.href = $(this).val();
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
