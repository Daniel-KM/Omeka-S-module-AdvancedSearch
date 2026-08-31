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
                // The "+" stays above the preview button.
                side.insertBefore(plus, side.querySelector('.collection-preview-all'));
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
            const typeControl = fieldset.querySelector('select[name$="[type]"]');
            const type = typeControl ? typeControl.value : '';
            const typeLabel = fieldText(fieldset, 'type');
            const parts = [];
            if (type) parts.push('<span class="collection-item-type input-type input-type-' + escapeHtml(type.toLowerCase()) + '" title="' + escapeHtml(typeLabel) + '" aria-label="' + escapeHtml(typeLabel) + '"></span>');
            if (label) parts.push('<span class="collection-item-label">' + escapeHtml(label) + '</span>');
            if (field && field !== label) parts.push('<span class="collection-item-field">' + escapeHtml(field) + '</span>');
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
            advanced.forEach(function (field) {
                if (!sectionOf(field)) details.appendChild(field);
            });
            orderedSections(advanced, sectionOf).forEach(function (sectionName) {
                const heading = document.createElement('div');
                heading.className = 'collection-advanced-section';
                heading.textContent = sectionName;
                details.appendChild(heading);
                advanced.forEach(function (field) {
                    if (sectionOf(field) === sectionName) details.appendChild(field);
                });
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

    // Consecutive fields flagged with the same data-inline are laid out side
    // by side (shared with the general panels).
    const groupInlineFields = function (container) {
        let group = null;
        let key = null;
        Array.from(container.querySelectorAll(':scope > .field')).forEach(function (field) {
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

    // The names of the sections in their order of appearance, with the
    // technical section always last.
    const orderedSections = function (parts, sectionOf) {
        const names = [];
        parts.forEach(function (part) {
            const name = sectionOf(part);
            if (name && names.indexOf(name) === -1) names.push(name);
        });
        const technical = t('technical', 'Technical');
        const index = names.indexOf(technical);
        if (index !== -1) {
            names.splice(index, 1);
            names.push(technical);
        }
        return names;
    };

    // A field flagged data-show-if="id" is displayed only when the checkbox
    // of this id is checked.
    const applyShowIf = function (root) {
        root.querySelectorAll('[data-show-if]').forEach(function (control) {
            const field = control.closest('.field');
            const master = document.getElementById(control.dataset.showIf);
            if (!field || !master) return;
            const apply = function () { field.style.display = master.checked ? '' : 'none'; };
            if (!master.dataset.showIfReady) {
                master.dataset.showIfReady = '1';
                master.addEventListener('change', apply);
            }
            apply();
        });
    };

    // Fold the fields of a general panel: the fields flagged data-common stay
    // visible, the others move into a closed details, grouped by section.
    const foldGeneral = function (container) {
        if (container.dataset.folded || !container.querySelector('[data-common]')) return;
        container.dataset.folded = '1';
        groupInlineFields(container);
        const parts = Array.from(container.querySelectorAll(':scope > .field, :scope > .collection-inline'))
            .filter(function (part) {
                // A leftover empty wrapper (moved button) is hidden.
                if (!part.querySelector('input, select, textarea, button')) {
                    part.style.display = 'none';
                    return false;
                }
                return true;
            });
        const advanced = parts.filter(function (part) { return !part.querySelector('[data-common]'); });
        if (!advanced.length) return;
        const details = document.createElement('details');
        details.className = 'collection-advanced';
        const summaryEl = document.createElement('summary');
        summaryEl.textContent = t('advanced', 'Advanced settings');
        details.appendChild(summaryEl);
        const sectionOf = function (part) {
            const control = part.querySelector('[data-advanced-section]');
            return control ? control.dataset.advancedSection : '';
        };
        advanced.forEach(function (part) {
            if (!sectionOf(part)) details.appendChild(part);
        });
        orderedSections(advanced, sectionOf).forEach(function (sectionName) {
            const heading = document.createElement('div');
            heading.className = 'collection-advanced-section';
            heading.textContent = sectionName;
            details.appendChild(heading);
            advanced.forEach(function (part) {
                if (sectionOf(part) === sectionName) details.appendChild(part);
            });
        });
        container.appendChild(details);
        applyShowIf(container);
    };

    // The tab of a collection gets two sub tabs: the general settings of the
    // section and the list of the items.
    const initSubTabs = function (section) {
        const collection = section.querySelector('.form-fieldset-collection');
        // Without collection, sub tabs are created only when a field asks for
        // a specific one.
        if (section.dataset.subTabsReady || (!collection && !section.querySelector('[data-subtab]'))) return;
        section.dataset.subTabsReady = '1';
        // The fields are spread between sub tabs: the collection and its "+"
        // go to the list, a field flagged data-subtab goes to this sub tab,
        // the others to the general one.
        const panels = {
            general: {label: t('general', 'General'), element: null},
            header: {label: t('headerFooter', 'Header and footer'), element: null},
            card: {label: t('resourceCard', 'Resource card'), element: null},
        };
        const panelOf = function (key) {
            if (!panels[key]) key = 'general';
            if (!panels[key].element) {
                const div = document.createElement('div');
                div.className = 'collection-subtab collection-subtab-' + key;
                panels[key].element = div;
            }
            return panels[key].element;
        };
        const listPart = document.createElement('div');
        listPart.className = 'collection-subtab collection-subtab-list';
        const legend = section.querySelector(':scope > legend');
        Array.from(section.children).forEach(function (child) {
            if (child === legend) return;
            const isCollectionPart = child === collection
                || child.classList.contains('config-fieldset-plus');
            if (isCollectionPart) {
                listPart.appendChild(child);
                return;
            }
            const control = child.querySelector ? child.querySelector('[data-subtab]') : null;
            panelOf(control ? control.dataset.subtab : 'general').appendChild(child);
        });
        const general = panelOf('general');
        // The legend of the collection duplicates the tab: useless in the list.
        const collectionLegend = collection ? collection.querySelector(':scope > legend') : null;
        if (collectionLegend) collectionLegend.style.display = 'none';
        const listLabel = collectionLegend && collectionLegend.textContent.trim()
            ? collectionLegend.textContent.trim()
            : t('list', 'List');
        // A "+" caught in a panel (moved there by the manager) goes back
        // under its list.
        general.querySelectorAll('.config-fieldset-plus').forEach(function (plus) {
            const side = collection ? collection.querySelector('.collection-side') : null;
            if (side) {
                plus.dataset.collectionId = collection.id;
                plus.classList.add('collection-add');
                side.appendChild(plus);
            }
        });
        const nav = document.createElement('nav');
        nav.className = 'section-nav collection-subnav';
        const ul = document.createElement('ul');
        const allPanels = [];
        const mk = function (label, target, active) {
            const li = document.createElement('li');
            if (active) li.className = 'active';
            const a = document.createElement('a');
            // No href: the global handler of the admin binds the anchors of
            // the section navs with a href.
            a.tabIndex = 0;
            a.setAttribute('role', 'tab');
            a.textContent = label;
            a.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); a.click(); }
            });
            a.addEventListener('click', function (e) {
                e.preventDefault();
                ul.querySelectorAll('li').forEach(function (x) { x.classList.remove('active'); });
                li.classList.add('active');
                allPanels.forEach(function (panel) {
                    panel.style.display = panel === target ? '' : 'none';
                });
            });
            li.appendChild(a);
            ul.appendChild(li);
            return li;
        };
        // A panel is displayed only when it holds a visible field.
        Object.keys(panels).forEach(function (key) {
            const panel = panels[key].element;
            if (!panel || !panel.querySelector('input, select, textarea')) return;
            allPanels.push(panel);
            mk(panels[key].label, panel, false);
        });
        let liList = null;
        if (collection) {
            allPanels.push(listPart);
            liList = mk(listLabel, listPart, true);
        } else if (ul.firstElementChild) {
            ul.firstElementChild.classList.add('active');
            const first = allPanels[0];
            allPanels.forEach(function (panel) { panel.style.display = panel === first ? '' : 'none'; });
        }
        nav.appendChild(ul);
        section.insertBefore(nav, legend ? legend.nextSibling : section.firstChild);
        allPanels.forEach(function (panel) {
            section.appendChild(panel);
            if (panel !== listPart) {
                // The settings are folded like the items: common fields
                // visible, the others in sections of a details.
                foldGeneral(panel);
                if (collection) panel.style.display = 'none';
            }
        });
        // A preview of the page of results, next to the save button.
        if (section.id === 'results' && window.AdvancedSearchInputPreview) {
            const pageActions = document.getElementById('page-actions');
            if (pageActions && !pageActions.querySelector('.collection-preview-results')) {
                const previewButton = document.createElement('button');
                previewButton.type = 'button';
                previewButton.className = 'button collection-preview-results';
                previewButton.textContent = t('previewResults', 'Preview the page of results');
                previewButton.addEventListener('click', function () {
                    window.AdvancedSearchInputPreview.showResultsPage(previewButton.textContent);
                });
                pageActions.insertBefore(previewButton, pageActions.firstChild);
            }
        }
        // Omeka clears the active tab of every section nav when a main tab is
        // opened: restore the current sub tab.
        if (window.jQuery) {
            window.jQuery(section).on('o:section-opened', function () {
                if (ul.querySelector('li.active')) return;
                const items = Array.from(ul.children);
                const current = items[allPanels.findIndex(function (panel) { return panel.style.display !== 'none'; })] || liList || items[0];
                if (current) current.classList.add('active');
            });
        }
    };

    // A grouped section holds several fieldsets displayed as sub tabs.
    const initGroupSection = function (section) {
        const parts = Array.from(section.querySelectorAll(':scope > fieldset.group-section'));
        if (parts.length < 2 || section.dataset.groupReady) return;
        section.dataset.groupReady = '1';
        const nav = document.createElement('nav');
        nav.className = 'section-nav collection-subnav';
        const ul = document.createElement('ul');
        nav.appendChild(ul);
        parts.forEach(function (part, i) {
            const legend = part.querySelector(':scope > legend');
            if (legend) legend.style.display = 'none';
            // The fields of the part may be folded by sections too.
            foldGeneral(part);
            const li = document.createElement('li');
            if (!i) li.className = 'active';
            const a = document.createElement('a');
            a.tabIndex = 0;
            a.setAttribute('role', 'tab');
            a.textContent = part.dataset.groupLabel || (legend ? legend.textContent.trim() : '');
            a.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); a.click(); }
            });
            a.addEventListener('click', function (e) {
                e.preventDefault();
                ul.querySelectorAll('li').forEach(function (x) { x.classList.remove('active'); });
                li.classList.add('active');
                parts.forEach(function (other) {
                    other.style.display = other === part ? '' : 'none';
                });
            });
            li.appendChild(a);
            ul.appendChild(li);
            part.style.display = i ? 'none' : '';
        });
        section.insertBefore(nav, section.firstChild);
        // Omeka clears the active tab of every section nav when a main tab
        // is opened: restore the current sub tab.
        if (window.jQuery) {
            window.jQuery(section).on('o:section-opened', function () {
                if (ul.querySelector('li.active')) return;
                const index = parts.findIndex(function (part) { return part.style.display !== 'none'; });
                (ul.children[index === -1 ? 0 : index] || ul.firstElementChild).classList.add('active');
            });
        }
    };

    const init = function () {
        const form = document.getElementById('search-config-configure-form');
        if (!form) return;
        form.querySelectorAll('.form-fieldset-collection').forEach(initCollection);
        form.querySelectorAll('fieldset.section').forEach(initSubTabs);
        form.querySelectorAll('div.section').forEach(initGroupSection);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
