'use strict';

/**
 * Widget for the value of the setting "advancedsearch_item_sets_redirects".
 *
 * The redirection of an item set is a keyword ("browse", "search", "first")
 * or the slug of a page of the site or a url. The keywords are radios, so
 * they are visible and exclusive, and the last choice reveals a field with
 * the pages of the site as suggestions, that accepts any url too.
 *
 * @see \Common\Form\Element\TraitPairsEditor
 */
(function () {
    if (!window.CommonPairsEditor || !window.CommonPairsEditor.valueWidgets) {
        return;
    }

    var config = window.AdvancedSearchItemSetsRedirects || {};
    var labels = config.labels || {};

    var t = function (key, fallback) {
        return labels[key] || fallback;
    };

    var keywords = ['browse', 'search', 'first'];
    var groupIndex = 0;

    window.CommonPairsEditor.valueWidgets.itemSetRedirect = function (context) {
        var valueOptions = context.options.valueOptions || {};
        var pages = valueOptions.pages || {};
        var value = context.value || '';
        var name = 'common-pairs-redirect-' + (++groupIndex);

        var cell = document.createElement('span');
        cell.className = 'common-pairs-cell-value item-sets-redirect';

        var choices = document.createElement('span');
        choices.className = 'item-sets-redirect-choices';
        cell.appendChild(choices);

        // The field is hidden until the last choice is selected, and it holds
        // the value, so the editor reads it like any other cell.
        var field = document.createElement('input');
        field.type = 'text';
        field.className = 'common-pairs-value item-sets-redirect-target';
        field.placeholder = t('pageOrUrl', 'Slug of a page or url');

        var datalistId = name + '-pages';
        var datalist = document.createElement('datalist');
        datalist.id = datalistId;
        Object.keys(pages).forEach(function (slug) {
            var option = document.createElement('option');
            option.value = slug;
            option.label = pages[slug];
            datalist.appendChild(option);
        });
        if (datalist.children.length) {
            field.setAttribute('list', datalistId);
            cell.appendChild(datalist);
        }

        var radios = {};
        var isKeyword = keywords.indexOf(value) !== -1;

        var sync = function () {
            var checked = cell.querySelector('input[type=radio]:checked');
            var choice = checked ? checked.value : '';
            var custom = choice === 'custom';
            field.hidden = !custom;
            // The value of the row is the keyword, or what is typed.
            if (!custom) {
                field.value = choice;
            }
            context.changed();
        };

        [
            ['browse', t('browse', 'Browse')],
            ['search', t('search', 'Search')],
            ['first', t('first', 'First page')],
            ['custom', t('custom', 'Page or url')],
        ].forEach(function (pair) {
            var label = document.createElement('label');
            label.className = 'item-sets-redirect-choice';
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = name;
            radio.value = pair[0];
            radio.addEventListener('change', function () {
                if (pair[0] === 'custom') {
                    field.value = '';
                    setTimeout(function () { field.focus(); }, 0);
                }
                sync();
            });
            radios[pair[0]] = radio;
            label.appendChild(radio);
            label.appendChild(document.createTextNode(' ' + pair[1]));
            choices.appendChild(label);
        });

        cell.appendChild(field);

        radios[isKeyword ? value : 'custom'].checked = value !== '' || false;
        if (value === '') {
            radios.browse.checked = false;
        }
        field.value = value;
        field.hidden = isKeyword || value === '';

        field.addEventListener('input', function () { context.changed(); });

        return cell;
    };

    // This script is deferred, so the editors may already be built with a
    // plain input: rebuild them now that the widget is known.
    if (typeof window.CommonPairsEditor.rebuildAll === 'function') {
        window.CommonPairsEditor.rebuildAll();
    }
})();
