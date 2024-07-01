Advanced Search (module for Omeka S)
====================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Advanced Search] is a module for [Omeka S] that improves the standard search
(visibility, thumbnails, starts with, resources without templates, search in
multiple properties at a time, etc.) and that adds search capabilities to the
public interface of Omeka S, in particular auto-completion, filters, facets,
and aggregated fields querying.

These features are progressively integrated in the Omeka core.

Furthermore, it provides a common interface for other modules to extend it
(forms, indexers, queriers). It can be displayed as a block on any page too.
Besides, it adds some features to the standard advanced search form.

Here is a live example:

![example of search page](data/images/search_page.png)

It can be extended in two ways:

- Forms that will build the search form and construct the query.
- Adapters that will do the real work (indexing and querying).

The default form answers to most of the common needs. It can be configured in
the admin interface to make it a basic form _à la_ Google, or to build a complex
form with or without auto-suggestion, advanced filters, sort fields,
facets, collection selector, resource class selector, resource template
selector, properties filters with various input elements, like numbers or date
ranges.

An internal adapter is provided too. It uses the internal sql api of Omeka to
search resources. So the search engine is the sql one, without indexer, so it is
limited strictly to the request like the standard Omeka S search engine (no
wildcards, no management of singular/plural, etc.). Nevertheless, it provides
the facets to improve the results (requires the module [Reference]).

An adapter is available for [Solr], one of the most common search engine.

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

Moreover, it adds new search query operator for properties (some are available
only via api, not in the advanced search form for now):

- Values:
    - `eq`/`neq`: is or is not exactly (default Omeka)
    - `in`/`nin`: contains or does not contains (default Omeka)
    - `sw`/`nsw`: starts or does not start with
    - `ew`/`new`: ends or does not end with
    - `near`/`nnear`: is or is not similar to (algorithm [Soundex], based on British English phonetic)
    - `list`/`nlist`: is or is not in list (api only)
- Resources:
    - `res`/`nres`: has resource or has no resource (default Omeka)
    - `resq`/`nresq`: is or is not resource matching query
- Linked resources:
    - `lex`/`nlex`: is or is not a linked resource
    - `lres`/`nlres`: is or is not linked with resource #id
    - `lkq`/`nlkq`: is or is not linked with resources matching query
- Count:
    - `ex`/`nex`: has any value or has no value (default Omeka)
    - `exs`/`nexs`: has or has not a single value
    - `exm`/`nexm`: has or has not multiple values
- Data Type:
    - `tp`/`ntp`: has or has not main type (literal-like, resource-like, uri-like)
    - `tpl`/`ntpl`: has or has not type literal-like
    - `tpr`/`ntpr`: has or has not type resource-like
    - `tpu`/`ntpu`: has or has not type uri-like
    - `dtp`/`ndtp`: has or has not data type
- Comparisons (api only):
    - `gt`: greater than
    - `gte`: greater than or equal
    - `lte`: lower than or equal
    - `lt`: lower than
- Curation:
    - `dup` and variants: has duplicate values, linked resources, uris, types and languages
      The variants allows to check duplicate for simple values only, linked
      resources only, uris only, including or not types or languages.

__Warning__: With the internal sql engine, comparisons are mysql comparisons, so
alphabetic ones. They works for string and four digit years and standard dates,
not for numbers nor variable dates.

Furthermore:
- the search can exclude one or more properties (except title).
- search in multiple properties at a time, for example `dcterms:creator or dcterms:contributor are equal to value "Anonymous"`.
- search resources without without template, class, item set, site and owner.
  This feature is included directly in the advanced search form in each select.
- adds the joiner type `not` that can be use to invert the query. For example,
  "and property dcterms:title not equals 'search text'" is the same than "not property dcterms:title equals 'search text'".
  It avoids to display half of the complex query types to the user.
- adds the key `datatype` to filter a property query by datatype. For example,
  "and property dcterms:subject equals 'subject' with datatype 'customvocab:1'".
