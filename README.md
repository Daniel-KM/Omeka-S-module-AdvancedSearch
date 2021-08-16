Advanced Search (module for Omeka S)
====================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Advanced Search] is a module for [Omeka S] that adds search capabilities to the
public interface of Omeka S, in particular search autocompletion, filters and
facets. Furthermore, it provides a common interface for other modules to extend
it (forms, indexers, queriers). It can be displayed as a block on any page too.
Furthermore, it adds some features to the standard advanced search form.

Here is a live example:

![example of search page](data/images/advancedsearch_config.png)

It can be extended in two ways:

- Forms that will build the search form and construct the query.
- Adapters that will do the real work (indexing and querying).

Two forms are provided by default.
- The basic form is the simple field _à la_ Google: it contains one single main
  search field without filters, that is enough in most of the cases for the end
  users, especially because the results allow facets.
- The advanced form is the full form that is used in Omeka advanced search, with
  a full customization from the admin interface: with or without facets, sort
  fields, collection selector, resource class selector, resource template
  selector, and properties filters.
- An advanced example of a full form is [Psl Search Form], that displays the
  same fields, plus a range of dates and map locations. Note: some features of
  this advanced form are not managed by the internal adapter currently, in
  particular the queries on a range of dates.

An internal adapter is provided too. It uses the internal Api of Omeka to search
resources. So the search engine is the sql one, without indexer, so it is
limited strictly to the request like the standard Omeka S search engine (no
wildcards, no management of singular/plural, etc.). Nevertheless, it provides
the facets to improve the results (requires the module [Reference]).

An adapter is available for [Solr], one of the most used search engine.

For the standard advanced form, it adds some fields to the advanced search form
to make search more precise.

Added fields are:

- before/on/after creation/modification date/time of any resource
- has media (for item)
- has original
- has thumbnail
- multiple media types (for item)
- multiple media types for media (included in core since Omeka S 2.0.2 for a
  single value)
- visibility public/private
- media by item set

Moreover, it adds new search query type for properties:

- start with
- end with
- in list (via api only).
- exclude one or multiple properties (except title)

Finally, an option allows to display only the used properties and classes in the
advanced search form, with chosen-select.


Installation
------------

The module uses an external library [jQuery-Autocomplete], so use the release
zip to install it, or use and init the source.

* From the zip

