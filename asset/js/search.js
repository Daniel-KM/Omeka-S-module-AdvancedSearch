'use strict';

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2024
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

const hasChosenSelect = typeof $.fn.chosen === 'function';

var Search = (function() {
    var self = {};

    self.setViewType = function(viewType) {
        // In some themes, the mode for resource list is set with a different class.
        var resourceLists = document.querySelectorAll('.search-results .resource-list, .search-results .resources-list-content');
        for (var i = 0; i < resourceLists.length; i++) {
            var resourceItem = resourceLists[i];
            resourceItem.className = resourceItem.className.replace(' grid', '').replace(' list', '')
                + ' ' + viewType;
        }
        localStorage.setItem('search_view_type', viewType);
    };

    /**
     * Check if the current query is an advanced search one.
     */
    self.isAdvancedSearchQuery = function () {
        let searchParams = new URLSearchParams(document.location.search);
        let  params = Array.from(searchParams.entries());
        for (let i in params) {
            let k = params[i][0];
            let v = params[i][1];
            if (v !== ''
                && !['q', 'search', 'fulltext_search', 'sort', 'sort_by', 'sort_order', 'page', 'per_page', 'limit', 'offset', 'csrf'].includes(k)
                && !k.startsWith('facet[')
                && !k.startsWith('facet%5B')
            ) {
                return true;
            }
        }
        return false;
    }

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
                    return {
                        value: dataItem.term,
                        data: dataItem.weight,
                    };
                }),
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

    const hasAutocomplete = typeof $.fn.autocomplete === 'function';

    /**
     * When the simple and the advanced form are the same form.
     */
    $('.advanced-search-form-toggle a').on('click', function(e) {
        e.preventDefault();
        $('.advanced-search-form, .advanced-search-form-toggle').toggleClass('open');
        if ($('.advanced-search-form').hasClass('open')) {
            $('.advanced-search-form-toggle a').text($('.advanced-search-form-toggle').data('msgOpen'));
        } else {
            $('.advanced-search-form-toggle a').text($('.advanced-search-form-toggle').data('msgClosed'));
        }
        // TODO Don't open autosuggestion when toggle.
        // $('#search-form [name=q]').focus();
    });

    /* Results tools (sort, pagination, per-page) */

    $('.as-url select, select.as-url').on('change', function(e) {
        const url = $(this).find('option:selected').data('url');
        if (url && url.length && window.location !== url) {
            window.location = url;
        };
    });

    /* Per-page selector links (depending if server or client build) */
    /* @deprecated Kept for old themes: use ".as-url" instead */
    $('.search-results-per-page:not(.as-url) select').on('change', function(e) {
        // Per-page fields don't look like a url.
        e.preventDefault();
        var perPage = $(this).val();
        if (perPage.substring(0, 6) === 'https:' || perPage.substring(0, 5) === 'http:') {
            window.location = perPage;
        } else if (perPage.substring(0, 1) === '/') {
            window.location = window.location.origin + perPage;
        } else {
            var searchParams = new URLSearchParams(window.location.search);
            searchParams.set('page', 1);
            searchParams.set('per_page', $(this).val());
            window.location.search = searchParams.toString();
        }
    });

    /* Sort selector links (depending if server or client build) */
    /* @deprecated Kept for old themes: use ".as-url" instead. */
    $('.search-sort:not(.as-url) select').on('change', function(e) {
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

    /* Facets. */

    $('.facets-active a').on('click', function(e) {
        // Reload with the link when there is no button to apply facets.
        if (!$('.facets-apply').length) {
            return true;
        }
        e.preventDefault();
        $(this).closest('li').hide();
        var facetName = $(this).data('facetName');
        var facetValue = $(this).data('facetValue');
        $('.facet-item input:checked').each(function() {
            if ($(this).prop('name') === facetName
                && $(this).prop('value') === String(facetValue)
            ) {
                $(this).prop('checked', false);
            }
        });
        $('select.facet-items option:selected').each(function() {
            if ($(this).closest('select').prop('name') === facetName
                && $(this).prop('value') === String(facetValue)
            ) {
                $(this).prop('selected', false);
                if (hasChosenSelect) {
                    $(this).closest('select').trigger('chosen:updated');
                }
            }
        });
        var facetRange = $(`input[type=range][name="${facetName}"]`);
        if (facetRange.length) {
            if (facetName.includes('[to]')) {
                facetRange.val(facetRange.attr('max'));
                controlSliderTo(facetRange[0]);
            } else {
                facetRange.val(facetRange.attr('min'));
                controlSliderFrom(facetRange[0]);
            }
        }
    });

    $('.facets').on('change', 'input[type=checkbox]', function() {
        if (!$('.facets-apply').length && $(this).data('url')) {
            window.location = $(this).data('url');
        }
    });

    $('.facets').on('change', 'select', function() {
        if (!$('#facets-apply').length) {
            // Replace the current select args by new ones.
            // Names in facets may have no index in array ("[]") when it is a multiple one.
            // But the select may be a single select too, in which case the url is already in data.
            let url;
            let selectValues = $(this).val();
            if (typeof selectValues !== 'object') {
                let option =  $(this).find('option:selected');
                if (option.length && option[0].value !== '') {
                    url = option.data('url');
                    if (url && url.length) {
                        window.location = url;
                    }
                } else {
                    url = new URL(window.location.href);
                    url.searchParams.delete($(this).prop('name'));
                    window.location = url.toString();
                }
                return;
            }
            // Prepare the url with the selected values.
            url = new URL(window.location.href);
            let selectName = $(this).prop('name');
            url.searchParams.delete(selectName);
            selectValues.forEach((element, index) => {
                url.searchParams.set(selectName.substring(0, selectName.length - 2) + '[' + index + ']', element);
            });
            window.location = url.toString();
        }
    });

    function facetExpandOrCollapse(button) {
        button = $(button);
        if (button.hasClass('expand')) {
            button.attr('aria-label', button.attr('data-label-expand') ? button.attr('data-label-expand') : Omeka.jsTranslate('Expand'));
            button.closest('.facet').find('.facet-elements').attr('hidden', 'hidden');
        } else {
            button.attr('aria-label', button.attr('data-label-expand') ? button.attr('data-label-collapse') : Omeka.jsTranslate('Collapse'));
            button.closest('.facet').find('.facet-elements').removeAttr('hidden');
        }
    }

    function facetSeeMoreOrLess(button) {
        button = $(button);
        if (button.hasClass('expand')) {
            button.text(button.attr('data-label-see-more') ? button.attr('data-label-see-more') : Omeka.jsTranslate('See more'));
            const defaultCount = Number(button.attr('data-default-count')) + 1;
            button.closest('.facet').find('.facet-items .facet-item:nth-child(n+' + defaultCount + ')').attr('hidden', 'hidden');
        } else {
            button.text(button.attr('data-label-see-less') ? button.attr('data-label-see-less') : Omeka.jsTranslate('See less'));
            button.closest('.facet').find('.facet-items .facet-item').removeAttr('hidden');
        }
    }

    $('.facets').on('click', '.facet-button, .facet-see-more-or-less', function() {
        const button = $(this);
        if (button.hasClass('expand')) {
            $(this).removeClass('expand').addClass('collapse');
        } else {
            $(this).removeClass('collapse').addClass('expand');
        }
        if (button.hasClass('facet-see-more-or-less')) {
            facetSeeMoreOrLess(button);
        } else {
            facetExpandOrCollapse(button);
        }
    });

    $('.facet-see-more-or-less').each((index, button) => facetSeeMoreOrLess(button));

    /**
     * Search range double / sliders
     *
     * @see https://medium.com/@predragdavidovic10/native-dual-range-slider-html-css-javascript-91e778134816
     */

    function getRangeDoubleElements(element) {
        const rangeDouble = element.closest('.range-double');
        return rangeDouble
            ? [
                rangeDouble.querySelector('.range-numeric-from'),
                rangeDouble.querySelector('.range-numeric-to'),
                rangeDouble.querySelector('.range-slider-from'),
                rangeDouble.querySelector('.range-slider-to'),
            ]
            : [null, null, null, null];
    }

    function controlNumericFrom(element) {
        const [inputFrom, inputTo, sliderFrom, sliderTo] = getRangeDoubleElements(element);
        const [from, to] = parseTwoElementsToInt(inputFrom, inputTo);
        [inputFrom.value, sliderFrom.value] = from > to ? [to, to] : [from, from];
        fillSlider(inputFrom, inputTo, sliderTo);
    }

    function controlNumericTo(element) {
        const [inputFrom, inputTo, sliderFrom, sliderTo] = getRangeDoubleElements(element);
        const [from, to] = parseTwoElementsToInt(inputFrom, inputTo);
        [inputTo.value, sliderTo.value] = from <= to ? [to, to] : [from, from];
        fillSlider(inputFrom, inputTo, sliderTo);
        toggleRangeSliderAccessible(sliderTo);
    }

    function controlSliderFrom(element) {
        const [inputFrom, inputTo, sliderFrom, sliderTo] = getRangeDoubleElements(element);
        const [from, to] = parseTwoElementsToInt(sliderFrom, sliderTo);
        [inputFrom.value, sliderFrom.value] = from > to ? [to, to] : [from, from];
        fillSlider(sliderFrom, sliderTo, sliderTo);
    }

    function controlSliderTo(element) {
        const [inputFrom, inputTo, sliderFrom, sliderTo] = getRangeDoubleElements(element);
        const [from, to] = parseTwoElementsToInt(sliderFrom, sliderTo);
        [inputTo.value, sliderTo.value] = from <= to ? [to, to] : [from, from];
        fillSlider(sliderFrom, sliderTo, sliderTo);
        toggleRangeSliderAccessible(sliderTo);
    }

    function fillSlider(from, to, controlSlider, colorSlider, colorRange) {
        const rangeDistance = to.max - to.min;
        const fromPosition = from.value - to.min;
        const toPosition = to.value - to.min;
        colorSlider = colorSlider ? colorSlider : colorSliderDefault;
        colorRange = colorRange ? colorRange : colorRangeDefault;
        controlSlider.style.background = `linear-gradient(
            to right,
            ${colorSlider} 0%,
            ${colorSlider} ${fromPosition / rangeDistance * 100}%,
            ${colorRange} ${fromPosition / rangeDistance * 100}%,
            ${colorRange} ${toPosition / rangeDistance * 100}%,
            ${colorSlider} ${toPosition / rangeDistance * 100}%,
            ${colorSlider} 100%)`;
    }

    function toggleRangeSliderAccessible(sliderCurrent) {
        const [inputFrom, inputTo, sliderFrom, sliderTo] = getRangeDoubleElements(sliderCurrent);
        sliderTo.style.zIndex = (Number(sliderFrom.value) === Number(sliderTo.value))
            || (Number(sliderTo.value) <= 0)
            ? 2
            : 0;
    }

    function parseTwoElementsToInt(currentFrom, currentTo) {
        const from = parseInt(currentFrom.value, 10);
        const to = parseInt(currentTo.value, 10);
        return [from, to];
    }

    const rangeDoubles = document.querySelectorAll('.range-double');

    /* TODO Get slider colors from the css. */
    const colorRangeDefault = '#a7a7a7';
    const colorSliderDefault = '#e9e9ed';

    /* Init ranges only when present. */
    if (rangeDoubles.length) {
        rangeDoubles.forEach((rangeDouble) => {
            const rangeSliderFrom = rangeDouble.querySelector('.range-slider-from');
            const rangeSliderTo = rangeDouble.querySelector('.range-slider-to');
            if (rangeSliderFrom && rangeSliderTo) {
                fillSlider(rangeSliderFrom, rangeSliderTo, rangeSliderTo);
                toggleRangeSliderAccessible(rangeSliderTo);
            }
        });

        $('.range-numeric-from').on('input', (event) => controlNumericFrom(event.target));
        $('.range-numeric-to').on('input', (event) => controlNumericTo(event.target));
        $('.range-slider-from').on('input', (event) => controlSliderFrom(event.target));
        $('.range-slider-to').on('input', (event) => controlSliderTo(event.target));
    }

    /* Results */

    $('.search-view-type-list').on('click', function(e) {
        e.preventDefault();
        Search.setViewType('list');
        $('.search-view-type').removeClass('active');
        $('.search-view-type-list').addClass('active');
    });

    $('.search-view-type-grid').on('click', function(e) {
        e.preventDefault();
        Search.setViewType('grid');
        $('.search-view-type').removeClass('active');
        $('.search-view-type-grid').addClass('active');
    });

    /**********
     * Initialisation.
     */

    /**
     * Open advanced search when it is used according to the query.
     *
     * @todo Check if we are on the advanced search page first.
     * @todo Use focus on load, but don't open autosuggestion on focus.
     */
    if (Search.isAdvancedSearchQuery()) {
        $('.advanced-search-form-toggle a').click();
    }

    /**
     * Init display of results list or grid.
     */
    var view_type = localStorage.getItem('search_view_type');
    if (!view_type) {
        view_type = 'list';
    }
    $('.search-view-type-' + view_type).click();

    /**
     * Init chosen select.
     */
    if (hasChosenSelect) {
        $('.chosen-select').chosen(Search.chosenOptions);
    }

    /**
     * Init autocompletion/autosuggestion of all specified input fields.
     */
    if (hasAutocomplete) {
        let searchElement = $('.form-search .autosuggest[name=q]');
        if (searchElement) {
            let autosuggestOptions = Search.autosuggestOptions(searchElement);
            if (autosuggestOptions) {
                searchElement.autocomplete(autosuggestOptions);
            }
        }
    }

});
