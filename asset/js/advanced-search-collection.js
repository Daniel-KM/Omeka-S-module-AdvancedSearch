'use strict';

/**
 * List and panel layout for the collections of the search config form
 * (facets, filters, sorts): a compact reorderable list of the items on the
 * left with their actions (move, remove), the selected item alone on the
 * right, its common fields visible and the advanced ones folded. The tab of
 * a collection gets two sub tabs: the general settings and the list.
 *
 * The form is not altered: the item fieldsets stay in place inside the
 * collection, only shown or hidden by a class, and a drag and drop or the
 * move buttons reorder them in the DOM, so the submission is unchanged and
 * the buttons of the manager (add, remove, up, down) keep working. Without
 * javascript the plain form keeps working too.
 */

(function () {

    // The common fields of an item are flagged with data-common by the form;
    // the others are folded. Fallback by collection when nothing is flagged.
    const commonFields = {
        facet_facets: ['field', 'label', 'type', 'limit', 'order'],
        form_filters: ['field', 'label', 'type'],
    };

    const i18n = (window.AdvancedSearch && window.AdvancedSearch.collection) || {};
    const t = function (key, fallback) {
        return i18n[key] || fallback;
    };

    const escapeHtml = function (s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };

    // The last segment of a wrapped name ("facet[facets][2][field]" → "field").
    const shortName = function (name) {
        const m = /\[([^\]]+)\]$/.exec((name || '').replace(/\[\]$/, ''));
        return m ? m[1] : name;
    };

    // The value of the control of a field of an item, as displayed.
    const fieldText = function (fieldset, name) {
        const control = fieldset.querySelector('[name$="[' + name + ']"]');
        if (!control) return '';
        if (control.tagName === 'SELECT') {
            const opt = control.selectedOptions[0];
            return opt && opt.value !== '' ? opt.textContent.trim() : '';
        }
        return (control.value || '').trim();
    };

    // The normalized value of a field: the checked radio, the checked
    // checkboxes, the selected option or the text, as a string.
    const fieldValue = function (field) {
        const radios = Array.from(field.querySelectorAll('input[type=radio]'));
        if (radios.length) {
            const checked = radios.find(function (r) { return r.checked; });
            return checked ? checked.value : '';
        }
        const checkboxes = Array.from(field.querySelectorAll('input[type=checkbox]'));
        if (checkboxes.length) {
            return checkboxes.filter(function (c) { return c.checked; }).map(function (c) { return c.value; }).join('|');
        }
        const control = field.querySelector('select, textarea, input:not([type=hidden])');
        if (!control) return '';
        if (control.tagName === 'SELECT') {
            return Array.from(control.selectedOptions).map(function (o) { return o.value; }).filter(Boolean).join('|');
        }
        return (control.value || '').trim();
    };

    // The default values of a new item, read from the template of the
    // collection, keyed by short field name.
    const templateDefaults = function (collection) {
        const span = collection.querySelector(':scope > span[data-template]');
        if (!span) return {};
        const holder = document.createElement('div');
        holder.innerHTML = span.getAttribute('data-template').split('__index__').join('0');
        const out = {};
        holder.querySelectorAll('.field').forEach(function (field) {
            const control = field.querySelector('input[name], select[name], textarea[name]');
            if (control) out[shortName(control.name)] = fieldValue(field);
        });
        return out;
    };

    // Show or hide the fields of an item according to the type of the item:
    // a field with data-filter-types applies to these types only.
    // A plausible default type for a field, when the type is still empty:
    // dates get a range slider, controlled values a select, else a text.
    const defaultTypeForField = function (field) {
        const f = (field || '').toLowerCase();
        if (f === '') return '';
        if (f === 'advanced') return 'Advanced';
        if (/date|created|modified|issued|year|temporal|_dt|_dr/.test(f)) return 'RangeDouble';
        if (/item_set|resource_class|resource_template|owner|site|media_type|type|language|rights|format|subject|creator|contributor|publisher/.test(f)) return 'Select';
        return 'text';
    };

    const applyTypeDependencies = function (fieldset) {
        const typeControl = fieldset.querySelector('[name$="[type]"]');
        if (!typeControl) return;
        const type = typeControl.value || typeControl.dataset.typeDefault || '';
        fieldset.querySelectorAll('[data-filter-types]').forEach(function (control) {
            const types = control.dataset.filterTypes.split(' ');
            const field = control.closest('.field');
            if (field) field.style.display = types.indexOf(type) === -1 ? 'none' : '';
        });
        fieldset.querySelectorAll('[data-filter-types-not]').forEach(function (control) {
            const types = control.dataset.filterTypesNot.split(' ');
            const field = control.closest('.field');
            if (field) field.style.display = types.indexOf(type) !== -1 ? 'none' : '';
        });
        // An inline group is hidden when all its fields are.
        fieldset.querySelectorAll('.collection-inline').forEach(function (group) {
            const visible = Array.from(group.children).some(function (f) { return f.style.display !== 'none'; });
            group.style.display = visible ? '' : 'none';
        });
        // A section heading is hidden when all its fields are.
        fieldset.querySelectorAll('.collection-advanced-section').forEach(function (heading) {
            let visible = false;
            let node = heading.nextElementSibling;
            while (node && !node.classList.contains('collection-advanced-section')) {
                if (node.style.display !== 'none') { visible = true; break; }
                node = node.nextElementSibling;
            }
            heading.style.display = visible ? '' : 'none';
        });
        // The label falls back to the label of the field: show it as a
        // placeholder.
        const fieldControl = fieldset.querySelector('select[name$="[field]"]');
        const labelControl = fieldset.querySelector('input[name$="[label]"]');
        if (fieldControl && labelControl) {
            const opt = fieldControl.selectedOptions[0];
            labelControl.placeholder = opt && opt.value !== '' ? opt.textContent.trim() : '';
        }
        // The preview of the type of input.
        if (window.AdvancedSearchInputPreview) {
            const collection = fieldset.closest('.form-fieldset-collection');
            window.AdvancedSearchInputPreview.render(fieldset, collection && collection.id === 'facet_facets' ? 'facet' : 'filter');
        }
    };

    const initCollection = function (collection) {
        if (collection.dataset.collectionReady) return;
        collection.dataset.collectionReady = '1';
        collection.classList.add('collection-layout');

        const common = commonFields[collection.id] || [];
        const defaults = templateDefaults(collection);

        // Layout: the legend and the comment stay above; a flex wrapper holds
        // the list and its "+" on the left, the items on the right. The "+"
        // button and the template stay direct children of the collection,
        // where the manager script looks for them; the button is only moved
        // visually under the list.
        const list = document.createElement('ul');
        list.className = 'collection-list';
        list.setAttribute('role', 'listbox');
        const empty = document.createElement('p');
        empty.className = 'collection-empty';
        empty.textContent = t('empty', 'No item yet: use the button + to add one.');
        const wrapper = document.createElement('div');
        wrapper.className = 'collection-wrapper';
        const side = document.createElement('div');
        side.className = 'collection-side';
        const main = document.createElement('div');
        main.className = 'collection-main';
        side.appendChild(list);
        main.appendChild(empty);
        wrapper.appendChild(side);
        wrapper.appendChild(main);
        Array.from(collection.children).forEach(function (child) {
            if (child.tagName === 'FIELDSET') main.appendChild(child);
        });
        collection.appendChild(wrapper);
        // The manager moves the "+" into the previous fieldset on load: it is
        // brought back here, under the list, and re-parented to the collection
        // for the manager (closest fieldset).
        const attachPlus = function () {
            // The button of this collection is the one whose fieldsetAppend
            // targets it: it was rendered right after the collection, then
            // moved by the manager into the previous fieldset (the collection
            // itself or its parent section).
            const section = collection.closest('fieldset.section') || document;
            const candidates = Array.from(section.querySelectorAll('.config-fieldset-plus'));
            const plus = candidates.find(function (b) {
                return b.closest('.form-fieldset-collection') === collection
                    || (b.parentElement === collection.parentElement && b.previousElementSibling === collection)
                    || b.dataset.collectionId === collection.id;
            });
            if (plus && plus.parentElement !== side) {
                plus.dataset.collectionId = collection.id;
                plus.classList.add('collection-add');
                plus.setAttribute('title', t('add', 'Add'));
                side.appendChild(plus);
            }
        };
        attachPlus();
        window.setTimeout(attachPlus, 0);
        if (window.jQuery) window.jQuery(attachPlus);

        const items = function () {
            return Array.from(main.querySelectorAll(':scope > fieldset'));
        };

        const summary = function (fieldset) {
            const label = fieldText(fieldset, 'label');
            const field = fieldText(fieldset, 'field') || fieldText(fieldset, 'name');
            const type = fieldText(fieldset, 'type');
            const parts = [];
            if (label) parts.push('<span class="collection-item-label">' + escapeHtml(label) + '</span>');
            if (field && field !== label) parts.push('<span class="collection-item-field">' + escapeHtml(field) + '</span>');
            if (type) parts.push('<span class="collection-item-type">' + escapeHtml(type) + '</span>');
            return parts.length
                ? parts.join(' ')
                : '<span class="collection-item-new">' + escapeHtml(collection.dataset.labelNew || t('newItem', 'New item')) + '</span>';
        };

        const select = function (fieldset) {
            items().forEach(function (f) {
                f.classList.toggle('collection-item-active', f === fieldset);
            });
            Array.from(list.children).forEach(function (li) {
                const active = li.fieldsetRef === fieldset;
                li.classList.toggle('active', active);
                li.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            empty.style.display = fieldset ? 'none' : '';
            if (fieldset) applyTypeDependencies(fieldset);
        };

        // Fold the advanced fields of an item in a details, opened when one of
        // them differs from the defaults of a new item.
        // Consecutive fields flagged with the same data-inline are laid out
        // side by side.
        const groupInline = function (fieldset) {
            let group = null;
            let key = null;
            Array.from(fieldset.querySelectorAll(':scope > .field')).forEach(function (field) {
                const control = field.querySelector('[data-inline]');
                const current = control ? control.dataset.inline : null;
                if (current && current === key) {
                    group.appendChild(field);
                    return;
                }
                if (current) {
                    group = document.createElement('div');
                    group.className = 'collection-inline';
                    field.parentNode.insertBefore(group, field);
                    group.appendChild(field);
                }
                key = current;
            });
        };

        const isCommon = function (field, usesFlags) {
            const control = field.querySelector('input[name], select[name], textarea[name]');
            if (!control) return true;
            if (usesFlags) return !!field.querySelector('[data-common]');
            return common.indexOf(shortName(control.name)) !== -1;
        };

        const foldAdvanced = function (fieldset) {
            if (fieldset.dataset.folded) return;
            const usesFlags = !!fieldset.querySelector('[data-common]');
            if (!common.length && !usesFlags) return;
            fieldset.dataset.folded = '1';
            groupInline(fieldset);
            const fields = Array.from(fieldset.querySelectorAll(':scope > .field, :scope > .collection-inline'));
            const advanced = fields.filter(function (field) { return !isCommon(field, usesFlags); });
            if (!advanced.length) return;
            let hasValue = false;
            advanced.forEach(function (field) {
                const control = field.querySelector('input[name], select[name], textarea[name]');
                if (!control) return;
                const name = shortName(control.name);
                // The name is always derived from the field.
                if (name === 'name') return;
                const current = fieldValue(field);
                const byDefault = defaults[name];
                if (byDefault === undefined ? current !== '' : current !== byDefault) hasValue = true;
            });
            const details = document.createElement('details');
            details.className = 'collection-advanced';
            details.open = hasValue;
            const summaryEl = document.createElement('summary');
            summaryEl.textContent = t('advanced', 'Advanced settings');
            details.appendChild(summaryEl);
            // The fields are grouped by section, titled, in the order of the
            // first field of each section.
            const sectionOf = function (field) {
                const control = field.querySelector('[data-advanced-section]');
                return control ? control.dataset.advancedSection : '';
            };
            const done = [];
            advanced.forEach(function (field) {
                const section = sectionOf(field);
                if (section && done.indexOf(section) === -1) {
                    done.push(section);
                    const heading = document.createElement('div');
                    heading.className = 'collection-advanced-section';
                    heading.textContent = section;
                    details.appendChild(heading);
                    advanced.forEach(function (other) {
                        if (sectionOf(other) === section) details.appendChild(other);
                    });
                } else if (!section) {
                    details.appendChild(field);
                }
            });
            fieldset.appendChild(details);
        };

        const move = function (fieldset, delta) {
            const all = items();
            const index = all.indexOf(fieldset);
            const target = index + delta;
            if (target < 0 || target >= all.length) return;
            if (delta < 0) {
                main.insertBefore(fieldset, all[target]);
            } else {
                main.insertBefore(all[target], fieldset);
            }
            // A reordering is an insertion for the observer below, that would
            // select the moved neighbour as if the manager added an item. Drop
            // these records: the list is rebuilt here.
            observer.takeRecords();
            selected = fieldset;
            rebuild();
        };

        const remove = function (fieldset) {
            fieldset.remove();
            rebuild();
        };

        let selected = null;
        const rebuild = function () {
            const all = items();
            // The focus is read before the list is emptied: once the focused
            // element is removed, the active element is already the body.
            const active = document.activeElement;
            const activeItem = active && list.contains(active) ? active.closest('li') : null;
            const activeControl = activeItem
                ? (active.classList.contains('collection-item-up') ? 'up'
                    : (active.classList.contains('collection-item-down') ? 'down' : null))
                : null;
            list.innerHTML = '';
            all.forEach(function (fieldset, i) {
                foldAdvanced(fieldset);
                const li = document.createElement('li');
                li.fieldsetRef = fieldset;
                li.draggable = true;
                li.setAttribute('role', 'option');
                li.tabIndex = 0;
                // The list is a listbox, where the bare arrows belong to the
                // selection, so the move takes a modifier. Announce it: the
                // shortcut is not discoverable otherwise.
                li.setAttribute('aria-keyshortcuts', 'Alt+ArrowUp Alt+ArrowDown');
                li.innerHTML = '<span class="collection-item-handle sortable-handle" aria-hidden="true" title="' + escapeHtml(t('drag', 'Drag to reorder')) + '"></span>'
                    + '<span class="collection-item-moves">'
                    + '<button type="button" class="collection-item-up o-icon- fas fa-caret-up" aria-label="' + escapeHtml(t('up', 'Move up')) + '" title="' + escapeHtml(t('up', 'Move up')) + '"' + (i === 0 ? ' disabled' : '') + '></button>'
                    + '<button type="button" class="collection-item-down o-icon- fas fa-caret-down" aria-label="' + escapeHtml(t('down', 'Move down')) + '" title="' + escapeHtml(t('down', 'Move down')) + '"' + (i === all.length - 1 ? ' disabled' : '') + '></button>'
                    + '</span>'
                    + '<span class="collection-item-summary">' + summary(fieldset) + '</span>'
                    + '<button type="button" class="collection-item-remove o-icon-delete" aria-label="' + escapeHtml(t('remove', 'Remove')) + '" title="' + escapeHtml(t('remove', 'Remove')) + '"></button>';
                li.addEventListener('click', function (e) {
                    if (e.target.closest('button')) return;
                    selected = fieldset;
                    select(fieldset);
                });
                li.addEventListener('keydown', function (e) {
                    if (e.target !== li) return;
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        selected = fieldset;
                        select(fieldset);
                    } else if (e.key === 'ArrowUp' && e.altKey) {
                        e.preventDefault();
                        move(fieldset, -1);
                    } else if (e.key === 'ArrowDown' && e.altKey) {
                        e.preventDefault();
                        move(fieldset, 1);
                    }
                });
                li.querySelector('.collection-item-up').addEventListener('click', function () { move(fieldset, -1); });
                li.querySelector('.collection-item-down').addEventListener('click', function () { move(fieldset, 1); });
                li.querySelector('.collection-item-remove').addEventListener('click', function () { remove(fieldset); });
                list.appendChild(li);
            });
            if (!selected || all.indexOf(selected) === -1) {
                selected = all[0] || null;
            }
            select(selected);
            // Focus follows the moved item, on the same button when it was
            // used, else on the item itself. A button disabled by the move
            // hands the focus to the other one rather than to the body.
            const activeLi = list.querySelector('li.active');
            if (activeLi && activeItem) {
                let target = activeLi;
                if (activeControl) {
                    const button = activeLi.querySelector('.collection-item-' + activeControl);
                    const other = activeLi.querySelector('.collection-item-' + (activeControl === 'up' ? 'down' : 'up'));
                    target = button && !button.disabled ? button : (other || activeLi);
                }
                target.focus();
            }
        };

        // Live summary and type dependencies on edit; on a field change with
        // an empty type, a plausible type is preselected.
        const onEdit = function (e) {
            const fieldset = e.target.closest('.collection-main > fieldset');
            if (!fieldset) return;
            if (e.type === 'change' && /\[field\]$/.test(e.target.name || '')) {
                const typeControl = fieldset.querySelector('select[name$="[type]"]');
                if (typeControl && (typeControl.value === '' || e.target.value === 'advanced')) {
                    const guess = defaultTypeForField(e.target.value);
                    if (guess && typeControl.querySelector('option[value="' + guess + '"]')) {
                        typeControl.value = guess;
                        if (window.jQuery) window.jQuery(typeControl).trigger('chosen:updated');
                    }
                }
            }
            rebuild();
        };
        main.addEventListener('input', onEdit);
        main.addEventListener('change', onEdit);
        // Chosen and the other jQuery widgets trigger a jQuery "change", not a
        // native event: listen to it too.
        if (window.jQuery) {
            window.jQuery(main).on('change', 'select, input, textarea', function (e) {
                if (e.originalEvent) return;
                onEdit({type: 'change', target: e.target});
            });
        }

        // Drag and drop reorder: the fieldsets follow the list.
        let dragged = null;
        list.addEventListener('dragstart', function (e) {
            dragged = e.target.closest('li');
            if (dragged) e.dataTransfer.effectAllowed = 'move';
        });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            const over = e.target.closest('li');
            if (!over || !dragged || over === dragged) return;
            const rect = over.getBoundingClientRect();
            list.insertBefore(dragged, (e.clientY - rect.top) < rect.height / 2 ? over : over.nextSibling);
        });
        list.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!dragged) return;
            Array.from(list.children).forEach(function (li) {
                main.appendChild(li.fieldsetRef);
            });
            dragged = null;
            rebuild();
        });

        // The manager appends a new item to the collection itself: move it
        // into the main area and select it; a removal refreshes the list.
        const observer = new MutationObserver(function (mutations) {
            let added = null;
            let structural = false;
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (n) {
                    if (n.nodeType === 1 && n.tagName === 'FIELDSET') { added = n; structural = true; }
                });
                m.removedNodes.forEach(function (n) {
                    if (n.nodeType === 1 && n.tagName === 'FIELDSET') structural = true;
                });
            });
            if (structural) {
                if (added && !added.dataset.folded) selected = added;
                rebuild();
            }
        });
        observer.observe(main, {childList: true});
        const mover = new MutationObserver(function () {
            Array.from(collection.querySelectorAll(':scope > fieldset')).forEach(function (n) {
                main.appendChild(n);
            });
            // The manager moves the "+" back at the end of the collection.
            attachPlus();
        });
        mover.observe(collection, {childList: true});

        rebuild();
    };

    // The tab of a collection gets two sub tabs: the general settings of the
    // section and the list of the items.
    const initSubTabs = function (section) {
        const collection = section.querySelector('.form-fieldset-collection');
        if (!collection || section.dataset.subTabsReady) return;
        section.dataset.subTabsReady = '1';
        // The general settings are every direct child before the collection,
        // except the legend; the collection and its "+" go to the list.
        const general = document.createElement('div');
        general.className = 'collection-subtab collection-subtab-general';
        const listPart = document.createElement('div');
        listPart.className = 'collection-subtab collection-subtab-list';
        const legend = section.querySelector(':scope > legend');
        Array.from(section.children).forEach(function (child) {
            if (child === legend) return;
            const isCollectionPart = child === collection
                || child.classList.contains('config-fieldset-plus');
            (isCollectionPart ? listPart : general).appendChild(child);
        });
        // The legend of the collection duplicates the tab: useless in the list.
        const collectionLegend = collection.querySelector(':scope > legend');
        if (collectionLegend) collectionLegend.style.display = 'none';
        const listLabel = collectionLegend && collectionLegend.textContent.trim()
            ? collectionLegend.textContent.trim()
            : t('list', 'List');
        // A "+" caught in the general part (moved there by the manager) goes
        // back under its list.
        general.querySelectorAll('.config-fieldset-plus').forEach(function (plus) {
            const side = collection.querySelector('.collection-side');
            if (side) {
                plus.dataset.collectionId = collection.id;
                plus.classList.add('collection-add');
                side.appendChild(plus);
            }
        });
        const nav = document.createElement('nav');
        nav.className = 'section-nav collection-subnav';
        const ul = document.createElement('ul');
        const mk = function (label, target, active) {
            const li = document.createElement('li');
            if (active) li.className = 'active';
            const a = document.createElement('a');
            a.href = '#';
            a.textContent = label;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                ul.querySelectorAll('li').forEach(function (x) { x.classList.remove('active'); });
                li.classList.add('active');
                general.style.display = target === general ? '' : 'none';
                listPart.style.display = target === listPart ? '' : 'none';
            });
            li.appendChild(a);
            ul.appendChild(li);
            return li;
        };
        mk(t('general', 'General'), general, false);
        const liList = mk(listLabel, listPart, true);
        nav.appendChild(ul);
        section.insertBefore(nav, legend ? legend.nextSibling : section.firstChild);
        section.appendChild(general);
        section.appendChild(listPart);
        // The list first: it is the common case.
        general.style.display = 'none';
        // Omeka clears the active tab of every section nav when a main tab is
        // opened: restore the current sub tab.
        if (window.jQuery) {
            window.jQuery(section).on('o:section-opened', function () {
                if (ul.querySelector('li.active')) return;
                const current = general.style.display === 'none' ? liList : ul.firstElementChild;
                current.classList.add('active');
            });
        }
    };

    const init = function () {
        const form = document.getElementById('search-config-configure-form');
        if (!form) return;
        form.querySelectorAll('.form-fieldset-collection').forEach(initCollection);
        form.querySelectorAll('fieldset.section').forEach(initSubTabs);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