Download the last release [AdvancedSearch.zip] from the list of releases (the
master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development:

If the module was installed from the source, rename the name of the folder of
the module to `AdvancedSearch`, and go to the root module, and run:

```sh
composer install --no-dev
```

See general end user documentation for [Installing a module].

### Optional dependencies

- Module [Reference] to display facets in the results with the internal adapter.
  It is not needed for external search engines. It should be at least version
  3.4.16.
- Module [Search Solr]. Note that this is not the module [Solr of Biblibre],
  that is named "Solr".


Quick start
-----------

The default search engine is automatically added to the sites.

The main admin menu `Search` allows to manage the search indexes and the search
pages: an instance of Omeka can contain multiple indexes, for example to hide
some fields in the public front-end, and multiple pages, for example a single
field search and an advanced search with filters, or different parameters for
different sites or different resource types (items or item sets).

An index and a page for the internal adapter are automatically prepared during
install. This search engine can be enabled in main settings and site settings.
It can be removed too.

To create a page with a search engine, follow these steps.

1. Create an index
    1. Add a new index with name `Internal` or whatever you want, using the
       `Internal` adapter. The index can be set for items and/or item sets.
    2. The internal adapter doesn’t create any index, so you don’t need to
       launch the indexation by clicking on the "reindex" button (two arrows
       forming a circle).
2. Create a page
    1. Add a page named `Internal search` or whatever you want, a path to access
       it, for example `search` or `find`, the index that was created in the
       previous step (`Internal` here), and a form adapter (`Main`) that will do
       the mapping between the form and the index. Forms added by modules can
       manage an advanced input field and/or filters.
    2. In the page configuration, you can enable/disable facet and sort fields
       by drag-drop. The order of the fields will be the one that will be used
       for display. Note that some indexers may have fields that seem
       duplicated, but they aren’t: some of them allow to prepare search indexes
       and some other facets or sort indexes. Some of them may be used for all
       uses. This is not the case for the internal indexer, since there is no
       index.
       For example, you can use `dcterms:type`, `dcterms:subject`,
       `dcterms:creator`, `dcterms:date`, `dcterms:spatial`, `dcterms:language`
       and `dcterms:rights` as facets, and `dcterms:title`, `dcterms:date`, and
       `dcterms:creator` as sort fields.
    3. Edit the name of the label that will be used for facets and sort fields
       in the same page. The string will be automatically translated if it
       exists in Omeka.
    4. There are options for the default search results. If wanted, the query
       may be nothing, all, or anything else. This option applies only for the
       search page, not for blocks.

3. In admin and site settings
    1. To access to the search form, enable it in the main settings (for the
       admin board) and in the site settings (for the front-end sites). So the
       search engine will be available in the specified path: `https://example.com/s/my-site/search`
       or `https://example.com/admin/search` in this example.
    2. You can specify to redirect the item-set page to the search page, as in
       the default Omeka (item-set/show is item/browse).
    3. Optionally, add a custom navigation link to the search page in the
       navigation settings of the site.

The search form should appear. Type some text then submit the form to display
the results as grid or as list. The page can be themed.

**IMPORTANT**

The Search module  does not replace the default search page neither the default
search engine. So the theme should be updated.


Internal engine (mysql)
-----------------------

The module is provided with an adapter for mysql. In order to get all features,
you need two other modules.

- The facets work only if the module [Reference] is enabled.


Standard advanced search form and api
-------------------------------------

### Exclude properties

To exclude properties to search in, use key `except`. For example, to search
anywhere except in "bibo:content", that may contains ocr or full text, use this
api query `https://example.org/api/items?property[0][except]=bibo:content&property[0][type]=in&property[0][text]=text to search`, or in internal api:

```php
$query['property'][] = [
    'joiner' => 'and',
    'property' => '',
    'except' => $excludedFields,
    'type' => 'in',
    'text' => "text to search",
];
```

The excluded fields may be one or multiple property ids or terms.

The title cannot be excluded currently, because it is automatically added by
the core.

### Visibility

The visibility check may not working if the api url contains `&is_public=&`:
`is_public` must not be a empty string. See the patch in https://github.com/omeka/omeka-s/pull/1671.
This patch is integrated in module only for url, and for call to internal api.


Search api
----------

The search engine can be used not only for the classical search page, but for
any other views too where you the search of items need to be done quickly, for
example the block layouts with a big database (more than 10000 to 100000 items,
according to your server and your collections).

To use this feature, a page should be created with the form `Api`. This form is
not a true form, but it allows to map the Omeka metadata and properties with the
fields indexed by the search engine. It allows to define a max number of results
too, that is used when no paginator is enable. The argument `limit` cannot go
further.

When ready, the api search is available via multiple means.
- Add `index=1` as query in the block layouts that use it, like [Browse preview].
- Do a standard search with `$this->api()->search()` with the value `'index' => true`
  appended to the argument `$data` or `$options` (recommended when possible to
  avoid to mix the query and the parameters).
- Do a standard search in the theme with the view helpers `$this->apiSearch()`,
  and `$this->apiSearchOne()`, that have the same arguments than `$this->api()->search()`
  and `$this->api()->searchOne()`. The result is an Omeka Response.
- Use the controller plugins `$this->apiSearch()` and `$this->apiSearchOne()`.
- The main api manager understand these arguments too.
- If the api page form is made available on a site, it will be a quick access to
  the results at `/s/mysite/api_advancedsearch_config`.

Note that some features may be not available in the external search engine. In
particular, some events are not triggered.


Indexation
----------

The indexation of items and item sets is automatic and all new metadata can be
searched in the admin board. Note that there may be a cache somewhere, and they
may be not searchable in the public sites.

So when the item pool of a site or the item sets attached to it are modified, a
manual reindexation should be done in the Search board. This job can be done via
a cron too (see your system cron).

Furthermore, there may be an indexation delay between the moment when a resource
is saved and the moment when it is fully available in the search engine (it may
be some minutes with Solr, according to your configuration).


TODO
----

- [x] The override of a search query with "property" should be called even with
  "initialize = false" in the api.
- [x] Remove distinction between advanced and basic form: they are just a list
  of elements.
- [ ] Simplify the form with https://docs.laminas.dev/laminas-form/v3/form-creation/creation-via-factory/
  and js, storing the whole form one time. See UserProfile too.
- [ ] Normalize the url query with a true standard (or the Omeka S one, or at the
  choice of the admin, or the developer of the forms and queriers).
- [x] Genericize the name of the fields of be able for internal querier to use
  or convert the fields names.
- [ ] Make the search arguments groupable to allow smart facets: always display all
  facets from the original queries, with "or" between facets of the same group,
  and "and" between groups. Require that the core api allows groups.
- [ ] Use the standard view with tabs and property selector for the page creation,
  in order not to limit it to Dublin Core terms. The tabs may be "Filters",
  "Facets", and "Sort".
- [ ] Create an internal index (see Omeka Classic) and move all related code into
  another module (use the fulltext feature).
- [ ] Allow to remove an index without removing pages.
- [ ] Allow to import/export a mapping via json, for example the default one.
- [ ] Add an option to use the search api by default (and an option `'index' => false`).
- [ ] Set one api page by site and a quick set in the pages settings.
- [ ] Update index when item pool of a site change.
- [ ] Genericize and move the value extractor from module Solr to module Search.
- [ ] Improve the check of presence of an item in sites for real time indexation.
- [ ] Updated index in batch, not one by one.
- [ ] Add an option to replace the default Omeka search form.
- [ ] Improve the internal autosuggester to return the list of next words when space.
- [ ] Use a or for facets of each group.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

### Module

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.

### Libraries

- jQuery-Autocomplete : [MIT]


Copyright
---------

See commits for full list of contributors.

* Copyright BibLibre, 2016-2017 (see [BibLibre])
* Copyright Daniel Berthereau, 2017-2021 (see [Daniel-KM])
* Copyright Tomas Kirda 2017 (library jQuery-Autocomplete)

This module is a merge of features from the deprecated modules [Advanced Search Plus]
and [Search].

The Psl search form and the Solr modules were initially built by [BibLibre] and
are used by the [digital library of PSL], a French university. Next improvements
were done for various projects. The auto-completion was build for future digital
library of [Campus Condorcet].


[Advanced Search]: https://github.com/Daniel-KM/Omeka-S-module-AdvancedSearch
[Omeka S]: https://omeka.org/s
[Solr]: https://solr.apache.org/
[Search Solr]: https://gitlab.com/Daniel-KM/Omeka-S-module-SearchSolr
[SearchSolr]: https://gitlab.com/Daniel-KM/Omeka-S-module-SearchSolr
[Installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[this patch]: https://github.com/omeka/omeka-s/pull/1519/files
[jQuery-Autocomplete]: https://github.com/devbridge/jQuery-Autocomplete
[Reference]: https://gitlab.com/Daniel-KM/Omeka-S-module-Reference
[Advanced Search Plus]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearchPlus
[Psl Search Form]: https://github.com/Daniel-KM/Omeka-S-module-PslSearchForm
[Solr of Biblibre]: https://github.com/BibLibre/Omeka-S-module-Solr
[Browse preview]: https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview
[module issues]: https://github.com/BibLibre/Omeka-S-module-AdvancedSearch/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: https://github.com/devbridge/jQuery-Autocomplete/blob/master/license.txt
[Search]: https://github.com/Daniel-KM/Omeka-S-module-Search
[BibLibre]: https://github.com/biblibre
[GitLab]: https://gitlab.com/Daniel-KM
[digital library of PSL]: https://bibnum.explore.univ-psl.fr
[Campus Condorcet]: https://www.campus-condorcet.fr
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
