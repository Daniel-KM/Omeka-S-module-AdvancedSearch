'use strict';

/**
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

if (typeof hasChosenSelect === 'undefined') {
    var hasChosenSelect = typeof $.fn.chosen === 'function';
}
if (typeof hasOmekaTranslate === 'undefined') {
    var hasOmekaTranslate = typeof Omeka !== 'undefined' && typeof Omeka.jsTranslate === 'function';
}

const $searchFiltersAdvanced = $('#search-filters');
const $searchFacets = $('#search-facets');

/**
 * Manage search form, search filters, search results, search facets.
 */

var Search = (function() {

    var self = {};

    /**
     * Data about search filter types.
     *
     * @see \AdvancedSearch\Stdlib\SearchResources
     */
    self.filterTypes = {
        withValue: [
            'eq',
            'neq',
            'in',
            'nin',
            'ma',
            'nma',
            'res',
            'nres',
            'resq',
            'nresq',
            'list',
            'nlist',
            'sw',
            'nsw',
            'ew',
            'new',
            'near',
            'nnear',
            'lres',
            'nlres',
            'lkq',
            'nlkq',
            'dtp',
            'ndtp',
            'tp',
            'ntp',
            'lt',
            'lte',
            'gte',
            'gt',
            '<',
            '≤',
            '≥',
            '>',
            'yreq',
            'nyreq',
            'yrlt',
            'yrlte',
            'yrgte',
            'yrgt',
        ],
        withoutValue: [
            'ex',
            'nex',
            'exs',
            'nexs',
            'exm',
            'nexm',
            'resq',
            'nresq',
            'lex',
            'nlex',
            'lkq',
            'nlkq',
            'dtp',
            'ndtp',
            'tp',
            'ntp',
            'tpl',
            'ntpl',
            'tpr',
            'ntpr',
            'tpu',
            'ntpu',
            'dup',
            'ndup',
            'dupl',
            'ndupl',
            'dupt',
            'ndupt',
            'duptl',
            'nduptl',
            'dupv',
            'ndupv',
            'dupvl',
            'ndupvl',
            'dupvt',
            'ndupvt',
            'dupvtl',
            'ndupvtl',
            'dupr',
            'ndupr',
            'duprl',
            'nduprl',
            'duprt',
            'nduprt',
            'duprtl',
            'nduprtl',
            'dupu',
            'ndupu',
            'dupul',
            'ndupul',
            'duput',
            'nduput',
            'duputl',
            'nduputl',
        ],
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

    /**
     * Check if the current query is an advanced search one.
     */
    self.isAdvancedSearchQuery = function() {
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
     * Autocomplete or autosuggest for any input element.
     *
     * @see https://github.com/devbridge/jQuery-Autocomplete
     */
    self.autosuggestOptions = function(element) {
        let serviceUrl = element.data('autosuggest-url');
        if (!serviceUrl || !serviceUrl.length) {
            return null;
        }

        let transformResult = function(response) {
            // Managed by Solr endpoint.
            // @see https://solr.apache.org/guide/suggester.html#example-usages
            if (response.suggest) {
                const answer = response.suggest[Object.keys(response.suggest)[0]];
                const searchString = answer[Object.keys(answer)[0]] instanceof String
                    ? answer[Object.keys(answer)[0]]
                    : Object.keys(answer)[0];
                if (!Object.keys(answer[searchString]).find((key) => 'suggestions' === key)) {
                    return {};
                }
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
            // Managed by module, that uses the format of jQuery-Autocomplete
            // in data, or jQuery-Autocomplete directly.
            return response.data ? response.data : response;
        };

        // Get the param name (always "q" for the internal suggester).
        let paramName = element.data('autosuggest-param-name');
        paramName = paramName && paramName.length ? paramName : 'q';
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
            onSelect: function (suggestion) {
                if (!$(this).data('autosuggest-fill-input')) {
                    $(this).closest('form').submit();
                }
            },
        };
    };

    /**
     * Advanced filters.
     */

    self.filtersAdvanced = (function() {
        var self = {};

        // At least one default.
        self.countDefault = $searchFiltersAdvanced.data('count-default') ? $searchFiltersAdvanced.data('count-default') : 0;

        self.countMax = $searchFiltersAdvanced.data('count-max') ? $searchFiltersAdvanced.data('count-max') : 0;

        self.countAll = function() {
            return $searchFiltersAdvanced.find('> fieldset.filter').length;
        };

        self.countFilled = function() {
            var countFilled = 0;
            $searchFiltersAdvanced.find('> fieldset.filter').each(function(index, filterFieldset) {
                // The fields may be select or radio or checkboxes.
                const field = $(filterFieldset).find('[name^="filter["][name$="][field]"]');
                const type = $(filterFieldset).find('[name^="filter["][name$="][type]"]');
                const value = $(filterFieldset).find('[name^="filter["][name$="][value]"]');
                // const matchField = field.prop('name').match(/filter\[(\d+)\]\[field\]/);
                // const matchType = type.prop('name').match(/filter\[(\d+)\]\[type\]/);
                // const matchValue = value.prop('name').match(/filter\[(\d+)\]\[value\]/);
                if (field.length
                    && type.length
                    && (Search.filterTypes.withoutValue.includes(type.val()) ? true : (value.length && value.val()))
                ) {
                    ++countFilled;
                }
            });
            return countFilled;
        };

        self.init = function() {
            // Warning: nth-child() counts all childs, even not fieldsets, so use nth-of-type().
            // Remove unmanaged fieldset.
            if (self.countMax) {
                $searchFiltersAdvanced.find('> fieldset.filter:nth-of-type(n+' + (self.countMax + 1) + ')').remove();
            }
            // Display or remove fieldsets.
            const countFilled = self.countFilled();
            const countNeeded = Math.max(self.countDefault, countFilled);
            $searchFiltersAdvanced.find('> fieldset.filter:nth-of-type(-n+' + (countNeeded + 1) + ')').removeAttr('hidden');
            $searchFiltersAdvanced.find('> fieldset.filter:nth-of-type(n+' + (countNeeded + 1) + ')').remove();
            // Create missing fieldsets.
            self.appendFilter(self.countDefault - self.countAll());
            self.updatePlus();
            return self;
        };

        self.appendFilter = function(countAppend = 1) {
            countAppend = self.countMax ? Math.min(countAppend, self.countMax - self.countAll()) : countAppend;
            if (countAppend <= 0) {
                return self;
            }
            const fieldsetTemplate = $searchFiltersAdvanced.find('span[data-template]').attr('data-template');
            let maxIndex = 0;
            $searchFiltersAdvanced.find('> fieldset.filter').each(function(no, item) {
                const fieldsetName = $(item).attr('name');
                const fieldsetIndex = fieldsetName.replace(/\D+/g, '');
                maxIndex = Math.max(maxIndex, fieldsetIndex);
            });
            for (var i = 0; i < countAppend; i++) {
                $searchFiltersAdvanced.append(fieldsetTemplate.split('__index__').join(++maxIndex));
            }
            $searchFiltersAdvanced.trigger('o:advanced-search.filter.append');
            return self;
        };

        self.removeFilter = function(filter) {
            $(filter).remove();
            $searchFiltersAdvanced.trigger('o:advanced-search.filter.remove');
            return self;
        };

        self.updatePlus = function() {
            const buttonPlus = $searchFiltersAdvanced.find('.search-filter-plus');
            if (self.countMax && self.countAll() >= self.countMax) {
                buttonPlus.hide();
            } else {
                buttonPlus.show();
            }
            return self;
        };

        return self;
    })();

    /* Results */

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

    /* Facets. */

    self.facets = (function() {
        var self = {};

        /**
         * Modes may be "click a button to apply facets" or "reload the page directly".
         */
        self.useApplyFacets = $searchFacets.find('.apply-facets').length > 0;

        self.expandOrCollapse = function(button) {
            button = $(button);
            if (button.hasClass('expand')) {
                button.attr('aria-label', button.attr('data-label-expand') ? button.attr('data-label-expand') : (hasOmekaTranslate ? Omeka.jsTranslate('Expand') : 'Expand'));
                button.closest('.facet').find('.facet-elements').attr('hidden', 'hidden');
            } else {
                button.attr('aria-label', button.attr('data-label-collapse') ? button.attr('data-label-collapse') : (hasOmekaTranslate ? Omeka.jsTranslate('Collapse') : 'Collapse'));
                button.closest('.facet').find('.facet-elements').removeAttr('hidden');
            }
            $searchFacets.trigger('o:advanced-search.facet.expand-or-collapse');
            return self;
        };

        self.seeMoreOrLess = function(button) {
            button = $(button);
            if (button.hasClass('expand')) {
                button.text(button.attr('data-label-see-more') ? button.attr('data-label-see-more') : (hasOmekaTranslate ? Omeka.jsTranslate('See more') : 'See more'));
                const defaultCount = Number(button.attr('data-default-count')) + 1;
                button.closest('.facet').find('.facet-items .facet-item:nth-child(n+' + defaultCount + ')').attr('hidden', 'hidden');
            } else {
                button.text(button.attr('data-label-see-less') ? button.attr('data-label-see-less') : (hasOmekaTranslate ? Omeka.jsTranslate('See less') : 'See less'));
                button.closest('.facet').find('.facet-items .facet-item').removeAttr('hidden');
            }
            $searchFacets.trigger('o:advanced-search.facet.see-more-or-less');
            return self;
        };

        self.directLinkCheckbox = function (facetItem) {
            if (!self.useApplyFacets) {
                facetItem = facetItem ? $(facetItem) : $(this);
                if (facetItem.data('url')) {
                    window.location = facetItem.data('url');
                }
            }
            return self;
        };

        self.directLinkSelect = function (facet) {
            if (self.useApplyFacets) {
                return self;
            }
            // Replace the current select args by new ones.
            // Names in facets may have no index in array ("[]") when it is a
            // multiple one. But the select may be a single select too, in which
            // case the url is already in data.
            facet = facet ? $(facet) : $(this);
            let url;
            let selectValues = facet.val();
            if (typeof selectValues !== 'object') {
                let option =  facet.find('option:selected');
                if (option.length && option[0].value !== '') {
                    url = option.data('url');
                    if (url && url.length) {
                        window.location = url;
                    }
                } else {
                    url = new URL(window.location.href);
                    url.searchParams.delete(facet.prop('name'));
                    window.location = url.toString();
                }
            } else {
                // Prepare the url with the selected values.
                url = new URL(window.location.href);
                let selectName = facet.prop('name');
                url.searchParams.delete(selectName);
                selectValues.forEach((element, index) => {
                    url.searchParams.set(selectName.substring(0, selectName.length - 2) + '[' + index + ']', element);
                });
                window.location = url.toString();
            }
            return self;
        };

        /**
         * Active facets are always a link, but the link may be skipped when
         * the mode is to use the apply button.
         */
        self.removeActiveFacet = function (activeFacet) {
            // Reload with the link when there is no button to apply facets.
            if (!Search.facets.useApplyFacets) {
                return true;
            }
            e.preventDefault();
            activeFacet = activeFacet ? $(activeFacet) : $(this);
            activeFacet.closest('li').hide();
            var facetName = activeFacet.data('facetName');
            var facetValue = activeFacet.data('facetValue');
            $searchFacets.find('.facet-item input:checked').each(function() {
                if ($(this).prop('name') === facetName
                    && $(this).prop('value') === String(facetValue)
                ) {
                    $(this).prop('checked', false);
                }
            });
            $searchFacets.find('select.facet-items option:selected').each(function() {
                if ($(this).closest('select').prop('name') === facetName
                    && $(this).prop('value') === String(facetValue)
                ) {
                    $(this).prop('selected', false);
                    if (hasChosenSelect) {
                        $(this).closest('select').trigger('chosen:updated');
                    }
                }
            });
            // Manage the special case where the active facet is a range.
            var facetRange = $searchFacets.find(`input[type=range][name="${facetName}"]`);
            if (facetRange.length) {
                if (facetName.includes('[to]')) {
                    facetRange.val(facetRange.attr('max'));
                    Search.rangeSliderDouble.controlSliderTo(facetRange[0]);
                } else {
                    facetRange.val(facetRange.attr('min'));
                    Search.rangeSliderDouble.controlSliderFrom(facetRange[0]);
                }
            }
            return self;
        };

        return self;
    })();

    /* Forms tools. */

    /**
     * Clear the form with hidden and min/max management.
     *
     * The html button "reset" resets to default values, not empty values.
     * Furthermore, the min/max of ranges and numbers should be managed.
     * For select and radio, set the default or the first value.
     * Keep hidden values.
     */
    self.smartClearForm = function(form) {
        // TODO Function reset() does not work on search form, only on facets form.
        typeof form.reset === 'function' ? form.reset() : $(form).trigger('reset');
        const elements = form.elements;
        var element, type;
        for (var i = 0; i < elements.length; i++) {
            element = elements[i];
            type = element.type.toLowerCase();
            switch(type) {
                case 'hidden':
                case 'button':
                case 'reset':
                case 'submit':
                    // Keep hidden fields.
                    break;
                case 'checkbox':
                case 'radio':
                    element.checked = false;
                    break;
                case 'select-one':
                case 'select-multiple':
                    $(element).find('option').removeAttr('selected').end().trigger('chosen:updated');
                    break;
                case 'number':
                    if ((element.name.endsWith('[to]')) || element.className.endsWith('-to')) {
                        element.defaultValue = element.value = element.max;
                        Search.rangeSliderDouble.controlNumericTo(element);
                    } else {
                        element.defaultValue = element.value = element.min;
                        if (element.name.endsWith('[from]') || element.className.endsWith('-from')) {
                            Search.rangeSliderDouble.controlNumericFrom(element);
                        }
                    }
                    break;
                case 'range':
                    if (element.name.endsWith('[to]') || element.className.endsWith('-to')) {
                        element.defaultValue = element.value = element.max;
                        Search.rangeSliderDouble.controlSliderTo(element);
                    } else {
                        element.defaultValue = element.value = element.min;
                        if (element.name.endsWith('[from]') || element.className.endsWith('-from')) {
                            Search.rangeSliderDouble.controlSliderFrom(element);
                        }
                    }
                    break;
                default:
                    // Text area, text, password, etc.
                    element.defaultValue = element.value = '';
                    break;
            }
        }
        return self;
    }

    /**
    * Search range double / sliders.
    *
    * "min" and "max" values are required to compute color.
    *
    * @todo Get slider colors from the css.
    * @see https://medium.com/@predragdavidovic10/native-dual-range-slider-html-css-javascript-91e778134816
    */
    self.rangeSliderDouble = (function() {
        var self = {};

        self.minDefault = 0;
        self.maxDefault = 100;
        self.colorRangeDefault = '#a7a7a7';
        self.colorSliderDefault = '#e9e9ed';

        self.getRangeDoubleElements = function(element) {
            const rangeDouble = element.closest('.range-double');
            return rangeDouble
                ? [
                    rangeDouble.querySelector('.range-numeric-from'),
                    rangeDouble.querySelector('.range-numeric-to'),
                    rangeDouble.querySelector('.range-slider-from'),
                    rangeDouble.querySelector('.range-slider-to'),
                ]
                : [null, null, null, null];
        };

        self.controlNumericFrom = function(element) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(element);
            const [from, to] = self.parseTwoElementsToInt(inputFrom, inputTo);
            [inputFrom.value, sliderFrom.value] = from > to ? [to, to] : [from, from];
            self.fillSlider(inputFrom, inputTo, sliderTo);
            return self;
        };

        self.controlNumericTo = function(element) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(element);
            const [from, to] = self.parseTwoElementsToInt(inputFrom, inputTo);
            [inputTo.value, sliderTo.value] = from <= to ? [to, to] : [from, from];
            self.fillSlider(inputFrom, inputTo, sliderTo);
            self.toggleRangeSliderAccessible(sliderTo);
            return self;
        }

        self.controlSliderFrom = function(element) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(element);
            const [from, to] = self.parseTwoElementsToInt(sliderFrom, sliderTo);
            [inputFrom.value, sliderFrom.value] = from > to ? [to, to] : [from, from];
            self.fillSlider(sliderFrom, sliderTo, sliderTo);
            return self;
        }

        self.controlSliderTo = function(element) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(element);
            const [from, to] = self.parseTwoElementsToInt(sliderFrom, sliderTo);
            [inputTo.value, sliderTo.value] = from <= to ? [to, to] : [from, from];
            self.fillSlider(sliderFrom, sliderTo, sliderTo);
            self.toggleRangeSliderAccessible(sliderTo);
            return self;
        }

        self.fillSlider = function(from, to, controlSlider, colorSlider, colorRange) {
            const minValue = to.min === '' ? self.minDefault : to.min;
            const maxValue = to.max === '' ? self.maxDefault : to.max;
            const fromValue = from.value === '' ? minValue : from.value;
            const toValue = to.value === '' ? maxValue : to.value;
            const rangeDistance = maxValue - minValue;
            const fromPosition = fromValue - minValue;
            const toPosition = toValue - minValue;
            colorSlider = colorSlider ? colorSlider : self.colorSliderDefault;
            colorRange = colorRange ? colorRange : self.colorRangeDefault;
            controlSlider.style.background = `linear-gradient(
                to right,
                ${colorSlider} 0%,
                ${colorSlider} ${fromPosition / rangeDistance * 100}%,
                ${colorRange} ${fromPosition / rangeDistance * 100}%,
                ${colorRange} ${toPosition / rangeDistance * 100}%,
                ${colorSlider} ${toPosition / rangeDistance * 100}%,
                ${colorSlider} 100%)`;
            return self;
        }

        self.toggleRangeSliderAccessible = function(sliderCurrent) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(sliderCurrent);
            sliderTo.style.zIndex = (Number(sliderFrom.value) === Number(sliderTo.value))
                || (Number(sliderTo.value) <= 0)
                ? 2
                : 0;
            return self;
        }

        self.parseTwoElementsToInt = function (currentFrom, currentTo) {
            const from = parseInt(currentFrom.value, 10);
            const to = parseInt(currentTo.value, 10);
            return [from, to];
        }

        return self;
    })();

    return self;
})();

