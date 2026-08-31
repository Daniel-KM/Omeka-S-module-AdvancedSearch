'use strict';

/**
 * Make the groups of the grouped checkboxes collapsible.
 *
 * The element Common\Form\Element\OptionalMultiCheckbox renders the groups as a
 * flat list, where each group starts with a disabled option used as a heading.
 * So the labels of each group are wrapped in a container toggled by its
 * heading, with the count of the checked options.
 *
 * @see \Common\Form\Element\TraitGroupedMultiOptions
 */
(function () {

    const collapse = function (fieldset) {
        const labels = Array.from(fieldset.querySelectorAll(':scope > label'));
        if (!labels.length) {
            return;
        }

        // A heading is the label of the hidden and disabled input of a group.
        const isHeading = (label) => {
            const input = label.querySelector('input');
            return input && input.disabled && input.value === '';
        };
        if (!labels.some(isHeading)) {
            return;
        }

        let group = null;
        labels.forEach(function (label) {
            if (isHeading(label)) {
                group = document.createElement('div');
                group.className = 'grouped-checkboxes-group';
                const heading = document.createElement('button');
                heading.type = 'button';
                heading.className = 'grouped-checkboxes-heading';
                heading.setAttribute('aria-expanded', 'false');
                heading.append(label.textContent.trim());
                const count = document.createElement('span');
                count.className = 'grouped-checkboxes-count';
                heading.append(count);
                const content = document.createElement('div');
                content.className = 'grouped-checkboxes-content';
                content.hidden = true;
                group.append(heading, content);
                label.replaceWith(group);
                heading.addEventListener('click', function () {
                    const expanded = heading.getAttribute('aria-expanded') === 'true';
                    heading.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    content.hidden = expanded;
                });
                return;
            }
            if (group) {
                group.querySelector('.grouped-checkboxes-content').append(label);
            }
        });

        // The count tells what a collapsed group contains.
        fieldset.querySelectorAll('.grouped-checkboxes-group').forEach(function (group) {
            const inputs = group.querySelectorAll('.grouped-checkboxes-content input[type=checkbox]');
            const count = group.querySelector('.grouped-checkboxes-count');
            const update = function () {
                const checked = Array.from(inputs).filter((input) => input.checked).length;
                count.textContent = checked + '/' + inputs.length;
            };
            inputs.forEach((input) => input.addEventListener('change', update));
            update();
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.field .inputs').forEach(collapse);
    });

})();