- search no item set, no class, no template, no owner or no site. To search
  missing value, use `0`, for example `item_set_id=0`.
- sort by a list of ids with `sort_by=ids`. The list of ids can be set in keys
  `id` or `sort_ids` as an array or as a comma-separated list.

Finally, an option allows to display only the used properties and classes in the
advanced search form, with chosen-select.


Installation
------------

This module is dependant of module [Common], that should be installed first.

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

See general end user documentation for [installing a module].

### Optional dependencies

- Module [Reference] to display facets in the results with the internal adapter.
  It is not needed for external search engines that can manage facets natively.
  It should be at least version 3.4.16.
- Module [Search Solr]. Note that this is not the module [Solr of Biblibre],
  that is named "Solr".


Quick start
-----------

The default search engine is automatically added to the sites.

The main admin menu `Search` allows to manage the search engines and the search
configs: an instance of Omeka can contain multiple engines, for example to hide
some fields in the public front-end, and multiple configs or pages, for example
a single field search and an advanced search with filters, or different
parameters for different sites or different resource types (items or item sets).

An engine and a page for the internal adapter are automatically prepared
during install. This search engine can be enabled in main settings and site
settings. It can be removed too.

To create a new config for a page with a search engine, follow these steps.

1. Create an engine
    1. Add a new engine with name `Internal` or whatever you want, using the
       `Internal` adapter. The engine can be set for items and/or item sets.
    2. The internal adapter doesn’t create any engine, so you don’t need to
       launch the indexation by clicking on the "reindex" button (two arrows
       forming a circle).
    3. The engine may have specific option that can be filled when needed. For
       internal adapter, you can list the fields that will be managed as a
       single field in the form.

2. Create a config for a page
    1. Add a page named `Internal search` or whatever you want, a path to
       access it, for example `search` or `find`, the engine that was created in
       the previous step (`Internal` here), and a form adapter (`Main`) that
       will do the mapping between the form and the engine. Forms added by
       modules can manage an advanced input field and/or filters.
    2. In the config of the page, you can manage main config of the page, and
       manage filters, sort fields and facets. Their fieldsets include a
       textarea that is a simple list of the fields you want, followed by the
       label and options. These textarea are followed by a field "available filters",
       "available sort fields" and "available facets", that can help to get the
       useful available fields in the whole list of fields managed by the search
       engile. The order of the fields will be the one that will be used for
       display. All field will be displayed, even if they are not managed by the
       search engine.
       With the internal adapter, the fields `item_set_id`, `resource_class_id`,
       and `resource_template_id` display a select by default. You may have to
       use `Omeka/Select`, `Omeka/MultiCheckbox`, `Thesaurus`, or variants to
       get option values automatically.
       Note that some indexers may have fields that seem duplicated, but they
       aren’t: some of them allow to prepare search engines and some other
       facets or sort indexes. Some of them may be used for all uses. This is
       not the case for the internal indexer, that is a simpler search engine
       based on the omeka sql database.
       For example, you can use `dcterms:type`, `dcterms:subject`, `dcterms:creator`,
       `dcterms:date`, `dcterms:spatial`, `dcterms:language` and `dcterms:rights`
       as facets, and `dcterms:title`, `dcterms:date`, and `dcterms:creator` as
       sort fields.
    3. There are options for the default search results. If wanted, the query
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


Search configuration
--------------------

A default configuration is provided. It includes all the current features, so
you generally only have to remove the one you don't need or the one useless with
your data.

A search form may have many parameters. They don't need to be all filled.

- Configuration of the search engine
    - main params
    - indexer
    - querier
- Before the query
    - main querier
    - autosuggestion
    - filters
    - advanced search form
- After the query
    - results display
    - pagination
    - sort
    - facets

Some features are complex, so they have their own config form (autosuggestion
for now, and, in a future version, advanced form and facets).

The search engine of a config should not be changed, because the keys may be
different.