$(document).ready(function() {

    const hasAutocomplete = typeof $.fn.autocomplete === 'function';

    /**
     * Fix when the simple and the advanced form are the same form.
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

    /**
     * Events.
     */

    $('#search-reset, #facets-reset').on('click', function () {
        const form = $(this).closest('form')[0];
        Search.smartClearForm(form);
    });

    /**
     * Init advanced search filters.
     */

    Search.filtersAdvanced.init();

    $searchFiltersAdvanced.on('click', '.search-filter-minus', function (ev) {
        const filter = $(ev.target).closest('fieldset.filter');
        Search.filtersAdvanced
            .removeFilter(filter)
            .updatePlus();
    });

    $searchFiltersAdvanced.on('click', '.search-filter-plus', function (ev) {
        // const index = fieldset.index('fieldset.filter');
        Search.filtersAdvanced
            .appendFilter()
            .updatePlus();
    });

    /**
     * Results tools (sort, pagination, per-page).
     */

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

    $('.as-url select, select.as-url').on('change', function(e) {
        const url = $(this).find('option:selected').data('url');
        if (url && url.length && window.location !== url) {
            window.location = url;
        };
    });

    /* Facets. */

    if ($searchFacets.length) {
        if (Search.facets.useApplyFacets) {
            $searchFacets.on('click', '.facets-active a', (event) => Search.facets.removeActiveFacet(event.target));
        } else {
            $searchFacets.on('change', 'input[type=checkbox][data-url]', (event) => Search.facets.directLinkCheckbox(event.target));
            $searchFacets.on('change', 'select', (event) => Search.facets.directLinkSelect(event.target));
        }

        $searchFacets.on('click', '.facet-button, .facet-see-more-or-less', function() {
            const button = $(this);
            if (button.hasClass('expand')) {
                $(this).removeClass('expand').addClass('collapse');
            } else {
                $(this).removeClass('collapse').addClass('expand');
            }
            if (button.hasClass('facet-see-more-or-less')) {
                Search.facets.seeMoreOrLess(button);
            } else {
                Search.facets.expandOrCollapse(button);
            }
        });

        $searchFacets.find('.facet-see-more-or-less').each((index, button) => Search.facets.seeMoreOrLess(button));
    }

    const rangeDoubles = document.querySelectorAll('.range-double');

    // Init ranges only when present.
    if (rangeDoubles.length) {
        rangeDoubles.forEach((rangeDouble) => {
            const rangeSliderFrom = rangeDouble.querySelector('.range-slider-from');
            const rangeSliderTo = rangeDouble.querySelector('.range-slider-to');
            if (rangeSliderFrom && rangeSliderTo) {
                Search.rangeSliderDouble.fillSlider(rangeSliderFrom, rangeSliderTo, rangeSliderTo);
                Search.rangeSliderDouble.toggleRangeSliderAccessible(rangeSliderTo);
            }
        });

        $('.range-numeric-from').on('input', (event) => Search.rangeSliderDouble.controlNumericFrom(event.target));
        $('.range-numeric-to').on('input', (event) => Search.rangeSliderDouble.controlNumericTo(event.target));
        $('.range-slider-from').on('input', (event) => Search.rangeSliderDouble.controlSliderFrom(event.target));
        $('.range-slider-to').on('input', (event) => Search.rangeSliderDouble.controlSliderTo(event.target));
    }

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
        $('input[type=search].autosuggest, input[type=text].autosuggest').each(function(index, element) {
            element = $(element);
            let autosuggestOptions = Search.autosuggestOptions(element);
            if (autosuggestOptions) {
                element.autocomplete(autosuggestOptions);
            }
        });
    }

});
