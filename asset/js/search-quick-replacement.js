'use strict';

/**
 * Move the link to the advanced search of the theme to the search page.
 *
 * When the quick search form is replaced by the form of the module, the theme
 * still displays its own link to the advanced search of the core, so the page
 * would contain two links. So the link of the theme is updated with the url of
 * the search page and the one appended by the module is removed.
 */
(function () {
    function upgradeThemeLinks() {
        var moduleLink = document.querySelector('a.advanced-search-link[data-quick-replacement]');
        if (!moduleLink) {
            return;
        }

        var themeLinks = [].filter.call(
            document.querySelectorAll('a[href*="/item/search"]'),
            function (link) {
                return link !== moduleLink && !link.closest('#content');
            }
        );
        if (!themeLinks.length) {
            return;
        }

        themeLinks.forEach(function (link) {
            link.href = moduleLink.href;
            link.classList.add('advanced-search-link');
            if (moduleLink.dataset.searchFormUrl) {
                link.dataset.searchFormUrl = moduleLink.dataset.searchFormUrl;
            }
            if (moduleLink.dataset.dialogHeading) {
                link.dataset.dialogHeading = moduleLink.dataset.dialogHeading;
            }
        });

        moduleLink.remove();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', upgradeThemeLinks);
    } else {
        upgradeThemeLinks();
    }
})();