### Configuration of the search engine

Currently, two search engines are supported: the default sql and [Solr] through
the module [Search Solr].

### Before the query

#### Autosuggestion

Example of a direct url for Solr (should be configured first): `http://example.com:8983/solr/omeka/suggest?suggest=true&suggest.build=true&suggest.dictionary=mainSuggester&suggest.count=100`.
The query param should be `suggest.q`.

#### Filters

Filters are used before the querying. Any field can be added.
In the text area, each line is a filter, with a name, a label, a type and
options, separated with a `=`.

For advanced filters, similar to the Omeka ones, use "advanced" as field name
and type.

### After the query

#### Facets

See options in the config form.
The format to fill each facet is "field = Label" and optionnally the type after
another "=", "Checkbox", "Select" or "SelectRange".

The list of facets can be displayed as checkboxes (default: `Checkbox`), a select
with multiple values `Select` or a double select for ranges `SelectRange`.

Warning: With internal sql engine, `SelectRange` orders values alphabetically,
so it is used for string, years or standard dates, but not for number or
variable dates. With Solr, `SelectRange` works only with date and numbers.


Internal engine (mysql)
-----------------------

The module is provided with an adapter for mysql. In order to get facets working,
you need the module [Reference].


Standard advanced search form and api
-------------------------------------

The fields that are added to the advanced search form are available in the api
and some other ones are available too.

### Api

