'use strict';

/**
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2026
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
            'dt',
            'ndt',
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
                const val = $(filterFieldset).find('[name^="filter["][name$="][val]"]');
                // A filter is filled when there is a field and either:
                // - a type that requires no value (like "is empty")
                // - or a value in the val field
                const hasField = field.length && field.val();
                const typeRequiresNoValue = type.length && Search.filterTypes.withoutValue.includes(type.val());
                const hasValue = val.length && val.val();
                if (hasField && (typeRequiresNoValue || hasValue)) {
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
        // Or different search engines are used, some with grid, some with list.
        var resourceLists = document.querySelectorAll('.search-results .resource-list, .search-results .resources-list-content');
        var searchResultsList = document.querySelector('.search-results-list');
        var searchResultsMap = document.querySelector('.search-results-map');

        var hasOnlyMode = Array.prototype.some.call(resourceLists, function(el) {
            return el.classList.contains('only-mode');
        });
        if (hasOnlyMode) {
            return;
        }

        // Handle map view type.
        if (viewType === 'map') {
            // Hide results list, show map.
            if (searchResultsList) {
                searchResultsList.style.display = 'none';
            }
            if (searchResultsMap) {
                searchResultsMap.style.display = 'block';
                // Initialize map if not already done.
                self.initSearchMap();
            }
        } else {
            // Show results list, hide map.
            if (searchResultsList) {
                searchResultsList.style.display = '';
            }
            if (searchResultsMap) {
                searchResultsMap.style.display = 'none';
            }
            // Apply grid/list class to resource lists.
            for (var i = 0; i < resourceLists.length; i++) {
                var resourceItem = resourceLists[i];
                resourceItem.className = resourceItem.className.replace(' grid', '').replace(' list', '')
                    + ' ' + viewType;
            }
        }
        localStorage.setItem('search_view_type', viewType);
    };

    /**
     * Map state tracking.
     */
    self.mapInitialized = false;
    self.map = null;
    self.features = null;
    self.featuresPoint = null;
    self.featuresPoly = null;

    /**
     * Initialize the search results map.
     * Uses the Mapping module's API to load features.
     */
    self.initSearchMap = function() {
        if (self.mapInitialized) {
            // Map already initialized, just invalidate size in case container was hidden.
            if (self.map) {
                self.map.invalidateSize();
            }
            return;
        }

        var searchMapDiv = document.getElementById('search-map');
        if (!searchMapDiv) {
            return;
        }

        // Check if MappingModule is available.
        if (typeof MappingModule === 'undefined') {
            console.warn('MappingModule not loaded. Map view requires the Mapping module.');
            return;
        }

        var featuresUrl = searchMapDiv.dataset.featuresUrl;
        var popupUrl = searchMapDiv.dataset.featurePopupContentUrl;
        var basemapProvider = searchMapDiv.dataset.basemapProvider || 'OpenStreetMap.Mapnik';
        var disableClustering = searchMapDiv.dataset.disableClustering === '1';

        // Get item IDs from the search results (passed from PHP as comma-separated string).
        // Empty string means: show all features (browse mode or too many results).
        var itemIdsString = searchMapDiv.dataset.itemIds || '';
        var itemsQuery = {};

        if (itemIdsString) {
            // Filtered search with reasonable number of results: pass IDs.
            // Omeka API supports "id=x,y,z" format.
            itemsQuery.id = itemIdsString;
        }
        // If empty, itemsQuery stays empty and Mapping will show all features.

        // Set map height.
        searchMapDiv.style.height = '600px';

        // Initialize map using MappingModule.
        var mapResult = MappingModule.initializeMap(searchMapDiv, {}, {
            disableClustering: disableClustering,
            basemapProvider: basemapProvider
        });

        self.map = mapResult[0];
        self.features = mapResult[1];
        self.featuresPoint = mapResult[2];
        self.featuresPoly = mapResult[3];

        // Load features.
        var onFeaturesLoad = function() {
            if (!self.map.mapping_map_interaction) {
                var bounds = self.features.getBounds();
                if (bounds.isValid()) {
                    self.map.fitBounds(bounds);
                }
            }
        };

        MappingModule.loadFeaturesAsync(
            self.map,
            self.featuresPoint,
            self.featuresPoly,
            featuresUrl,
            popupUrl,
            JSON.stringify(itemsQuery),
            JSON.stringify({}),
            onFeaturesLoad
        );

        self.mapInitialized = true;
    };

    /* Facets. */

    self.facets = (function() {
        var self = {};

        /**
         * Modes may be "click a button to apply facets" or "reload the page directly".
         * .apply-facets is kept for compatibility with old themes.
         */
        self.useApplyFacets = $searchFacets.find('.facets-apply, .apply-facets').length > 0;

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
                // Collapsing: remove pagination and show only default items.
                self.removePagination(button);
                button.text(button.attr('data-label-see-more') ? button.attr('data-label-see-more') : (hasOmekaTranslate ? Omeka.jsTranslate('See more') : 'See more'));
                const defaultCount = Number(button.attr('data-default-count')) + 1;
                button.closest('.facet').find('.facet-items .facet-item:nth-child(n+' + defaultCount + ')').attr('hidden', 'hidden');
            } else {
                // Expanding: check if pagination is enabled.
                const perPage = Number(button.attr('data-per-page')) || 0;
                if (perPage > 0) {
                    button.text(button.attr('data-label-see-less') ? button.attr('data-label-see-less') : (hasOmekaTranslate ? Omeka.jsTranslate('See less') : 'See less'));
                    self.initPagination(button, perPage);
                } else {
                    button.text(button.attr('data-label-see-less') ? button.attr('data-label-see-less') : (hasOmekaTranslate ? Omeka.jsTranslate('See less') : 'See less'));
                    button.closest('.facet').find('.facet-items .facet-item').removeAttr('hidden');
                }
            }
            $searchFacets.trigger('o:advanced-search.facet.see-more-or-less');
            return self;
        };

        /**
         * Initialize pagination for a facet.
         *
         * @param {jQuery} button The "see more/less" button.
         * @param {number} perPage Number of items per page.
         */
        self.initPagination = function(button, perPage) {
            button = $(button);
            var facet = button.closest('.facet');
            var items = facet.find('.facet-items .facet-item');

            // Count all non-active (unchecked) items for pagination.
            var paginableItems = items.filter(function() {
                var input = $(this).find('input[type=checkbox]');
                return !input.length || !input.prop('checked');
            });
            var totalPages = Math.ceil(paginableItems.length / perPage);
            if (totalPages <= 0) totalPages = 1;

            // Remove any existing pagination.
            facet.find('.facet-pagination').remove();

            var labelPage = button.attr('data-label-page') || 'Page';
            var paginationHtml = '<div class="facet-pagination">'
                + '<button type="button" class="facet-page-first" title="' + labelPage + ' 1">&laquo;</button>'
                + '<button type="button" class="facet-page-prev">&lsaquo;</button>'
                + '<span class="facet-page-indicator">1/' + totalPages + '</span>'
                + '<button type="button" class="facet-page-next">&rsaquo;</button>'
                + '<button type="button" class="facet-page-last" title="' + labelPage + ' ' + totalPages + '">&raquo;</button>'
                + '</div>';
            button.closest('.facet-see-more').before(paginationHtml);

            // Store pagination state on the facet element.
            facet.data('facet-page', 1);
            facet.data('facet-total-pages', totalPages);
            facet.data('facet-per-page', perPage);

            self.showPage(button, 1);
            return self;
        };

        /**
         * Show a specific page of facet items.
         *
         * Active/checked items always remain visible.
         *
         * @param {jQuery} button The "see more/less" button.
         * @param {number} page The page number (1-based).
         */
        self.showPage = function(button, page) {
            button = $(button);
            var facet = button.closest('.facet');
            var items = facet.find('.facet-items .facet-item');
            var perPage = facet.data('facet-per-page') || Number(button.attr('data-per-page')) || 10;
            var totalPages = facet.data('facet-total-pages') || 1;

            if (page < 1) page = 1;
            if (page > totalPages) page = totalPages;

            facet.data('facet-page', page);

            var paginableIndex = 0;
            var startIndex = (page - 1) * perPage;
            var endIndex = page * perPage;

            items.each(function() {
                var item = $(this);
                var input = item.find('input[type=checkbox]');
                var isActive = input.length && input.prop('checked');

                if (isActive) {
                    // Active/checked items: always visible, don't count.
                    item.removeAttr('hidden');
                } else {
                    // All non-active items are paginated.
                    if (paginableIndex >= startIndex && paginableIndex < endIndex) {
                        item.removeAttr('hidden');
                    } else {
                        item.attr('hidden', 'hidden');
                    }
                    paginableIndex++;
                }
            });

            // Update indicator.
            facet.find('.facet-page-indicator').text(page + '/' + totalPages);

            // Update button states.
            facet.find('.facet-page-first, .facet-page-prev').prop('disabled', page <= 1);
            facet.find('.facet-page-next, .facet-page-last').prop('disabled', page >= totalPages);

            return self;
        };

        /**
         * Remove pagination controls and reset state.
         *
         * @param {jQuery} button The "see more/less" button.
         */
        self.removePagination = function(button) {
            button = $(button);
            var facet = button.closest('.facet');
            facet.find('.facet-pagination').remove();
            facet.removeData('facet-page');
            facet.removeData('facet-total-pages');
            facet.removeData('facet-per-page');
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
        if (!form) {
            return;
        }
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
                    // Fix reset issue with some config.
                    $(element).prop('checked', false);
                    $(element).removeAttr('checked');
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
     * Clean search query by removing empty inputs before form submission.
     *
     * This produces cleaner URLs like ?q=pont instead of
     * ?q=pont&submit=&filter[0][join]=and&filter[0][field]=...&filter[0][val]=
     *
     * Generic solution for any form structure:
     * - Groups with empty value field (val, text) AND type requiring value → remove group
     * - Types without value (ex, nex, etc.) are kept even if val is empty
     * - Empty simple inputs are removed directly
     * - "0" is kept as a valid value
     */
    self.cleanSearchQuery = function(form) {
        if (!form) return self;
        const $form = $(form);

        const isEmpty = (v) => v === '' || v === null || (Array.isArray(v) && !v.length);
        const typesWithValue = self.filterTypes.withValue;

        // Groups to remove (identified by their prefix).
        const groupsToRemove = new Set();

        // First pass: find groups with empty value fields where type requires value.
        $form.find(':input[name]').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            const value = $input.val();

            // Keep "0" as valid value.
            if (value === '0' || value === 0) return;

            // Match grouped inputs: prefix[val] or prefix[text].
            const match = name.match(/^(.+)\[(val|text)\]$/);
            if (match && isEmpty(value)) {
                const prefix = match[1]; // e.g., "filter[0]" or "property[1]"
                // Check if the type requires a value.
                const typeInput = $form.find(`[name="${prefix}[type]"]`);
                const typeVal = typeInput.length ? typeInput.val() : null;
                // Remove group if no type field, or type requires value.
                if (!typeVal || typesWithValue.includes(typeVal)) {
                    groupsToRemove.add(prefix);
                }
            }
        });

        // Second pass: remove groups and empty simple inputs.
        $form.find(':input[name]').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            const value = $input.val();

            // Keep "0" as valid value.
            if (value === '0' || value === 0) return;

            // Check if this input belongs to a group to remove.
            for (const prefix of groupsToRemove) {
                if (name.startsWith(prefix + '[')) {
                    $input.prop('name', '');
                    return;
                }
            }

            // Simple input: remove if empty.
            if (isEmpty(value)) {
                $input.prop('name', '');
            }
        });

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
            const [from, to] = self.parseTwoElementsToNumber(inputFrom, inputTo);
            [inputFrom.value, sliderFrom.value] = from > to ? [to, to] : [from, from];
            self.fillSlider(inputFrom, inputTo, sliderTo);
            return self;
        };

        self.controlNumericTo = function(element) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(element);
            const [from, to] = self.parseTwoElementsToNumber(inputFrom, inputTo);
            [inputTo.value, sliderTo.value] = from <= to ? [to, to] : [from, from];
            self.fillSlider(inputFrom, inputTo, sliderTo);
            self.toggleRangeSliderAccessible(sliderTo);
            return self;
        }

        self.controlSliderFrom = function(element) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(element);
            const [from, to] = self.parseTwoElementsToNumber(sliderFrom, sliderTo);
            [inputFrom.value, sliderFrom.value] = from > to ? [to, to] : [from, from];
            self.fillSlider(sliderFrom, sliderTo, sliderTo);
            return self;
        }

        self.controlSliderTo = function(element) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(element);
            const [from, to] = self.parseTwoElementsToNumber(sliderFrom, sliderTo);
            [inputTo.value, sliderTo.value] = from <= to ? [to, to] : [from, from];
            self.fillSlider(sliderFrom, sliderTo, sliderTo);
            self.toggleRangeSliderAccessible(sliderTo);
            return self;
        }

        self.fillSlider = function(fromEl, toEl, controlSlider, colorSlider, colorRange) {
            // Here, from and to may be the input or the slider.
            // This is the main point to manage the double slider simply.

            const rangeDouble = controlSlider.closest('.range-double');
            if (!rangeDouble) {
                return self;
            }
            const prog = rangeDouble.querySelector('.range-progress');
            if (!prog) {
                return self;
            }

            // Validate bounds.
            const min = toEl.min !== '' && !isNaN(parseFloat(toEl.min)) ? parseFloat(toEl.min) : self.minDefault;
            const max = toEl.max !== '' && !isNaN(parseFloat(toEl.max)) ? parseFloat(toEl.max) : self.maxDefault;

            // Parse current values.
            const fromVal = fromEl.value !== '' && !isNaN(parseFloat(fromEl.value)) ? parseFloat(fromEl.value) : min;
            const toVal = toEl.value !== '' && !isNaN(parseFloat(toEl.value)) ? parseFloat(toEl.value) : max;

            const fromPct = ((Math.min(Math.max(fromVal, min), max) - min) / (max - min)) * 100;
            const toPct = ((Math.min(Math.max(toVal, min), max) - min) / (max - min)) * 100;

            const fromProgress= Math.min(fromPct, toPct);
            const toProgress = Math.max(fromPct, toPct);

            // Drive CSS variables on the visible progress bar.
            // Previous version used a linear-gradient on main range.
            prog.style.setProperty('--from', fromProgress + '%');
            prog.style.setProperty('--to', toProgress + '%');

            return self;
        }

        self.toggleRangeSliderAccessible = function(sliderCurrent) {
            const [inputFrom, inputTo, sliderFrom, sliderTo] = self.getRangeDoubleElements(sliderCurrent);
            if (!sliderFrom || !sliderTo) return self;
            const min = sliderFrom.min !== '' && !isNaN(parseFloat(sliderFrom.min)) ? parseFloat(sliderFrom.min) : self.minDefault;
            const max = sliderTo.max !== '' && !isNaN(parseFloat(sliderTo.max)) ? parseFloat(sliderTo.max) : self.maxDefault;
            const fromValue = sliderFrom.value !== '' && !isNaN(parseFloat(sliderFrom.value)) ? parseFloat(sliderFrom.value) : min;
            const toValue = sliderTo.value !== '' && !isNaN(parseFloat(sliderTo.value)) ? parseFloat(sliderTo.value) : max;

            // Allow right slider to capture events when overlap.
            sliderTo.style.zIndex = (fromValue === toValue) ? '2' : '0';
            return self;
        }

        self.parseTwoElementsToNumber = function (currentFrom, currentTo) {
            const from = parseFloat(currentFrom.value, 10);
            const to = parseFloat(currentTo.value, 10);
            return [from, to];
        }

        self.ensureValidBounds = function(el) {
            if (!el) {
                return self;
            }

            const minDefault = self.minDefault;
            const maxDefault = self.maxDefault;

            // Validate min and max. Swap if needed.
            let min = el.min !== '' ? parseFloat(el.min) : NaN;
            let max = el.max !== '' ? parseFloat(el.max) : NaN;
            if (isNaN(min)) {
                min = minDefault;
            }
            if (isNaN(max)) {
                max = maxDefault;
            }
            if (min > max) {
                const tmp = min;
                min = max;
                max = tmp;
            }
            el.min = String(min);
            el.max = String(max);

            // Clamp current value into bounds when present.
            if (el.value !== '') {
                const val = parseFloat(el.value);
                el.value = isNaN(val) ? String(min) : String(Math.min(Math.max(val, min), max));
            } else {
                // Default to lower bound for "from", upper for "to" if class hints exist.
                if (el.classList.contains('range-slider-to') || el.classList.contains('range-numeric-to')) {
                    el.value = String(max);
                } else {
                    el.value = String(min);
                }
            }

            return self;
        }

        self.normalizeRangeDouble = function(rangeDouble) {
            if (!rangeDouble) {
                return self;
            }

            const inputFrom = rangeDouble.querySelector('.range-numeric-from');
            const inputTo = rangeDouble.querySelector('.range-numeric-to');
            const sliderFrom = rangeDouble.querySelector('.range-slider-from');
            const sliderTo = rangeDouble.querySelector('.range-slider-to');

            // Ensure valid bounds on all parts.
            [inputFrom, inputTo, sliderFrom, sliderTo].forEach(self.ensureValidBounds);

            // Sync values: prefer the slider values if present; keep order from <= to.
            const fromVal = parseFloat(sliderFrom && sliderFrom.value || inputFrom && inputFrom.value || self.minDefault);
            const toVal = parseFloat(sliderTo && sliderTo.value || inputTo && inputTo.value || self.maxDefault);
            const min = parseFloat(sliderTo ? sliderTo.min : self.minDefault);
            const max = parseFloat(sliderTo ? sliderTo.max : self.maxDefault);

            const from = isNaN(fromVal) ? min : Math.min(Math.max(fromVal, min), max);
            const to = isNaN(toVal) ? max : Math.min(Math.max(toVal, min), max);
            const fromClamped = Math.min(from, to);
            const toClamped = Math.max(from, to);

            if (inputFrom) {
                inputFrom.value = String(fromClamped);
            }
            if (sliderFrom) {
                sliderFrom.value = String(fromClamped);
            }
            if (inputTo) {
                inputTo.value = String(toClamped);
            }
            if (sliderTo) {
                sliderTo.value = String(toClamped);
            }

            // Render and adjust accessibility.
            if (sliderFrom && sliderTo) {
                self.fillSlider(sliderFrom, sliderTo, sliderTo);
                self.toggleRangeSliderAccessible(sliderTo);
            } else if (inputFrom && inputTo && sliderTo) {
                self.fillSlider(inputFrom, inputTo, sliderTo);
                self.toggleRangeSliderAccessible(sliderTo);
            }

            return self;
        };

        /**
         * Clear range values at extremes before form submission.
         *
         * When the slider is at the minimum, don't filter by "from" (any start).
         * When the slider is at the maximum, don't filter by "to" (any end).
         * This provides better UX: user hasn't moved the slider = no filter.
         *
         * Note: For <input type="range">, setting value='' resets to min,
         * so we remove the name attribute to exclude from form submission.
         *
         * @todo Backend: sort items without date values last when no filter is active.
         * @todo Backend: exclude items without date when filter is active, include when not.
         * @todo Add admin option to enable/disable this behavior per field.
         */
        self.clearExtremesBeforeSubmit = function(rangeDouble) {
            if (!rangeDouble) {
                return self;
            }

            const inputFrom = rangeDouble.querySelector('.range-numeric-from');
            const inputTo = rangeDouble.querySelector('.range-numeric-to');
            const sliderFrom = rangeDouble.querySelector('.range-slider-from');
            const sliderTo = rangeDouble.querySelector('.range-slider-to');

            // Get min/max from slider or input attributes.
            const refElement = sliderFrom || inputFrom;
            if (!refElement) {
                return self;
            }

            const min = parseFloat(refElement.min);
            const max = parseFloat(refElement.max);

            // Remove "from" from submission if it equals min.
            // For range inputs, we remove the name attribute (value='' doesn't work).
            const fromValue = parseFloat(sliderFrom ? sliderFrom.value : (inputFrom ? inputFrom.value : NaN));
            if (!isNaN(fromValue) && !isNaN(min) && fromValue === min) {
                if (sliderFrom) sliderFrom.removeAttribute('name');
                if (inputFrom && inputFrom.name) inputFrom.removeAttribute('name');
            }

            // Remove "to" from submission if it equals max.
            const toValue = parseFloat(sliderTo ? sliderTo.value : (inputTo ? inputTo.value : NaN));
            if (!isNaN(toValue) && !isNaN(max) && toValue === max) {
                if (sliderTo) sliderTo.removeAttribute('name');
                if (inputTo && inputTo.name) inputTo.removeAttribute('name');
            }

            return self;
        };

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
        // The button may be outside the form.
        const form = $(this)[0].form;
        Search.smartClearForm(form);
        // Reload page only when there is no button to apply.
        if (!Search.facets.useApplyFacets) {
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);
            for (const key of params.keys()) {
                if (key.startsWith('facet[') || key.startsWith('facet%5B')) {
                    params.delete(key);
                }
            }
            url.search = params.toString();
            window.location.href = url.toString();
        }
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

    /**
     * Map view handler.
     * Requires the Mapping module to be installed.
     */
    $('.search-view-type-map').on('click', function(e) {
        e.preventDefault();
        Search.setViewType('map');
        $('.search-view-type').removeClass('active');
        $('.search-view-type-map').addClass('active');
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

        // Pagination navigation buttons.
        $searchFacets.on('click', '.facet-page-first', function() {
            var facet = $(this).closest('.facet');
            var button = facet.find('.facet-see-more-or-less');
            Search.facets.showPage(button, 1);
        });
        $searchFacets.on('click', '.facet-page-prev', function() {
            var facet = $(this).closest('.facet');
            var button = facet.find('.facet-see-more-or-less');
            var page = facet.data('facet-page') || 1;
            Search.facets.showPage(button, page - 1);
        });
        $searchFacets.on('click', '.facet-page-next', function() {
            var facet = $(this).closest('.facet');
            var button = facet.find('.facet-see-more-or-less');
            var page = facet.data('facet-page') || 1;
            Search.facets.showPage(button, page + 1);
        });
        $searchFacets.on('click', '.facet-page-last', function() {
            var facet = $(this).closest('.facet');
            var button = facet.find('.facet-see-more-or-less');
            var totalPages = facet.data('facet-total-pages') || 1;
            Search.facets.showPage(button, totalPages);
        });

        // Filter facet values via the search input (CheckboxSearch type).
        $searchFacets.on('input', '.facet-search-input', function() {
            const input = $(this);
            const filter = input.val().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const facet = input.closest('.facet');
            const seeMore = facet.find('.facet-see-more-or-less');
            const pagination = facet.find('.facet-pagination');
            if (filter.length) {
                // Hide pagination while filtering.
                if (pagination.length) {
                    pagination.hide();
                }
                // Show all items (remove hidden from pagination) so filter works on all.
                facet.find('.facet-items .facet-item').removeAttr('hidden');
                // Expand if collapsed.
                if (seeMore.length && seeMore.hasClass('expand')) {
                    seeMore.removeClass('expand').addClass('collapse');
                    // Don't call seeMoreOrLess here to avoid re-pagination,
                    // just update the label.
                    seeMore.text(seeMore.attr('data-label-see-less') || 'See less');
                }
            } else {
                // Restore pagination when filter is cleared.
                if (pagination.length) {
                    pagination.show();
                    var page = facet.data('facet-page') || 1;
                    Search.facets.showPage(seeMore, page);
                } else if (seeMore.length && seeMore.hasClass('collapse')) {
                    // No pagination: re-apply see-more state.
                    var perPage = Number(seeMore.attr('data-per-page')) || 0;
                    if (perPage > 0) {
                        // Re-init pagination.
                        Search.facets.initPagination(seeMore, perPage);
                    }
                }
            }
            facet.find('.facet-items .facet-item').each(function() {
                const item = $(this);
                const text = item.text().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                if (!filter.length || text.indexOf(filter) !== -1) {
                    item.removeClass('facet-search-hidden');
                } else {
                    item.addClass('facet-search-hidden');
                }
            });
        });
    }

    const rangeDoubles = document.querySelectorAll('.range-double');

    // Init ranges only when present.
    if (rangeDoubles.length) {
        rangeDoubles.forEach((rangeDouble) => {
            Search.rangeSliderDouble.normalizeRangeDouble(rangeDouble);
        });
        $('.range-numeric-from').on('input', (event) => Search.rangeSliderDouble.controlNumericFrom(event.target));
        $('.range-numeric-to').on('input', (event) => Search.rangeSliderDouble.controlNumericTo(event.target));
        $('.range-slider-from').on('input', (event) => Search.rangeSliderDouble.controlSliderFrom(event.target));
        $('.range-slider-to').on('input', (event) => Search.rangeSliderDouble.controlSliderTo(event.target));

        // Clear extreme values and empty inputs before form submission.
        // When slider is at min, don't filter by "from"; at max, don't filter by "to".
        // Also clean empty query parameters for cleaner URLs.
        // Note: form ID can be "form-search", "search-form", or custom.
        $('#search-form, #form-search, #search-filters-form, #facets-form, .search-facets form, #advanced-search-form form').on('submit', function() {
            $(this).find('.range-double').each(function() {
                Search.rangeSliderDouble.clearExtremesBeforeSubmit(this);
            });
            Search.cleanSearchQuery(this);
        });

        // Handle range-double submit button click (for link/js mode with Ok button).
        $('.range-double-submit').on('click', function(e) {
            const rangeDouble = $(this).closest('.range-double');
            if (!rangeDouble.length) return;

            // Check if using direct link mode (data-url on inputs).
            const inputFrom = rangeDouble.find('.range-numeric-from, .range-slider-from').filter('[data-url]').first();
            const inputTo = rangeDouble.find('.range-numeric-to, .range-slider-to').filter('[data-url]').first();

            if (!inputFrom.length && !inputTo.length) {
                // Not direct link mode, let form submission handle it.
                return;
            }

            e.preventDefault();

            // Get min/max from attributes.
            const refEl = rangeDouble.find('.range-slider-from, .range-numeric-from')[0];
            const min = parseFloat(refEl?.min);
            const max = parseFloat(refEl?.max);
            const fromVal = parseFloat(rangeDouble.find('.range-numeric-from').val() || rangeDouble.find('.range-slider-from').val());
            const toVal = parseFloat(rangeDouble.find('.range-numeric-to').val() || rangeDouble.find('.range-slider-to').val());

            // Build URL: use "to" input URL as base, modify from/to params.
            let url = inputTo.data('url') || inputFrom.data('url');
            if (!url) return;

            // Parse URL to modify query parameters.
            const urlObj = new URL(url, window.location.origin);
            const facetName = inputFrom.attr('name')?.match(/facet\[([^\]]+)\]/)?.[1]
                || inputTo.attr('name')?.match(/facet\[([^\]]+)\]/)?.[1];

            if (facetName) {
                // Only add from/to if not at extremes.
                if (!isNaN(fromVal) && !isNaN(min) && fromVal !== min) {
                    urlObj.searchParams.set(`facet[${facetName}][from]`, fromVal);
                } else {
                    urlObj.searchParams.delete(`facet[${facetName}][from]`);
                }
                if (!isNaN(toVal) && !isNaN(max) && toVal !== max) {
                    urlObj.searchParams.set(`facet[${facetName}][to]`, toVal);
                } else {
                    urlObj.searchParams.delete(`facet[${facetName}][to]`);
                }
            }

            window.location.href = urlObj.toString();
        });
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
     * Auto-scroll to results when enabled.
     */
    var searchResults = document.getElementById('search-results');
    if (searchResults && searchResults.dataset.autoscroll) {
        searchResults.scrollIntoView({behavior: 'smooth', block: 'start'});
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
        $('select.chosen-select').chosen(Search.chosenOptions);
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
