'use strict';

/**
 * Open the advanced search form in a dialog, loading it on demand.
 *
 * The form of a search page may be very large, so it is not rendered in each
 * page of the site: it is fetched at the first click, then kept in memory.
 *
 * Without javascript, the link stays a normal link to the search page, so the
 * filters remain reachable in all cases.
 *
 * The dialog of Common uses a native "<dialog>", promoted in the top layer: it
 * is not affected by the transforms, overflows and z-index of the header of the
 * theme, in particular when it is animated with headroom.js.
 *
 * @see CommonDialog.dialogGeneric()
 */
(function () {
    var cache = {};

    var refAttributes = ['for', 'form', 'list', 'aria-labelledby', 'aria-controls', 'aria-describedby'];

    /**
     * Suspend the ids of the quick search while the dialog is open.
     *
     * The dialog contains the same form, so the same ids: a button moved
     * outside of the form and bound to it with the attribute "form" would be
     * attached to the first matching form of the document, that is the quick
     * search, whose submit has no action. The quick search is inert behind the
     * modal anyway, so its ids are suspended and restored on close, and the
     * dialog keeps the canonical ids expected by advanced-search-form.js.
     */
    function suspendDuplicatedIds(dialog) {
        var inDialog = {};
        dialog.querySelectorAll('[id]').forEach(function (el) {
            inDialog[el.id] = true;
        });

        var restore = [];
        document.querySelectorAll('[id]').forEach(function (el) {
            if (dialog.contains(el) || !inDialog[el.id]) {
                return;
            }
            var previous = el.id;
            var suspended = previous + '-behind-dialog';
            restore.push(function () {
                el.id = previous;
            });
            // Keep the references of the suspended element consistent.
            refAttributes.forEach(function (attribute) {
                document.querySelectorAll('[' + attribute + '="' + CSS.escape(previous) + '"]').forEach(function (ref) {
                    if (dialog.contains(ref)) {
                        return;
                    }
                    restore.push(function () {
                        ref.setAttribute(attribute, previous);
                    });
                    ref.setAttribute(attribute, suspended);
                });
            });
            el.id = suspended;
        });

        return function () {
            restore.forEach(function (fn) {
                fn();
            });
        };
    }

    /**
     * Init the form loaded in the dialog.
     *
     * The form is inserted after the load of the page, so it is not handled by
     * the init of search.js, that runs on the ready event: the selects would
     * stay standard ones and the buttons to add or remove a filter would do
     * nothing, since they need the controller stored on the fieldset.
     */
    function initForm(dialog) {
        if (typeof Search === 'undefined') {
            return;
        }
        if (Search.initChosen) {
            Search.initChosen(dialog);
        }
        if (Search.initFiltersAdvanced) {
            Search.initFiltersAdvanced(dialog);
        }
    }

    function openDialog(html, heading) {
        CommonDialog.dialogGeneric({
            heading: heading,
            body: html,
            // The form has its own submit button and the dialog is closed with
            // the header button or the Escape key.
            textOk: null,
            textCancel: null,
        });

        var dialog = document.querySelector('dialog.dialog-generic');
        if (!dialog) {
            return;
        }

        var restore = suspendDuplicatedIds(dialog);
        dialog.addEventListener('close', restore, {once: true});

        initForm(dialog);

        var field = dialog.querySelector('input[type="text"], input[type="search"]');
        if (field) {
            field.focus();
        }
    }

    document.addEventListener('click', function (ev) {
        var link = ev.target.closest('a.advanced-search-link[data-search-form-url]');
        if (!link || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) {
            return;
        }

        // Let the browser follow the link when the dialog is unavailable.
        if (typeof CommonDialog === 'undefined' || !CommonDialog.dialogGeneric) {
            return;
        }

        ev.preventDefault();

        var url = link.dataset.searchFormUrl;
        var heading = link.dataset.dialogHeading || link.textContent.trim();

        if (cache[url] !== undefined) {
            openDialog(cache[url], heading);
            return;
        }

        link.setAttribute('aria-busy', 'true');

        fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(response.status);
                }
                return response.text();
            })
            .then(function (html) {
                cache[url] = html;
                openDialog(html, heading);
            })
            .catch(function () {
                // The search page always contains the form: use it as fallback.
                window.location.assign(link.href);
            })
            .finally(function () {
                link.removeAttribute('aria-busy');
            });
    });
})();