- `datetime`, that is a list of arrays with keys `field` ("created" or "modified"),
  `joiner` ("and" or "or), a `type` ("lt", "lte", "eq", "gte", "gt", "neq", "ex", "nex")
  and a value ("2021-08-23 12:34:56"), partial or not.
- `resource_class_term`, the term can be a single class term or a list. It is case sensitive for now.
- `has_media` for items.
- `has_original` for items and medias.
- `has_thumbnails` for items and medias.
- `item_set_id` for medias.
- `media_types` for items.
- to search resource without template, class, item set, site and owner, search
  for the id `0`, for example, in a url, `resource_template_id=0`.

### Exclude properties

To exclude properties to search in, use key `except`. For example, to search
anywhere except in "bibo:content", that may contain ocr or full text, use this
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

This fix has been integrated in Omeka v3.1.


Search api
----------

The search engine can be used not only for the classical search page, but for
any other views too where you the search of items need to be done quickly, for
example the block layouts with a big database (more than 10000 to 100000 items,
according to your server and your collections).

To use this feature, a config should be created with the form `Api`. This form
is not a true form, but it allows to map the Omeka metadata and properties with
the fields indexed by the search engine. It allows to define a max number of
results too, that is used when no paginator is enable. The argument `limit`
cannot go further.

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
- If the api config is made available on a site, it will be a quick access to
  the results at `/s/mysite/api_search_page`.

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

- [ ] Inverse logic in response: fill all results as flat and group them by resource type only if needed.
- [ ] Update to remove features integrated in Omeka S v 3.1 and remove dead fixes for Omeka S beta.
- [x] The override of a search query with "property" should be called even with "initialize = false" in the api.
- [x] Remove distinction between advanced and basic form: they are just a list of elements.
- [ ] Create advanced search form (in particular prepared select) only not used (add an option or argument?).
- [ ] Simplify the form with https://docs.laminas.dev/laminas-form/v3/form-creation/creation-via-factory/ and js, storing the whole form one time. See UserProfile too.
- [ ] Normalize the url query with a true standard: Solr? Omeka S?, at the choice of the admin or the developer of the forms and queriers? Avoid to multiply query formats. Probably replace the custom one by the Solr/Lucene one.
- [x] Genericize the name of the fields of be able for internal querier to use or convert the fields names.
- [ ] Make the search arguments groupable to allow smart facets: always display all facets from the original queries, with "or" between facets of the same group, and "and" between groups. Require that the core api allows groups.
- [ ] Integrate auto-suggestion (or short list) to any field.
- [ ] Use the Laminas config (ini/json/xml) to allow complex form (see User Profile)
- [ ] Use the standard view with tabs and property selector for the page creation, in order not to limit it to Dublin Core terms. The tabs may be "Filters", "Facets", and "Sort".
- [x] Create an internal index (see Omeka Classic) or use the fulltext feature
- [-] Move all code related to Internal (sql) into another module? No.
- [ ] Allow to remove an index without removing pages.
- [ ] Allow to import/export a mapping via json, for example the default one.
- [ ] Add an option to use the search api by default (and an option `'index' => false`).
- [ ] Set one api page by site and a quick set in the pages settings.
- [ ] Update index when item pool of a site change.
- [ ] Genericize and move the value extractor from module SearchSolr to this module.
- [ ] Improve the check of presence of an item in sites for real time indexation.
- [x] Updated index in batch, not one by one.
- [ ] Add an option to replace the default Omeka search form.
- [ ] Improve the internal autosuggester to return the list of next words when space.
- [x] Use a "or" for facets of each group.
- [x] Manage pagination when item set is redirected to search.
- [ ] Reorder items in items set (from module Next, see MvcListeners).
- [ ] Integrate the override in a way a direct call to adapter->buildQuery() can work with advanced property search (see Reference and some other modules).
- [ ] Rename search config "name" by "title" or "label".
- [ ] Add hidden query to site settings.
- [ ] DateRange field (_dr) may not appear in the type of index in mapping.
- [ ] Use omeka selects option values by default for classes, templates, item sets, sites.
- [ ] Factorize and separate config, form and adapter.
- [ ] Create index for Soundex and non-English algorithms.
- [ ] Remove SearchingForm?
- [ ] Restructure form config: separate form and results and allows to mix them, in particular to get multiple form (quick, simple) with same results, or different facets (facets by item sets or main results).
- [ ] Allow to config the names of the form variants: simple, quick, basic, etc.


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
* Copyright Daniel Berthereau, 2017-2024 (see [Daniel-KM])
* Copyright Tomas Kirda 2017 (library jQuery-Autocomplete)

This module is a merge of features from the deprecated modules [Advanced Search Plus],
[Search] and [Psl Search Form] and derivative ones.

The Psl search form and the Solr modules were initially built by [BibLibre] and
were used by the [digital library of PSL], a French university. Next improvements
were done for various projects. The auto-completion was built for the future
digital library of [Campus Condorcet]. The aggregated fields feature was built
for the future digital library [Corpus du Louvre].


[Advanced Search]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch
[Omeka S]: https://omeka.org/s
[Solr]: https://solr.apache.org/
[Search Solr]: https://gitlab.com/Daniel-KM/Omeka-S-module-SearchSolr
[SearchSolr]: https://gitlab.com/Daniel-KM/Omeka-S-module-SearchSolr
[Soundex]: https://en.wikipedia.org/wiki/Soundex
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[this patch]: https://github.com/omeka/omeka-s/pull/1519/files
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[jQuery-Autocomplete]: https://github.com/devbridge/jQuery-Autocomplete
[Reference]: https://gitlab.com/Daniel-KM/Omeka-S-module-Reference
[Advanced Search Plus]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearchPlus
[Psl Search Form]: https://gitlab.com/Daniel-KM/Omeka-S-module-PslSearchForm
[Solr of Biblibre]: https://github.com/BibLibre/Omeka-S-module-Solr
[Browse preview]: https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: https://github.com/devbridge/jQuery-Autocomplete/blob/master/license.txt
[Search]: https://gitlab.com/Daniel-KM/Omeka-S-module-Search
[BibLibre]: https://github.com/biblibre
[GitLab]: https://gitlab.com/Daniel-KM
[digital library of PSL]: https://bibnum.explore.univ-psl.fr
[Campus Condorcet]: https://www.campus-condorcet.fr
[Corpus du Louvre]: https://corpus.louvre.fr
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
