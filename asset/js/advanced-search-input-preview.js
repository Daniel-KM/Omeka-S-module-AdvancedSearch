/**
 * Preview of the type of input of a filter or a facet in the admin config.
 *
 * A static mock of the input the visitor will see is displayed under the select
 * of the type, and refreshed on change. The icons of the types in the select
 * are css (see advanced-search-manager.css).
 */

(function () {
    'use strict';

    const i18n = (window.AdvancedSearch && window.AdvancedSearch.preview) || {};
    const t = function (key, fallback) {
        return i18n[key] || fallback;
    };

    const escapeHtml = function (s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };

    let currentValues = null;
    const samples = function () {
        return currentValues && currentValues.length
            ? currentValues.slice(0, 3)
            : [t('value1', 'First value'), t('value2', 'Second value'), t('value3', 'Third value')];
    };

    // The labels of the first lines of a textarea "key = label".
    const textareaSamples = function (fieldset, name) {
        const control = fieldset.querySelector('textarea[name$="[' + name + ']"]');
        if (!control || !control.value.trim()) return null;
        return control.value.split(/\r?\n/)
            .map(function (line) {
                const pos = line.indexOf('=');
                const key = (pos === -1 ? line : line.substring(0, pos)).trim();
                const label = pos === -1 ? '' : line.substring(pos + 1).trim();
                return label || key;
            })
            .filter(function (v) { return v !== ''; });
    };

    const count = function (i, show) {
        return show ? ' <span class="count">(' + [12, 7, 3][i] + ')</span>' : '';
    };

    const checkboxes = function (values, options) {
        return '<ul class="preview-list">' + values.map(function (v, i) {
            return '<li><label><input type="checkbox"' + (i === 0 && options.checked ? ' checked' : '') + '> ' + escapeHtml(v) + count(i, options.count) + '</label></li>';
        }).join('') + '</ul>';
    };

    const links = function (values, options) {
        return '<ul class="preview-list preview-links">' + values.map(function (v, i) {
            return '<li><a href="#">' + escapeHtml(v) + count(i, options.count) + '</a></li>';
        }).join('') + '</ul>';
    };

    const radios = function (values) {
        return '<ul class="preview-list">' + values.map(function (v, i) {
            return '<li><label><input type="radio" name="preview-radio"' + (i === 0 ? ' checked' : '') + '> ' + escapeHtml(v) + '</label></li>';
        }).join('') + '</ul>';
    };

    const select = function (values, options) {
        options = options || {};
        let html = '<select' + (options.multiple ? ' multiple size="3"' : '') + '>';
        if (!options.multiple) html += '<option>' + escapeHtml(options.empty || t('choose', 'Choose…')) + '</option>';
        if (options.group) {
            html += '<optgroup label="' + escapeHtml(t('group', 'Group')) + '">';
        }
        values.forEach(function (v, i) {
            html += '<option>' + escapeHtml(v) + (options.count ? ' (' + [12, 7, 3][i] + ')' : '') + '</option>';
        });
        if (options.group) html += '</optgroup>';
        return html + '</select>';
    };

    const tree = function (values, options) {
        const item = function (v, i, children) {
            return '<li><label>' + (options.links ? '<a href="#">' : '<input type="checkbox"> ')
                + escapeHtml(v) + count(i, options.count) + (options.links ? '</a>' : '') + '</label>'
                + (children || '') + '</li>';
        };
        return '<ul class="preview-list preview-tree">'
            + item(values[0], 0, '<ul>' + item(values[1], 1) + item(values[2], 2) + '</ul>')
            + '</ul>';
    };

    const slider = function (double, o) {
        o = o || {};
        const min = o.min !== '' && o.min !== undefined && o.min !== null ? Number(o.min) : 1800;
        const max = o.max !== '' && o.max !== undefined && o.max !== null ? Number(o.max) : 1950;
        const lo = Math.round(min + (max - min) * 0.25);
        const hi = Math.round(min + (max - min) * 0.7);
        const ticks = o.ticks
            ? '<span class="preview-slider-tick" style="left:25%"></span><span class="preview-slider-tick" style="left:50%"></span><span class="preview-slider-tick" style="left:75%"></span>'
            : '';
        return '<div class="preview-slider">'
            + '<span class="preview-slider-track"><span class="preview-slider-fill"' + (double ? ' style="left:25%;right:30%"' : ' style="left:0;right:60%"') + '></span>' + ticks
            + (double ? '<span class="preview-slider-thumb" style="left:25%"></span><span class="preview-slider-thumb" style="left:70%"></span>' : '<span class="preview-slider-thumb" style="left:40%"></span>')
            + '</span>'
            + '<span class="preview-slider-values">' + (double ? '<input type="text" value="' + lo + '"> – <input type="text" value="' + hi + '">' : '<input type="text" value="' + Math.round((min + max) / 2) + '">') + '</span>'
            + '</div>';
    };

    const text = function (placeholder, value) {
        return '<input type="text"' + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '') + (value ? ' value="' + escapeHtml(value) + '"' : '') + '>';
    };

    const textAutosuggest = function () {
        const v = samples();
        return '<span class="preview-autosuggest">' + text('', t('typed', 'Val'))
            + '<span class="preview-autosuggest-list"><span>' + escapeHtml(v[0]) + '</span><span>' + escapeHtml(v[1]) + '</span></span></span>';
    };

    const note = function (message) {
        return '<p class="preview-note">' + escapeHtml(message) + '</p>';
    };

    // The mocks of the filters, by type.
    const filters = {
        '': function (o) { return o.autosuggest ? textAutosuggest() : text(o.label); },
        text: function (o) { return o.autosuggest ? textAutosuggest() : text(o.label); },
        Advanced: function (o) {
            const fields = o.fields && o.fields.length ? o.fields : [t('title', 'Title'), t('subject', 'Subject')];
            const row = function (first) {
                let html = '<div class="preview-advanced-row">';
                if (!first && o.joiner) html += '<select><option>' + escapeHtml(t('and', 'and')) + '</option><option>' + escapeHtml(t('or', 'or')) + '</option>' + (o.joinerNot ? '<option>' + escapeHtml(t('not', 'not')) + '</option>' : '') + '</select>';
                html += select(fields.slice(0, 3), {empty: fields[0]});
                if (o.operator) html += select([t('opContains', 'contains'), t('opIs', 'is exactly')], {empty: t('opContains', 'contains')});
                html += o.filterAutosuggest ? textAutosuggest() : text(t('queryText', 'Text to search'));
                return html + '</div>';
            };
            const rows = Math.max(1, Math.min(3, o.rows || 2));
            let html = '<div class="preview-advanced">';
            for (let i = 0; i < rows; i++) html += row(i === 0);
            return html + '<button type="button" class="preview-button">+</button></div>';
        },
        Checkbox: function (o) { return '<label><input type="checkbox"> ' + escapeHtml(o.label) + '</label>'; },
        HasValue: function (o) { return '<label><input type="checkbox"> ' + escapeHtml(o.valueLabel || o.label) + '</label>'; },
        MultiCheckbox: function () { return checkboxes(samples(), {}); },
        Hidden: function () { return note(t('hidden', 'Hidden field: nothing is displayed, the value is sent with the query.')); },
        Number: function (o) { const min = o.min !== '' && o.min != null ? Number(o.min) : 1800; const max = o.max !== '' && o.max != null ? Number(o.max) : 1950; return '<input type="number" value="' + Math.round((min + max) / 2) + '">'; },
        Radio: function () { return radios(samples()); },
        Range: function (o) { return slider(false, o); },
        RangeDouble: function (o) { return slider(true, o); },
        Select: function (o) { return select(samples(), {multiple: o.multiple, group: o.layoutGroup}); },
        SelectFlat: function () { return select(samples()); },
        SelectGroup: function () { return select(samples(), {group: true}); },
        MultiSelect: function () { return select(samples(), {multiple: true}); },
        MultiSelectFlat: function () { return select(samples(), {multiple: true}); },
        MultiSelectGroup: function () { return select(samples(), {multiple: true, group: true}); },
        MultiText: function () { return text('', t('multiText', 'first value, second value')); },
        Specific: function () { return note(t('specific', 'Specific type set in the options: no preview.')); },
        Access: function () { return radios([t('accessFree', 'Free'), t('accessReserved', 'Reserved'), t('accessForbidden', 'Forbidden')]); },
        Tree: function () { return tree(samples(), {}); },
        Thesaurus: function () { return tree(samples(), {}); },
    };

    // The "see more" button or the pagination of a facet.
    const more = function (o) {
        if (o.paginate) return '<span class="preview-pagination"><span class="current">1</span> <a href="#">2</a> <a href="#">3</a> <a href="#">›</a></span>';
        return '<a class="preview-see-more" href="#">' + escapeHtml(t('seeMore', 'See more')) + '</a>';
    };

    // The mocks of the facets, by type.
    const facets = {
        '': function (o) { return o.links ? links(samples(), o) : checkboxes(samples(), o); },
        Checkbox: function (o) { return (o.links ? links(samples(), o) : checkboxes(samples(), o)) + more(o); },
        CheckboxFilter: function (o) { return text(t('filterValues', 'Filter the values…')) + checkboxes(samples(), o) + more(o); },
        HasValue: function (o) { return '<label><input type="checkbox"> ' + escapeHtml(o.label) + count(0, o.count) + '</label>'; },
        RangeDouble: function (o) { return slider(true, o); },
        Select: function (o) { return select(samples(), {count: o.count}); },
        SelectRange: function () { return '<div class="preview-inline">' + select(['1850', '1900', '1950'], {empty: t('from', 'From')}) + select(['1900', '1950', '2000'], {empty: t('to', 'To')}) + '</div>'; },
        Tree: function (o) { return tree(samples(), o); },
        Thesaurus: function (o) { return tree(samples(), o); },
    };

    const fieldValue = function (fieldset, name) {
        const checked = fieldset.querySelector('input[type=radio][name$="[' + name + ']"]:checked');
        if (checked) return checked.value;
        const control = fieldset.querySelector('[name$="[' + name + ']"]');
        return control ? control.value : '';
    };

    const fieldChecked = function (fieldset, name) {
        const control = fieldset.querySelector('input[type=checkbox][name$="[' + name + ']"]');
        return !!(control && control.checked);
    };

    /**
     * Render the preview of an item (filter or facet) of the config.
     */
    // The mock of an item of the config: {type, label, html} or null.
    const buildMock = function (fieldset, kind) {
        const typeControl = fieldset.querySelector('[name$="[type]"]');
        if (!typeControl) return null;
        const type = typeControl.value || '';
        const mocks = kind === 'facet' ? facets : filters;
        const mock = mocks[type];
        const label = fieldValue(fieldset, 'label')
            || (fieldset.querySelector('select[name$="[field]"] option:checked') || {}).textContent
            || t('label', 'Label');
        currentValues = textareaSamples(fieldset, 'values');
        const elements = Array.from(fieldset.querySelectorAll('input[name$="[field_elements][]"]:checked')).map(function (c) { return c.value; });
        const options = {
            label: (label || '').trim(),
            links: fieldChecked(fieldset, 'as_link'),
            count: fieldChecked(fieldset, 'display_count'),
            multiple: fieldChecked(fieldset, 'multiple'),
            layoutGroup: fieldValue(fieldset, 'value_layout') === 'group',
            valueLabel: fieldValue(fieldset, 'value_label'),
            autosuggest: fieldChecked(fieldset, 'autosuggest'),
            min: fieldValue(fieldset, 'min'),
            max: fieldValue(fieldset, 'max'),
            ticks: fieldChecked(fieldset, 'scale_show_ticks'),
            paginate: fieldChecked(fieldset, 'paginate'),
            fields: textareaSamples(fieldset, 'fields'),
            rows: parseInt(fieldValue(fieldset, 'default_number'), 10) || 0,
            joiner: elements.indexOf('joiner') !== -1,
            joinerNot: elements.indexOf('joiner_not') !== -1,
            operator: elements.indexOf('operator') !== -1,
            filterAutosuggest: elements.indexOf('autosuggest') !== -1,
            checked: false,
        };
        if (!mock) return null;
        // A simple filter carries its label itself.
        const withTitle = kind === 'facet' || ['Checkbox', 'HasValue', 'text', ''].indexOf(type) === -1;
        return {
            type: type,
            label: options.label,
            html: (withTitle ? '<span class="preview-label">' + escapeHtml(options.label) + '</span>' : '') + mock(options),
        };
    };

    const render = function (fieldset, kind) {
        const typeControl = fieldset.querySelector('[name$="[type]"]');
        if (!typeControl) return;
        const typeField = typeControl.closest('.field');
        if (!typeField) return;
        let box = typeField.nextElementSibling;
        if (!box || !box.classList.contains('input-type-preview')) {
            box = document.createElement('div');
            box.className = 'input-type-preview';
            box.innerHTML = '<span class="input-type-preview-title">' + escapeHtml(t('preview', 'Preview')) + '</span><div class="input-type-preview-body" inert></div>';
            typeField.parentNode.insertBefore(box, typeField.nextSibling);
        }
        const built = buildMock(fieldset, kind);
        box.hidden = !built;
        if (built) {
            box.querySelector('.input-type-preview-body').innerHTML = built.html;
        }

        // The icon of the selected type in the chosen container.
        const type = typeControl.value || '';
        const chosen = typeControl.nextElementSibling;
        if (chosen && chosen.classList.contains('chosen-container')) {
            const single = chosen.querySelector('.chosen-single > span');
            if (single) {
                single.className = type ? 'input-type input-type-' + type.toLowerCase() : '';
            }
        }
    };

    // A modal preview of the whole form or facets, built from the mocks of
    // every item of the collection, in their order, in the dialog of Common.
    const showAll = function (collection, kind, title) {
        if (!window.CommonDialog) return;
        const parts = Array.from(collection.querySelectorAll('.collection-main > fieldset, :scope > fieldset'))
            .map(function (fieldset) { return buildMock(fieldset, kind); })
            .filter(Boolean)
            .map(function (built) { return '<div class="input-type-preview-item">' + built.html + '</div>'; });
        window.CommonDialog.dialogGeneric({
            heading: title,
            body: '<div class="input-type-preview-all input-type-preview-body" inert>' + parts.join('') + '</div>',
            textOk: null,
            textCancel: null,
        });
    };

    // The preview depends on the label and on some options too.
    const refresh = function (e) {
        if (!e.target.name || !/\[(label|as_link|display_count|multiple|value_layout|values|fields|value_label|autosuggest|min|max|scale_show_ticks|paginate|default_number|field_elements|position)\](\[\])?$/.test(e.target.name)) return;
        const fieldset = e.target.closest('fieldset.form-fieldset-element');
        if (!fieldset) return;
        const collection = fieldset.closest('.form-fieldset-collection');
        render(fieldset, collection && collection.id === 'facet_facets' ? 'facet' : 'filter');
    };
    document.addEventListener('input', refresh);
    document.addEventListener('change', refresh);

    // A modal preview of the page of results, built from the general
    // settings of the tab Results and from the mocks of the first facets.
    const showResultsPage = function (title) {
        if (!window.CommonDialog) return;
        const rv = function (name) {
            const checked = document.querySelector('input[type=radio][name="results[' + name + ']"]:checked');
            return checked ? checked.value : '';
        };
        const cv = function (name) {
            const control = document.querySelector('input[type=checkbox][name="results[' + name + ']"]');
            return !!(control && control.checked);
        };
        const tv = function (name) {
            const control = document.querySelector('[name="results[' + name + ']"]');
            return control ? control.value.trim() : '';
        };
        const inHeader = function (v) { return v === 'header' || v === 'both'; };
        const inFooter = function (v) { return v === 'footer' || v === 'both'; };

        // The labels of the properties to display, from the textarea.
        const propertyLabels = [];
        const fieldSource = document.getElementById('form_filter_field');
        (tv('properties') || '').split(/\r?\n/).forEach(function (line) {
            if (!line.trim()) return;
            const pos = line.indexOf('=');
            const term = (pos === -1 ? line : line.substring(0, pos)).trim();
            let label = pos === -1 ? '' : line.substring(pos + 1).trim();
            if (!label && fieldSource) {
                const opt = fieldSource.querySelector('option[value="' + CSS.escape(term) + '"]');
                if (opt) label = opt.textContent.trim();
            }
            propertyLabels.push(label || term.split(':').pop());
        });

        const bar = function () {
            let html = '<div class="preview-results-bar">';
            html += '<span>' + escapeHtml(t('results12', '12 results')) + '</span>';
            if (rv('sort') && rv('sort') !== 'none') html += select([t('sortRelevance', 'Relevance'), t('title', 'Title')], {empty: t('sortRelevance', 'Relevance')});
            if (rv('per_page') && rv('per_page') !== 'none') html += select(['10', '25', '50'], {empty: '10'});
            if (rv('grid_list') && rv('grid_list') !== 'none') html += '<span class="preview-grid-list">▦ ≣</span>';
            if (rv('paginator') && rv('paginator') !== 'none') html += '<span class="preview-pagination"><span class="current">1</span> <a href="#">2</a> <a href="#">3</a> <a href="#">›</a></span>';
            return html + '</div>';
        };

        const result = function (n) {
            const thumb = rv('thumbnail_mode') !== 'none'
                ? '<span class="preview-thumb" aria-hidden="true"></span>'
                : '';
            let body = '';
            if (propertyLabels.length) {
                body = '<dl>' + propertyLabels.slice(0, 3).map(function (label, i) {
                    return '<div><dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml([t('value1', 'First value'), t('value2', 'Second value'), t('value3', 'Third value')][i]) + '</dd></div>';
                }).join('') + '</dl>';
            } else {
                // The default card displays the title and the description.
                body = '<p class="preview-result-description">' + escapeHtml(t('resultDescription', 'Description of the resource, on a few lines…')) + '</p>';
            }
            return '<div class="preview-result">' + thumb
                + '<div class="preview-result-body"><a href="#" class="preview-result-title">' + escapeHtml(t('resultTitle', 'Title of the resource') + ' ' + n) + '</a>' + body + '</div>'
                + '</div>';
        };

        // The facets: the mocks of the two first facets of the tab Facets.
        let facetsHtml = '';
        const positionControl = document.querySelector('input[type=radio][name="facet[position]"]:checked');
        const facetsPosition = positionControl ? positionControl.value : '';
        if (facetsPosition && facetsPosition !== 'none') {
            const facetCollection = document.getElementById('facet_facets');
            if (facetCollection) {
                const mocks = Array.from(facetCollection.querySelectorAll('.collection-main > fieldset, :scope > fieldset'))
                    .slice(0, 2)
                    .map(function (fieldset) { return buildMock(fieldset, 'facet'); })
                    .filter(Boolean)
                    .map(function (built) { return '<div class="input-type-preview-item">' + built.html + '</div>'; });
                if (mocks.length) {
                    facetsHtml = '<aside class="preview-facets">' + mocks.join('') + '</aside>';
                }
            }
        }

        let html = '<div class="preview-results-page">';
        if (cv('breadcrumbs')) html += '<div class="preview-breadcrumbs">' + escapeHtml(t('home', 'Home')) + ' › ' + escapeHtml(t('searchTitle', 'Search')) + '</div>';
        if (inHeader(rv('search_form_simple'))) html += '<div class="preview-search-form">' + text('', 'lorem') + '<button type="button" class="preview-button">' + escapeHtml(t('searchTitle', 'Search')) + '</button></div>';
        if (rv('search_filters') && rv('search_filters') !== 'none') html += '<div class="preview-active-filters"><span class="preview-chip">lorem ✕</span><span class="preview-chip">' + escapeHtml(t('value1', 'First value')) + ' ✕</span></div>';
        html += bar();
        html += '<div class="preview-results-layout' + (facetsPosition === 'after' ? ' preview-facets-after' : '') + '">' + facetsHtml
            + '<div class="preview-results-list">' + result(1) + result(2) + '</div></div>';
        const footParts = [];
        if (inFooter(rv('paginator'))) footParts.push('<span class="preview-pagination"><span class="current">1</span> <a href="#">2</a> <a href="#">3</a> <a href="#">›</a></span>');
        if (footParts.length) html += '<div class="preview-results-bar">' + footParts.join('') + '</div>';
        html += '</div>';

        window.CommonDialog.dialogGeneric({
            heading: title,
            body: '<div class="input-type-preview-all input-type-preview-body" inert>' + html + '</div>',
            textOk: null,
            textCancel: null,
        });
    };

    window.AdvancedSearchInputPreview = {
        render: render,
        buildMock: buildMock,
        showAll: showAll,
        showResultsPage: showResultsPage,
    };
})();
