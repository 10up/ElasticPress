# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/).

## [Unreleased]

## [3.6.3] - 2021-09-29

**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

Official PHP support is currently 5.6+. Minimum PHP version for ElasticPress 3.7.0 will be 7.0+.

### Added
- New `ep_facet_widget_term_html` and `ep_facet_widget_term_label` filters to the Facet widget for filtering the HTML and label of individual facet terms. Props [@JakePT](https://github.com/JakePT), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2363](https://github.com/10up/ElasticPress/pull/2363).
- New `ep_set_sort` filter for changing the `sort` clause of the ES query if `orderby` is not set in WP_Query. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2343](https://github.com/10up/ElasticPress/pull/2343) and [#2364](https://github.com/10up/ElasticPress/pull/2364).
- WP-CLI documentation for some commands and parameters. Props [@felipeelia](https://github.com/felipeelia) via [#2369](https://github.com/10up/ElasticPress/pull/2369).

### Changed
- In addition to post titles, now autosuggest also partially matches taxonomy terms. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon) via [#2347](https://github.com/10up/ElasticPress/pull/2347).
- Date parsing change to avoid `E_WARNING`s. Props [@pschoffer](https://github.com/pschoffer) via [#2340](https://github.com/10up/ElasticPress/pull/2340).

### Fixed
- Comments created by anonymous users (but approved by default) are now indexed. Props [@tomjn](https://github.com/tomjn) and [@Rahmon](https://github.com/Rahmon) via [#2357](https://github.com/10up/ElasticPress/pull/2357).
- Deleted terms are now properly removed from the Elasticsearch index. Props [@felipeelia](https://github.com/felipeelia) via [#2366](https://github.com/10up/ElasticPress/pull/2366).
- Children of deleted terms are now properly removed from the Elasticsearch index. Props [@pschoffer](https://github.com/pschoffer) via [#2349](https://github.com/10up/ElasticPress/pull/2349).
- Post tag duplicated in the Elasticsearch query. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2341](https://github.com/10up/ElasticPress/pull/2341).
- Infinite loading state of ElasticPress Related Posts block in the Widgets Edit Screen. Props [@felipeelia](https://github.com/felipeelia) via [#2353](https://github.com/10up/ElasticPress/pull/2353).
- Return of `Search::integrate_search_queries()` when `is_integrated_request`. Props [@adiloztaser](https://github.com/adiloztaser) via [#2355](https://github.com/10up/ElasticPress/pull/2355).
- Mapping determination based on existing info. Props [@felipeelia](https://github.com/felipeelia) via [#2345](https://github.com/10up/ElasticPress/pull/2345).
- `WP_Term_Query` and `parent = 0`. Props [@felipeelia](https://github.com/felipeelia) and [@juansanchezfernandes](https://github.com/juansanchezfernandes) via [#2344](https://github.com/10up/ElasticPress/pull/2344).
- WP Acceptance Tests. Props [@felipeelia](https://github.com/felipeelia) via [#2352](https://github.com/10up/ElasticPress/pull/2352).
- Typos in the output of some WP-CLI Commands. Props [@rebeccahum](https://github.com/rebeccahum) via [#2336](https://github.com/10up/ElasticPress/pull/2336).

### Security
- Bumped `10up-toolkit` from 1.0.11 to 1.0.12, `terser-webpack-plugin` from 5.1.4 to 5.2.4, `@wordpress/api-fetch` from 3.21.5 to 3.23.1, and `@wordpress/i18n` from 3.18.0 to 3.20.0. Props [@felipeelia](https://github.com/felipeelia) via [#2372](https://github.com/10up/ElasticPress/pull/2372).

## [3.6.2] - 2021-08-26

**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version bumps official PHP support from 5.3+ to 5.6+. Minimum PHP version for ElasticPress 3.7.0 will be 7.0+.

### Added
- GitHub Action to test compatibility with different PHP versions. Props [@felipeelia](https://github.com/felipeelia) via [#2303](https://github.com/10up/ElasticPress/pull/2303).
- Validate mapping currently in index against expected version. Props [@tott](https://github.com/tott), [@tlovett1](https://github.com/tlovett1), [@asharirfan](https://github.com/asharirfan), [@oscarssanchez](https://github.com/oscarssanchez), and [@felipeelia](https://github.com/felipeelia) via [#1472](https://github.com/10up/ElasticPress/pull/1472).
- `ep_default_analyzer_filters` filter to adjust default analyzer filters. Props [@pschoffer](https://github.com/pschoffer) and [@felipeelia](https://github.com/felipeelia) via [#2282](https://github.com/10up/ElasticPress/pull/2282).
- `title` and `aria-labels` attributes to each icon hyperlink in the header toolbar. Props [@claytoncollie](https://github.com/claytoncollie) and [@felipeelia](https://github.com/felipeelia) via [#2304](https://github.com/10up/ElasticPress/pull/2304).
- `Utils\is_integrated_request()` function to centralize checks for admin, AJAX, and REST API requests. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@brandwaffle](https://github.com/brandwaffle), [@moritzlang](https://github.com/moritzlang), and [@lkraav](https://github.com/lkraav) via [#2267](https://github.com/10up/ElasticPress/pull/2267).

### Changed
- Use `10up-toolkit` to build assets. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@nicholasio](https://github.com/nicholasio) via [#2279](https://github.com/10up/ElasticPress/pull/2279).
- Official PHP supported version bumped to 5.6. Props [@felipeelia](https://github.com/felipeelia) via [#2320](https://github.com/10up/ElasticPress/pull/2320).
- Lint React rules using `10up/eslint-config/react`. Props [@Rahmon](https://github.com/Rahmon) via [#2306](https://github.com/10up/ElasticPress/pull/2306).
- For ES 7.0+ mappings, change `edgeNGram` to `edge_ngram`. Props [@pschoffer](https://github.com/pschoffer) and [@rinatkhaziev](https://github.com/rinatkhaziev) via [#2315](https://github.com/10up/ElasticPress/pull/2315).

### Removed
- Remove duplicate category_name, cat and tag_id from ES query when tax_query set. Props [@rebeccahum](https://github.com/rebeccahum) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2241](https://github.com/10up/ElasticPress/pull/2241).
- Remove unused `path` from `dynamic_templates`. Props [@pschoffer](https://github.com/pschoffer) via [#2315](https://github.com/10up/ElasticPress/pull/2315).

### Fixed
- Remove data from Elasticsearch on a multisite network when a site is archived, deleted or marked as spam. Props [@dustinrue](https://github.com/dustinrue) and [@felipeelia](https://github.com/felipeelia) via [#2284](https://github.com/10up/ElasticPress/pull/2284).
- `stats` and `status` commands in a multisite scenario. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@dustinrue](https://github.com/dustinrue) via [#2290](https://github.com/10up/ElasticPress/pull/2290).
- Multiple words synonyms. Props [@scooterlord](https://github.com/scooterlord), [@jonasstrandqvist](https://github.com/jonasstrandqvist), and [@felipeelia](https://github.com/felipeelia) via [#2287](https://github.com/10up/ElasticPress/pull/2287).
- Category slug used when doing cat Tax Query with ID. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@karols0](https://github.com/karols0) via [#2322](https://github.com/10up/ElasticPress/pull/2322).
- Restore current blog when the_post triggers outside the loop in multisite environment and the whole network is searched if the first result is from another blog. Props [@gonzomir](https://github.com/gonzomir) and [@felipeelia](https://github.com/felipeelia) via [#2283](https://github.com/10up/ElasticPress/pull/2283).
- Prevents a post from being attempted to delete twice. Props [@pauarge](https://github.com/pauarge) via [#2314](https://github.com/10up/ElasticPress/pull/2314).
- Indexing button on Health screen. Props [@Rahmon](https://github.com/Rahmon) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2312](https://github.com/10up/ElasticPress/pull/2312).
- WP Acceptance tests and Page Crashed errors. Props [@felipeelia](https://github.com/felipeelia) and [@jeffpaul](https://github.com/jeffpaul) via [#2281](https://github.com/10up/ElasticPress/pull/2281) and [#2313](https://github.com/10up/ElasticPress/pull/2313).
- Facets: Children of selected terms ordered by count. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2288](https://github.com/10up/ElasticPress/pull/2288).

### Security
- Bumps `path-parse` from 1.0.6 to 1.0.7. Props [@dependabot](https://github.com/dependabot) via [#2302](https://github.com/10up/ElasticPress/pull/2302).

## [3.6.1] - 2021-07-15

**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version requires a full reindex. The new `facet` field introduced in `3.6.0` requires a change in the mapping, otherwise, all content sync related to posts will silently fail. If you've upgraded to 3.6.0 and didn't resync your content yet (via Dashboard or with WP-CLI `wp elasticpress index --setup`) make sure to do so.

### Added
* Filter `ep_remote_request_add_ep_user_agent`. Passing `true` to that, the ElasticPress version will be added to the User-Agent header in the request. Props [@felipeelia](https://github.com/felipeelia) via [#2264](https://github.com/10up/ElasticPress/pull/2264)
* Flagged `3.6.0` as version that needs a full reindex. Props [@adiloztaser](https://github.com/adiloztaser) and [@felipeelia](https://github.com/felipeelia) via [#2264](https://github.com/10up/ElasticPress/pull/2264)

### Changed
* Notice when a sync is needed is now an error. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#2264](https://github.com/10up/ElasticPress/pull/2264)

### Fixed
* Encode the Search Term header before sending it to ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia) via [#2265](https://github.com/10up/ElasticPress/pull/2265)

## [3.6.0] - 2021-07-07
**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version requires a full reindex.

### Breaking Changes
* Autosuggest will now respect the `[name="post_type"]` input in the same form. Before it would bring all post types. Props [@mustafauysal](https://github.com/mustafauysal) and [@JakePT](https://github.com/JakePT) via [#1689](https://github.com/10up/ElasticPress/pull/1689)
* Facets Widget presentation, replacing the `<input type="checkbox">` elements in option links with a custom `.ep-checkbox presentational` div. Props [@MediaMaquina](https://github.com/MediaMaquina), [@amesplant](https://github.com/amesplant), [@JakePT](https://github.com/JakePT), and [@oscarssanchez](https://github.com/oscarssanchez) via [#1886](https://github.com/10up/ElasticPress/pull/1886)
* Confirmation for destructive WP-CLI commands. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@Rahmon](https://github.com/Rahmon) via [#2120](https://github.com/10up/ElasticPress/pull/2120)

### Added
* Comments Indexable. Props [@dkotter](https://github.com/dkotter), [@dinhtungdu](https://github.com/dinhtungdu), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#1531](https://github.com/10up/ElasticPress/pull/1531)
* "ElasticPress - Comments", a search form for comments. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia) via [#2238](https://github.com/10up/ElasticPress/pull/2238)
* Facets: new `ep_facet_allowed_query_args` filter. Props [@mustafauysal](https://github.com/mustafauysal), [@JakePT](https://github.com/JakePT),[@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#1689](https://github.com/10up/ElasticPress/pull/1689)
* Facets: new `ep_facet_use_field` filter. Props [@moraleida](https://github.com/moraleida) via [#2071](https://github.com/10up/ElasticPress/pull/2071)
* GitHub Action to auto-close non-responsive reporter feedback issues after 3 days. Props [@jeffpaul](https://github.com/jeffpaul) via [#2199](https://github.com/10up/ElasticPress/pull/2199)
* Autosuggest: new `ep_autosuggest_default_selectors` filter. Props [@JakePT](https://github.com/JakePT) and [@johnbillion](https://github.com/johnbillion) via [#2181](https://github.com/10up/ElasticPress/pull/2181)
* WP-CLI: Index by ID ranges with `--upper-limit-object-id` and `--lower-limit-object-id`. Props [@WPprodigy](https://github.com/WPprodigy), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2171](https://github.com/10up/ElasticPress/pull/2171)
* `Elasticsearch::get_documents()` and `Indexable::multi_get()`. Props [@nickdaugherty](https://github.com/nickdaugherty), [@felipeelia](https://github.com/felipeelia), and [@Rahmon](https://github.com/Rahmon) via [#2210](https://github.com/10up/ElasticPress/pull/2210)
* Custom sorting to features on the Features page. Props [@Rahmon](https://github.com/Rahmon) via [#1987](https://github.com/10up/ElasticPress/pull/1987)
* Terms: add a new `facet` field to hold the entire term object in json format. Props [@moraleida](https://github.com/moraleida) via [#2071](https://github.com/10up/ElasticPress/pull/2071)
* Elasticsearch connection check to Site Health page. Props [@spacedmonkey](https://github.com/spacedmonkey) and [@Rahmon](https://github.com/Rahmon) via [#2084](https://github.com/10up/ElasticPress/pull/2084)
* Support for NOT LIKE operator for meta_query. Props [@Thalvik)](https://github.com/Thalvik) and [@Rahmon](https://github.com/Rahmon) via [#2157.](https://github.com/10up/ElasticPress/pull/2157)
* Support for `category__not_in` and `tag__not_in`. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#2174](https://github.com/10up/ElasticPress/pull/2174)
* Support for `post__name_in`. Props [@jayhill90](https://github.com/jayhill90) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2194](https://github.com/10up/ElasticPress/pull/2194)
* `$indexable_slug` property to `ElasticPress\Indexable\Post\SyncManager`. Props [@edwinsiebel](https://github.com/edwinsiebel) via [#2196](https://github.com/10up/ElasticPress/pull/2196)
* Permission check bypass for indexing / deleting for cron and WP CLI. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@felipeelia](https://github.com/felipeelia) via [#2172](https://github.com/10up/ElasticPress/pull/2172)
* Check if term exists before a capabilities check is done. Props [@msawicki](https://github.com/msawicki) via [#2230](https://github.com/10up/ElasticPress/pull/2230)
* New `ep_show_indexing_option_on_multisite` filter. Props [@johnbillion](https://github.com/johnbillion) and [@Rahmon](https://github.com/Rahmon) via [#2156](https://github.com/10up/ElasticPress/pull/2156)
* Documentation updates related to upcoming changes in 3.7.0. Props [@jeffpaul](https://github.com/jeffpaul) via [#2248](https://github.com/10up/ElasticPress/pull/2248)
* Documentation about how to search using rendered content (shortcodes and reusable blocks). Props [@johnbillion](https://github.com/johnbillion) and [@felipeelia](https://github.com/felipeelia) via [#2127](https://github.com/10up/ElasticPress/pull/2127)
* Autosuggest: filter results HTML by defining a `window.epAutosuggestItemHTMLFilter()` function in JavaScript. Props [@JakePT](https://github.com/JakePT) via [#2146](https://github.com/10up/ElasticPress/pull/2146)

### Changed
* Autosuggest: JavaScript is not loaded anymore when ElasticPress is indexing. Props [@fagiani](https://github.com/fagiani) and [@felipeelia](https://github.com/felipeelia) via [#2163](https://github.com/10up/ElasticPress/pull/2163)
* `Indexable\Post\Post::prepare_date_terms()` to only call `date_i18n()` once. Props [@WPprodigy](https://github.com/WPprodigy) and [@Rahmon](https://github.com/Rahmon) via [#2214](https://github.com/10up/ElasticPress/pull/2214)

### Removed
* Assets source mappings. Props [@Rahmon](https://github.com/Rahmon) and [@MadalinWR](https://github.com/MadalinWR) via [#2162](https://github.com/10up/ElasticPress/pull/2162)
* References to `posts_by_query` property and `spl_object_hash` calls. Props [@danielbachhuber](https://github.com/danielbachhuber) and [@Rahmon](https://github.com/Rahmon) via [#2158](https://github.com/10up/ElasticPress/pull/2158)

### Fixed
* GitHub issue templates. Props [@jeffpaul](https://github.com/jeffpaul) via [#2145](https://github.com/10up/ElasticPress/pull/2145)
* Facets: error in filters where terms wouldn't match if the user types a space. Props [@felipeelia](https://github.com/felipeelia) via [#2218](https://github.com/10up/ElasticPress/pull/2218)
* Facets: pagination parameters in links are now removed when clicking on filters. Props [@shmaltz](https://github.com/shmaltz), [@oscarssanchez](https://github.com/oscarssanchez), and [@Rahmon](https://github.com/Rahmon) via [#2229](https://github.com/10up/ElasticPress/pull/2229)
* Output of WP-CLI index errors. Props [@notjustcode-sp](https://github.com/notjustcode-sp) and [@felipeelia](https://github.com/felipeelia) via [#2243](https://github.com/10up/ElasticPress/pull/2243)
* `index_name` is transformed in lowercase before the index creation in Elasticsearch. Props [@teoteo](https://github.com/teoteo) and [@felipeelia](https://github.com/felipeelia) via [#2173](https://github.com/10up/ElasticPress/pull/2173)
* Validate that a meta_value is a recognizable date value before storing. Props [@jschultze](https://github.com/jschultze), [@moraleida](https://github.com/moraleida) and [@Rahmon](https://github.com/Rahmon) via [#1703](https://github.com/10up/ElasticPress/pull/1703)
* Array with a MIME type without the subtype in `post_mime_type` argument. Props [@ethanclevenger91](https://github.com/ethanclevenger91) and [@Rahmon](https://github.com/Rahmon) via [#2222](https://github.com/10up/ElasticPress/pull/2222)
* Sort for WP_User_Query. Props [@Rahmon](https://github.com/Rahmon) via [#2226](https://github.com/10up/ElasticPress/pull/2226)
* WP Acceptance Tests. Props [@felipeelia](https://github.com/felipeelia) via [#2184](https://github.com/10up/ElasticPress/pull/2184)
* Styling issue of Autosuggest and search block (WP 5.8). Props [@dinhtungdu](https://github.com/dinhtungdu) via [#2219](https://github.com/10up/ElasticPress/pull/2219)
* `Undefined variable: closed` notice in `Elasticsearch::update_index_settings()`. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@pschoffer](https://github.com/pschoffer) via [#2159](https://github.com/10up/ElasticPress/pull/2159)
* Documentation for WP-CLI `*-feature` commands. Props [@felipeelia](https://github.com/felipeelia) via [#2164](https://github.com/10up/ElasticPress/pull/2164)
* Custom Results: a `current_user_can()` call now receives the post ID instead of the whole object. Props [@Sysix](https://github.com/Sysix) via [#2255](https://github.com/10up/ElasticPress/pull/2255)
* Autosuggest: adjust debounce to avoid sending unnecessary requests to the server. Props [@Rahmon](https://github.com/Rahmon) via [#2257](https://github.com/10up/ElasticPress/pull/2257)

### Security
* Updated browserslist and jsdoc versions. Props [@felipeelia](https://github.com/felipeelia) via [#2246](https://github.com/10up/ElasticPress/pull/2246)
* Updated lodash, hosted-git-info, ssri, rmccue/requests, and y18n versions. Props [@dependabot](https://github.com/dependabot) via [#2203](https://github.com/10up/ElasticPress/pull/2203), [#2204](https://github.com/10up/ElasticPress/pull/2204), [#2179](https://github.com/10up/ElasticPress/pull/2179), [#2188](https://github.com/10up/ElasticPress/pull/2188), and [#2153](https://github.com/10up/ElasticPress/pull/2153)

## [3.5.6] - 2021-03-18
This release fixes some bugs and also adds some new actions and filters.

Security Fix:
* Updated JS dependencies. Props [@hats00n](https://github.com/hats00n) and [@felipeelia](https://github.com/felipeelia)

Bug Fixes:
* Fixed document indexing when running index command with nobulk option. Props [@Rahmon](https://github.com/Rahmon)
* Added an extra check in the iteration over the aggregations. Props [@felipeelia](https://github.com/felipeelia)
* Fixed no mapping found for [name.sortable] for Elasticsearch version 5. Props [@Rahmon](https://github.com/Rahmon)
* Fixed uninstall process to remove all options and transients. Props [@Rahmon](https://github.com/Rahmon)

Enhancements:
* Added missing inline JS documentation. Props [@JakePT](https://github.com/JakePT)
* Added the filter `ep_autosuggest_http_headers`. Props [@Rahmon](https://github.com/Rahmon)
* Added terms indexes to the status and stats WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* The Protected Content feature isn't auto-activated when using ElasticPress.io anymore. Props [@felipeelia](https://github.com/felipeelia)
* Added the new filter `ep_highlight_should_add_clause` to let developers decide where the highlight clause should be added to the ES query. Props [@felipeelia](https://github.com/felipeelia)
* Added the new filter `epwr_weight` and changed the default way scores are applied based on post date. Props [@Rahmon](https://github.com/Rahmon)

## [3.5.5] - 2021-02-25
This release fixes some bugs and also adds some new actions and filters.

Bug Fixes:
* Fix a problem in autosuggest when highlighting is not active. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon)
* Fix Facet Term Search for more than one Widget. Props [@goaround](https://github.com/goaround)
* Fix a Warning that was triggered while using PHP 8. Props [@Rahmon](https://github.com/Rahmon)
* Fix a wrong phrase in the Indexables documentation. Props [@jpowersdev](https://github.com/jpowersdev)

Enhancements:
* Add an `is-loading` class to the search form while autosuggestions are loading. Props [@JakePT](https://github.com/JakePT)
* Add the new `set-algorithm-version` and `get-algorithm-version` WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* Add a new `ep_query_weighting_fields` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott)
* Add two parameters to the `ep_formatted_args_query` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott)
* Add the new `set-algorithm-version` and `get-algorithm-version` WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* Create a new section in documentation called `Theme Integration`. Props [@JakePT](https://github.com/JakePT)
* Improvements to contributing documentation and tests. Props [@jeffpaul](https://github.com/jeffpaul) and [@felipeelia](https://github.com/felipeelia)
* Add the following new actions: `ep_wp_cli_after_index`, `ep_after_dashboard_index`, `ep_cli_before_set_search_algorithm_version`, `ep_cli_after_set_search_algorithm_version`, `ep_cli_before_clear_index`, `ep_after_update_feature`, and `ep_cli_after_clear_index`. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon)

## [3.5.4] - 2021-02-11
This is primarily a security and bug fix release. PLEASE NOTE that versions 3.5.2 and 3.5.3 contain a vulnerability that allows a user to bypass the nonce check associated with re-sending the unaltered default search query to ElasticPress.io that is used for providing Autosuggest queries. If you are running version 3.5.2. or 3.5.3 please upgrade to 3.5.4 immediately.

Security Fix:
* Fixed a nonce check associated with updating the default Autosuggest search query in ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia)

Bug Fixes:
* Fix broken click on highlighted element in Autosuggest results. Props [@felipeelia](https://github.com/felipeelia)
* Properly cast `from` parameter in `$formatted_args` to an integer to prevent errors if empty. Props [@CyberCyclone](https://github.com/CyberCyclone)

Enhancements:
* Add an `ep_is_facetable` filter to enable custom control over where to show or hide Facets. Props [@moraleida]
* Improvements to contributing documentation and tests. Props [@jeffpaul](https://github.com/jeffpaul) and [@felipeelia](https://github.com/felipeelia)

## [3.5.3] - 2021-01-28
This is a bug fix release.

Bug Fixes:
* Fixed a bug where the `ep-synonym` post type is updated to a regular post, which can cause it to be accidentally deleted. Props [@Rahmon](https://github.com/Rahmon)
* Fixed CSS formatting issues in the Settings and Features menus. Props [@Rahmon](https://github.com/Rahmon)

## [3.5.2] - 2021-01-18
This is a bug fix release.

Bug Fixes:
* Fixed a typo in elasticpress.pot. Props [@alexwoollam](https://github.com/alexwoollam)
* Don’t use timestamps that cause 5 digit years. Props [@brandon-m-skinner](https://github.com/brandon-m-skinner)
* Fix admin notice on the Synonyms page. Props [@Rahmon](https://github.com/Rahmon)
* Properly update slider numbers while sliding. Props [@Rahmon](https://github.com/Rahmon)
* Properly handle error from `get_terms()`. Props [@ciprianimike](https://github.com/ciprianimike)
* Fix incorrect titles page. Props [@Rahmon](https://github.com/Rahmon)
* Fix linting tests. Props [@felipeelia](https://github.com/felipeelia)
* Fix issue with price filter unsetting previous query. Props [@oscarssanchez](https://github.com/oscarssanchez)

Enhancements:
* Added actions that fire after bulk indexing (`ep_after_bulk_index`), in event of an invalid Elasticsearch response (`ep_invalid_response`), and before object deletion (`ep_delete_{indexable slug}`); added filters `ep_skip_post_meta_sync`, `pre_ep_index_sync_queue`, `ep_facet_taxonomies_size`, `epwr_decay_function`, `and epwr_score_mode`. Props [@brandon-m-skinner](https://github.com/brandon-m-skinner)
* Added `ep_filesystem_args` filter. Props [@pjohanneson](https://github.com/pjohanneson)
* Add SKU field to Weighting Engine if WooCommerce is active and fix issue with overriding `search_fields`. Props [@felipeelia](https://github.com/felipeelia)
* Support `author__in` and `author__not_in` queries. Props [@dinhtungdu](https://github.com/dinhtungdu)
* Update multiple unit tests. Props [@petenelson](https://github.com/petenelson)
* Show CLI indexing status in EP dashboard. Props [@Rahmon](https://github.com/Rahmon)
* Add `ep_query_send_ep_search_term_header` filter and don’t send `EP-Search-Term` header if not using ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia)

## [3.5.1] - 2020-10-29
A bug fix release.

Bug fixes:
* Fixes highlighting so that full content is returned instead of only snippets.
* Fix empty synonym bug.
* Only highlight post content, excerpt, and title.

Enhancements:
* Track CLI index in a headless fashion

## [3.5.0] - 2020-10-20
Version 3.5 is a very exciting release as it contains two major new features: a synonym dashboard and search term result highlighting. The synonym dashboard empowerers users to create synonym lists for searches. For example. searching "New York City" would return contain with "NYC". Search term highlighting will underline and add a CSS class to keywords within content that matches the current search.

The new version also includes a revamp of the search algorithm. This is a backwards compatibility break. If you'd like to revert to the old search algorithm, you can use the following code: `add_filter( 'ep_search_algorithm_version', function() { return '3.4'; } );`. The new algorithm offers much more relevant search results and removes fuzziness which results in mostly unwanted results for most people. If you are hooking in and modifying the search query directly, it's possible this code might break and you might need to tweak it.

Bug fixes:
* Fix default autosuggest selector.
* Fix facet feature $_GET parameter naming collision.
* Autosuggest a11y fixes. Props [amesplant](https://github.com/amesplant).
* Check $feature type before calling Feature methods. Props [pdewouters](https://github.com/pdewouters).
* Ensure trashed post is removed from Elasticsearch. Props [edwinsiebel](https://github.com/edwinsiebel).
* Add default permission callback to REST routes. Props [dkotter](https://github.com/dkotter).
* Hide links to weighting and synonym dashboard if network activated. Props [felipeelia](https://github.com/felipeelia).
* Only sync post on allow listed post meta. Props [felipeelia](https://github.com/felipeelia).
* Check if site is indexable before syncing in network activated mode.
* Fix facet widget and 3-level deep hierarchical taxonomy. Props [dinhtungdu](https://github.com/dinhtungdu).
* Make sure AJAX sync is disabled if EP_DASHBOARD is set to false.. Props [turtlepod](https://github.com/turtlepod).

Enhancements:
* Synonym dashboard. Props [christianc1](https://github.com/christianc1).
* Search term highlighting. Props [oscarsanchez](https://github.com/oscarsanchez).
* Search algorithm improvements.
* Improved WP Acceptance tests. Props [asharirfan](https://github.com/asharirfan).
* Rename ElasticPress submenu to "Features". Props [helen](https://github.com/helen).
* Add functionality for skipping ElasticPress install.
* Increase max supported Elasticsearch version to 7.9.
* Add filter to prepared user meta data. Props [g-kanoufi](https://github.com/g-kanoufi).
* Improve Terms Feature terminology to remove confusion.
* Add filter to facet terms query arguments. Props [felipeelia](https://github.com/felipeelia).

## [3.4.3] - 2020-07-21
Enhancements:
* Remove jQuery from front end JavaScript dependencies.

Bug Fixes:
* Fix accessibility bug on autosuggest.
* Fix broken facet search.

## [3.4.2] - 2020-06-17
Bug fixes:
* uninstall.php: Change the EP_FILE const to its value. Props [felipeelia](https://github.com/felipeelia).
* Fix list features WP CLI command. Props [felipeelia](https://github.com/felipeelia).
* Add `rel="nofollow"` to facet links. Props [mlaroy](https://github.com/mlaroy).
* Facets widget: Move `<div>` outside `ob_start()`. Props [kallehauge](https://github.com/kallehauge).
* Load facet scripts and styles only when they are really necessary. Props [goaround](https://github.com/goaround).
* Index attachments with Protected Content and query for them in media search. Props [oscarsanchez](https://github.com/oscarsanchez).
* Fixed `Deprecated field [include] used, expected [includes] instead.`. Props [dinhtungdu](https://github.com/dinhtungdu).

Enhancements:
* Add filter for enabling sticky posts.  Props [shadyvb](https://github.com/shadyvb).
* Add sync kill filter. Props [barryceelen](https://github.com/barryceelen).
* Add timeout filters for bulk_index and index_document. Props [@oscarsanchez](https://github.com/oscarsanchez).

## [3.4.1] - 2020-3-31
* Make weighting dashboard flex containers to prevent the slider from changing size. Props [@mlaroy](https://github.com/mlaroy).
* Fix issue where weightings wouldn't save properly for certain post types. Props [mustafauysal](https://github.com/mustafauysal).
* Fix bug where terms wouldn't finish syncing in certain scenarios.
* Properly order WooCommerce products using double to account for decimals. Props [@oscarsanchez](https://github.com/oscarsanchez).
* Show current indices in index health dashboard. Props [moraleida](https://github.com/moraleida).

## [3.4.0] - 2020-03-03
* Addition of Terms Indexable and Feature. ElasticPress can now integrate with `WP_Term_Query`. Props [dkotter](https://github.com/dkotter).
* Fixes for `WP_User_Query` 'fields' parameter. Props [petenelson](https://github.com/petenelson).
* Support all taxonomies in root of `WP_Query`
* Readd `ep_retrieve_aggregations` hook for backwards compatibility
* Move indexable posts class registration into a function that runs in the `plugins_loaded` action. Props [petenelson](https://github.com/petenelson).
* Fix author name in weighting and use post_author.display_name for weighted author field search. Props [petenelson](https://github.com/petenelson).
* Add `ep_prepared_*_meta` filters
* Refactor CLI indexing code for simplicity.
* Limit indexed Protected Content post types removing `revision`, `oembed_cache`, `custom_css`, `user_request`, `customize_changeset`, and `wp_block`.
* Cast taxonomy slug to array in case it's already an array in `WP_Query`.
* Remove unnecessary usage of `--network-wide` CLI paramter.
* Add name, nickname, and display name to fields used for user search.
* Add `clear-transient` WP CLI command.
* Don't make product categories facetable when WooCommerce feature is not active. Props [mustafauysal](https://github.com/mustafauysal).

## [3.3.0] - 2018-12-18
* Officially support Elasticsearch 7.5
* Add optional Google Analytics Autosuggest tracking Event
* Fix single node warning before sync has occurred.
* When `ep_integrate` is set to false, do not apply faceting.
* Fix search ordering error when there are no pointers.
* Add filter `ep_get_hits_from_query` to modify retrieved Elasticsearch hits.
* Make sure `post_type` array does not include keys. Fixes a bbPress issue.
* Pass query object to EP response so we can check for main query. This fixes a faceting bug.
* Add EP-Search-Term header to autosuggest requests to EP.io
* Clean up indexing transient on sigkill

## [3.2.6] - 2019-11-27
* Fix Under some edge conditions content for autosuggest can be large - don't cache it

## [3.2.5] - 2019-11-20
* Fix WP <5.0 fatal error on register_block_type.

## [3.2.4] - 2019-11-19
* Fix Gutenberg block initialization
* Fix Autosuggest: remove filter with proper priority in query generation. Props [Maxdw](https://github.com/Maxdw).
* Fix Autosuggest: returning WP_Error for non object cache autosuggest queries causes issue. Fallback to transient

## [3.2.3] - 2019-11-13
* Ensure query building for Autosuggest does not fallback to WPDB.

## [3.2.2] - 2019-11-05
* Fix PHPCS errors. Props [mmcachran](https://github.com/mmcachran)
* Fix ensuring stats are built prior to requesting information
* Fix related post block enqueue block assets on the frontend
* Fix custom order results change webpack config for externals:lodash
* Fix don't overwrite search fields
* Autosuggest queries generated though PHP instead of JavaScript
* Add WP Acceptance tests
* Add new WP-CLI commands: get_indexes and get_cluster_indexes

## [3.2.1] - 2019-10-14
* Fix Gutenberg breaking issue with Related Posts and image blocks. Props [adamsilverstein](https://github.com/adamsilverstein)

## [3.2.0] - 2019-10-08
* Improve block asset enqueueing: hook on `enqueue_block_editor_assets`. Props [adamsilverstein](https://github.com/adamsilverstein).
* Handle empty search weighting fields bug.
* Integrate WooCommerce default filter by price widget with ES range query.
* Improve messaging for custom result post type.
* Index health page.
* Add tag_in and tag__and support.
* Related posts Gutenberg block.
* Facet widget ordering option. Props [psorensen](https://github.com/psorensen).
* Control Index-ability of individual sites in multisite.
* Integrate WooCommerce default filter by price widget with ES range query.

See https://github.com/10up/ElasticPress/pulls?utf8=%E2%9C%93&q=is%3Apr+milestone%3A3.2.0+is%3Aclosed+

## [3.1.1] - 2019-07-25
### Fixed
- Ensure taxonomies that are shared among multiple post types show up on the weighting screen

## [3.1.0] - 2019-07-22
### Added
- Support for nested tax queries. Props [@dkotter](https://github.com/dkotter)
- `ep_bulk_index_action_args` filter. Props [@fabianmarz](https://github.com/fabianmarz)
- Add filters to control MLT related posts params.
- `ep_allow_post_content_filtered_index` filter to bypass filtered post content on indexing.
- Weighting dashboard to control weights of specific fields on a per post type basis
- Search ordering feature. Enables custom results for specific search queries.

### Changed
- Refactor admin notice, admin screen "resolver", and install path logic
- WordPress.org profile
- New EP settings interface. Props [@dkoo](https://github.com/dkoo)
- Delete pagination from facet URL.
- allows WooCommerce product attributes to be facetable in 3.0
- Autosuggest queries now match the search queries performed by WordPress, including weighting and any custom results

### Fixed
- Fix data escaping in WP 4.8.x
- Support order by "type"/"post_type" in EP queries
- Properly redirect after network sync
- User mapping for pre 5.0 Props [@mustafauysal](https://github.com/mustafauysal)
- Avoid multiple reflows in autosuggest. Props [@fabianmarz](https://github.com/fabianmarz)
- 400 error when popularity is default sorting.
- Fixed Facet widget not rendering WC product attribute options. Props [@fabianmarz](https://github.com/fabianmarz)
- Delete wpcli sync option/transient when an error occurs
- Create index/network alias when adding a new site on a network activated installation. Props [@elliott-stocks](https://github.com/elliott-stocks)
- Fix WooCommerce order search when WooCommerce module activated but protected content turned off.

## [3.0.3] - 2019-06-04
### Added
- Pass $post_id twice in ep_post_sync_kill for backwards compatibility. Props [@aaemnnosttv](https://github.com/aaemnnosttv)
- `ep_search_request_path` filter for backwards compant.
- `ep_query_request_path` filter for modifying the query path.

### Fixed
- Missing action name in post query integration.
- Properly add date filter to WP_Query.

## [3.0.2] - 2019-05-23
### Fixed
- Date query errors
- Re-add ep_retrieve_the_{type} filter. Props [@gassan](https://github.com/gassan)
- Empty autosuggest selector notice

## [3.0.1] - 2019-05-20
### Changed
- Changed max int to use core predefined constant. Props [@fabianmarz](https://github.com/fabianmarz)

### Fixed
- `wp elasticpress stats` and `wp elasticpress status` commands fatal error.
- Add autosuggest selector field default to fix notice.
- Re-add `ep_find_related` as deprecated function.
- Properly support legacy feature registration callbacks per #1329.
- Properly disable settings as needed on dashboard.
- Don't force document search on REST requests.

## [3.0] - 2019-05-13
### Notice
- Requires re-index

### Overview
- 3.0 is a refactor of ElasticPress for modern coding standards (PHP 5.4 required) as well as the introduction to indexables. Indexables abstracts out content types so data types other than post can be indexed and searched. 3.0 includes user indexing and search (integration with WP_User_Query). User features require at least WordPress version 5.1.
- The refactor changes a lot of ElasticPress internals. The biggest change is the feature registration API has completely changed. Now, new features should extend the `ElasticPress\Feature` class rather than calling `ep_register_feature`. Older features should be backwards compatible.

### Added
- Elasticsearch language setting in admin

### Changed
- `ep_post_sync_kill` filter removed `$post_args` argument.
- `posts-per-page` changed to `per-page` for WP-CLI index command.

### Removed
- `ep_feature_setup` action

## [2.8.2] - 2019-03-26
### Added
- WooCommerce product attributes as facets.
- Performance Boost for document indexing.

### Fixed
- Issue on WP REST API searches.
- Case-sensitivity issue with facet search.

## [2.8.1] - 2019-02-13
### Fixed
- Homepage out of chronological order.
- Missing meta key. (Props [@turtlepod](https://github.com/turtlepod))
- Bulk indexing default value on settings page.

## [2.8.0] - 2019-02-08
### Added
- Sticky posts support.
- WooCommerce searches with custom fields.
- Elasticsearch version in settings. (Props [@turtlepod](https://github.com/turtlepod))
- Allow user to set number of posts during bulk indexing cycle.
- Facet query string customization (Props [@ray-lee](https://github.com/ray-lee))
- Protected content: filtering of filtered post types.
- Implemented --post-ids CLI option to index only specific posts. (Props [@dotancohen](https://github.com/dotancohen))

### Changed
- Meta LIKE query adjustment.
- Autosuggest to abide by plugin settings.
- Adjustment to `wp elasticpress status`

### Removed
- Logic that determines if blog is public / indexable. (Resolves sync issue.)
- Date weighting for protected content admin queries.

### Fixed
- Autosuggest bugfix.
- Auto activating sync notices. (Props [@petenelson](https://github.com/petenelson))

## [2.7.0] - 2018-12-06
### Notice
- Requires re-index

### Added
- Prevent indexing when blog is deleted or not public.
- Do not apply absint to comment_status.
- Random WooCommerce ordering allowed.
- Better error notices. (Props [@petenelson](https://github.com/petenelson))

### Changed
- Query only post IDs when indexing. (Props [@elliott-stocks](https://github.com/elliott-stocks))

### Fixed
- ElasticPress.io credentials bugfix.
- Related posts bugfix.

## [2.6.1] - 2018-08-24
### Fixed
- Missing file for wp-cli.

## [2.6] - 2018-08-22
### Added
- Ability to set autosuggest endpoint by a constant (EP_AUTOSUGGEST_ENDPOINT).
- Enable WooCommerce products to be included in autosuggest results.
- Support for tax_query operators EXISTS and NOT EXISTS.
- Filter to change default orderby/sort (ep_set_default_sort).

### Changed
- Do not search for author_name when searching products in WooCommerce.

## [2.5.2] - 2018-05-09
### Notice
- Requires re-index

### Removed
- Unnecessary facet JavaScript

### Fixed
- Facet aggregations warning

## [2.5.1] - 2018-05-02
### Notice
- Requires re-index as we change the way data is being sent to Elasticsearch.
- It's also worth noting for ElasticPress version 2.5+, the Facets feature, which is on by default, will run post type archive and search page main queries through Elasticsearch. If Elasticsearch is out of sync with your content (possible in rare edge cases), this could result in incorrect content being shown. Turning off Facets would fix the problem.

### Fixed
- Don't pre-strip HTML before sending it to Elasticsearch.
- Support PHP 5.2 backwards compat.
- Don't show faceting widget if post type doesn't support taxonomy.

## [2.5] - 2018-04-23
### Overview
- ElasticPress 2.5 includes a new Facets feature that makes it easy to add high performance content filtering controls to a website.
- A new Facets widget enables site administrators to add taxonomy facets to a sidebar (or any widgetized area). When viewing a content list on the front end of the website, the widget will display the name of the taxonomy – e.g. “Categories” – and a checklist with all of its terms. Visitors can narrow down content by selecting terms they are interested in. The Facets feature can be globally configured to narrow results to content that is tagged with any or all of the selected terms. The widget’s front end output contains carefully named CSS classes, so that designers and developers can apply unique styling.

### Added
- Official support for Elasticsearch 6.2
- Increased functional parity with the WP_Query API
- Facets feature
- `--post-ids` CLI option to index only specific posts. Props [@dotancohen](https://github.com/dotancohen).
- Filter for hiding host setting in dashboard. Props [@tomdxw](https://github.com/tomdxw).
- Support `WP_Query` meta query `not between` comparator.

### Fixed
- Disallow duplicated Elasticsearch requests on WooCommerce orders page. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Taxonomy sync object warning. Props [@eugene-manuilov](https://github.com/eugene-manuilov)
- `true` in `is_empty_query` terminates ep_query process when it shouldn't. Props [@yaronuliel](https://github.com/yaronuliel)

## [2.4.2] - 2018-01-31
### Fixed
- Related posts not showing up bug.

## [2.4.1] - 2018-01-30
### Added
- Increase maximum Elasticsearch compatibility to 6.1

### Fixed
- Support Elasticsearch 6.1 and properly send Content-Type header with application/json. Props [@eugene-manuilov](https://github.com/eugene-manuilov).
- Autosuggest event target issue bug. Props [@eugene-manuilov](https://github.com/eugene-manuilov).
- Widget init bug. Props [@eugene-manuilov](https://github.com/eugene-manuilov).
- Taxonomy sync parameter warning. Props [@eugene-manuilov](https://github.com/eugene-manuilov).

## [2.4] - 2017-11-01
### Overview
- Version 2.4 introduces the Autosuggest feature. When enabled, input fields of type "search" or with the CSS class "search-field" or "ep-autosuggest" will be enhanced with autosuggest functionality. As text is entered into the search field, suggested content will appear below it, based on top search results for the text. Suggestions link directly to the content.
- We also added hooks and filters to ElasticPress that make query logging possible. The [Debug Bar ElasticPress](https://github.com/10up/debug-bar-elasticpress) plugin now adds a Query Log screen to the ElasticPress admin menu. The Query Log is an extremely powerful tool for diagnosing search and indexing issues.

### Added
- Autosuggest feature
- Hooks for query log functionality in [Debug Bar ElasticPress](https://github.com/10up/debug-bar-elasticpress)
- Support `WP_Query` `fields` parameter. Props [@kallehauge](https://github.com/kallehauge).
- Setting for enabling/disabling date weighting in search. Props [@lukaspawlik](https://github.com/kallehauge).
- Shipping class as indexed WooCommerce taxonomy. Props [@kallehauge](https://github.com/kallehauge).
- Allow WooCommerce orders to be searched by items. Props [@kallehauge](https://github.com/kallehauge).
- Support Elasticsearch 5.6
- Filter to granularly control admin notices. Props [@mattonomics](https://github.com/mattonomics).
- Support ES 5.5+ strict content type checking. Props [@sc0ttclark](https://github.com/sc0ttclark)

### Removed
- Extra post meta storage key from Elasticsearch

### Fixed
- `author_name` search field. Props [@ivankristianto](https://github.com/ivankristianto).
- Unavailable taxonomy issue in WooCommerce. Props [@ivankristianto](https://github.com/ivankristianto).
- Index all publicly queryable taxonomies. Props [@allan23](https://github.com/allan23).
- Resolve case insensitive sorting issues. Props [@allan23](https://github.com/allan23).
- Escaping per VIP standards. Props [@jasonbahl](https://github.com/jasonbahl).
- WooCommerce post type warnings.

## [2.3.2] - 2017-06-30
### Fixed
- Broken upgrade sync notification.
- Cache ES plugins request. **This is super important.** Instead of checking the status of ES on every page load, do it every 5 minutes. If ES isn't available, show admin notification that allows you to retry the host.

## [2.3.1] - 2017-06-29
### Notice
- This requires a re-index if you are using the WooCommerce feature.

### Fixed
- Properly respect WC product visibility. Props [@ivankristianto](https://github.com/ivankristianto).

## [2.3] - 2017-05-26
### Overview
- Version 2.3 introduces the Documents feature which indexes text inside of popular file types, and adds those files types to search results. We've also officially added support for Elasticsearch 5.3.

### Added
- Documents feature
- Enable multiple feature status messages
- Disable dashboard sync via constant: `define( 'EP_DASHBOARD_SYNC', false );`. Props [@rveitch](https://github.com/rveitch).
- Filter for custom WooCommerce taxonomies. Props [@kallehauge](https://github.com/kallehauge).
- Support WooCommerce `product_type` taxonomy. Props [@kallehauge](https://github.com/kallehauge).

### Fixed
- WP-CLI `--no-bulk` number of posts indexed message. Props [i@vankristianto](https://github.com/ivankristianto).
- Honor `ep_integrate` in WooCommerce queries. Props [@ivankristianto](https://github.com/ivankristianto).
- Properly check when ES results are empty. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Incorrect `found_posts` set in query when ES is unavailable. Props [@lukaspawlik](https://github.com/lukaspawlik)

## [2.2.1] - 2017-03-30
### Added
- `EP_INDEX_PREFIX` constant. If set, index names will be prefixed with the constant. Props [@allan23](https://github.com/allan23).
- Increase total field limit to 5000 and add filter. Props [@ssorathia](https://github.com/ssorathia).
- Increase max result window size to 1000000 and add filter.

### Removed
- operator=>AND unneed execution code.

### Fixed
- Dashboard syncing delayed start issues.
- If plugins endpoint errors, try root endpoint to get the ES version.
- Make sure orderby is correct for default WooCommerce sorting. Props [@ivankristianto](https://github.com/ivankristianto).
- Stop dashboard sync if error occurs in the middle. Props [@ivankristianto](https://github.com/ivankristianto).
- Prevent EP from auto-activating a feature that was force deactivated
- Prevent massive field Elasticsearch error when indexing large strings

## [2.2] - 2017-02-28
### Notice
- Requires re-index

### Overview
- Version 2.2 rethinks the module process to make ElasticPress a more complete query engine solution. Modules are now auto-on and really just features. Why would anyone want to not use amazing functionality that improves speed and relevancy on their website? Features (previously modules) can of course be overridden and disabled. Features that don't have their minimum requirements met, such as a missing plugin dependency, are auto-disabled.
- We've bumped the minimum Elasticsearch version to 1.7 (although we strongly recommend 2+). The maximum tested version of Elasticsearch is version 5.2. If you are running Elasticsearch outside this version range, you will see a warning in the dashboard.

### Added
- __(Breaking change)__ Module registration API changed. See `register_module` in `classes/class-ep-modules.php`.
- __(Breaking change)__ Related posts are now in a widget instead of automatically being appending to content.
- __(Breaking change)__ Admin module renamed to Protected Content.
- Admin warning if current Elasticsearch version is not between the min/max supported version. Version 2.2 supports versions 1.3 - 5.1.
- Auto-reindex on versions requiring reindex.
- User friendly admin notifications for ElasticPress not set up, first sync needed, and feature auto activation.
- Protected Content feature applies to all features. This means if Protected Content isn't active, search or WooCommerce integration won't happen in the admin.
- Support for post_mime_type. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- 'number' param to satisfy WP v4.6+ fixing get_sites call. Props [@rveitch](https://github.com/rveitch)

### Fixed
- Back compat with old `ep_search` function.
- Respect indexable post types in WooCommerce feature
- New product drafts not showing in WooCommerce admin list
- WooCommerce feature breaking image search in media library. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- WooCommerce order search broken
- Stop the insansity made private. Props [@sc0ttclark](https://github.com/sc0ttclark)
- Multidimensional meta querys. Props [@Ritesh-patel](https://github.com/Ritesh-patel).
- Properly show bulk index errors in WP-CLI
- Update ep_delete_post, include $post_type argument. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- post_type product getting set in any WP_Query if tax_query is provided in WooCommerce feature. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- Order by proper relevancy in WooCommerce product search. Props [@ivankristianto](https://github.com/ivankristianto)
- Recursion fatal error due to oembed discovery during syncing. Props [@ivankristianto](https://github.com/ivankristianto)

## [2.1.2] - 2016-11-11
### Notice
- Requires re-index

### Changed
- Separate mapping for ES 5.0+

### Fixed
- Unit tests

## [2.1.1] - 2016-09-29
### Fixed
- PHP 5.3 errors
- Properly show syncing button module placeholder during sync

## [2.1] - 2016-09-20
### Backcompat breaks
- Move ep_admin_wp_query_integration to search integration only. EP integration by default is available everywhere.
- Remove `keep alive` setting
- Remove setting to integrate with search (just activate the module instead)
- Back up hosts code removed
- Remove active/inactive state. Rather just check if an index is going on our not.

### Added
- Support `meta_key` and `meta_value`
- Order by `meta_value_num`
- Search scope file. Props [@rveitch](https://github.com/rveitch)
- Support WP_Query `post_status`. Props [@sc0ttclark](https://github.com/sc0ttkclark)

### Changed
- Redo UI
- Make plugin modular
- Bundle existing modules into plugin

### Removed
- Remove unnecessary back up hosts code

### Fixed
- Properly support `post_parent = 0`. Props [@tuanmh](https://github.com/tuanmh)
- `post__in` support
- `paged` overwriting `offset`
- Integer and comma separated string `sites` WP_Query processing. Props [@jaisgit](https://github.com/jaisgit).

## [2.0.1] - 2016-08-02
### Fixed
- Don't load settings on front end. This fixes a critical bug causing ElasticPress to check the Elasticsearch connection on the front end.

## [2.0] - 2016-06-01
### Overview
- 10up ships ElasticPress 2.0 with __radical search algorithm improvements__ and a __more comprehensive integration of WP_Query__. ElasticPress is now even closer to supporting the complete WP_Query API. This version also improves upon post syncing ensuring that post meta updates are synced to Elasticsearch, adds a number of important hooks, and, of course, fixes some pesky bugs.
- A special thanks goes out to [Tuan Minh Huynh](https://github.com/tuanmh) and everyone else for contributions to version 2.0.

### Added
- Radical search algorithm improvements for more relevant results (see [#508](https://github.com/10up/ElasticPress/pull/508) for details)
- Support meta `BETWEEN` queries.
- Support `OR` relation for tax queries.
- Sync post to Elasticsearch when meta is added/updated.
- Support all taxonomies as root WP_Query arguments. Props [@tuanmh](https://github.com/tuanmh)
- `ID` field to Elasticsearch mapping
- Support `post_parent` WP_Query arguments. Props [@tuanmh](https://github.com/tuanmh)
- Filter to disable printing of post index status. Props [@tuanmh](https://github.com/tuanmh)
- Useful CLI hooks
- Filter to bypass permission checking on sync (critical for front end updates)

### Changed
- Improve GUI by disabling index status meta box text and improving instructions. Props [@ivanlopez](https://github.com/ivanlopez)

### Fixed
- Consider all remote request 20x responses as successful. Props [@tuanmh](https://github.com/tuanmh)
- Plugin localization. Props [@mustafauysal](https://github.com/mustafauysal)
- Do query logging by default. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Cannot redeclare class issue. Props [@tuanmh](https://github.com/tuanmh)
- Double querying Elasticsearch by ignoring `category_name` when `tax_query` is present.
- Post deletion endpoint URL. Props [@lukaspawlik](https://github.com/lukaspawlik)

## [1.9.1] - 2016-05-23
### Fixed
- GUI not working properly when plugin is not network enabled within multisite. Props [@ivanlopez](https://github.com/ivanlopez)

## [1.9] - 2016-05-17
### Overview
- ElasticPress 1.9 adds in an admin UI, where you can set your Elasticsearch Host and run your index command, without needing to us WP-CLI. Version 1.9 also adds in some performance improvements to reduce memory consumption during indexing. Full list of enhancements and bug fixes:

### Added
- Admin GUI to handle indexing. Props [@ChrisWiegman](https://github.com/ChrisWiegman).
- Option to not disable ElasticPress while indexing. Props [@lukaspawlik](https://github.com/lukaspawlik).
- Allow filtering of which post types we want to search for. Props [@rossluebe](https://github.com/rossluebe).
- Ensure both PHPUnit and WP-CLI are available in the development environment. Props [@ChrisWiegman](https://github.com/ChrisWiegman).
- User lower-case for our composer name, so packagist can find us. Props [@johnpbloch](https://github.com/johnpbloch).
- Check query_vars, not query to determine status. Props [@ChrisWiegman](https://github.com/ChrisWiegman).
- Further reduce memory usage during indexing. Props [@lukaspawlik](https://github.com/lukaspawlik).
- post__in and post__not_in documentation. Props [@mgibbs189](https://github.com/mgibbs189).
- Elasticsearch Shield authentication headers if constant is set. Props [@rveitch](https://github.com/rveitch).

### Changed
- Improve memory usage during indexing and fix unnecessary cache flushes. Props [@cmmarslender](https://github.com/cmmarslender).

### Removed
- composer.lock from the repo. Props [@ChrisWiegman](https://github.com/ChrisWiegman).

### Fixed
- --no-bulk indexing option. Props [@lukaspawlik](https://github.com/lukaspawlik).
- Error that occurs if no Elasticsearch host is running. Props [@petenelson](https://github.com/petenelson).
- Exception error. Props [@dkotter](https://github.com/dkotter).
- WP-CLI status command. Props [@dkotter](https://github.com/dkotter).

## [1.8] (Mapping change, requires reindex) - 2016-01-19
### Overview
- ElasticPress 1.8 adds a bunch of mapping changes for accomplishing more complex WP_Query functions such as filtering by term id and sorting by any Elasticsearch property. Version 1.8 also speeds up post syncing dramatically through non-blocking queries. Full list of enhancements and bug fixes:

### Added
- Filter around the search fuzziness argument. Props [@dkotter](https://github.com/dkotter).
- Make post indexing a non-blocking query. Props [@cmmarslender](https://github.com/cmmarslender).
- Log queries for debugging. Makes [ElasticPress Debug Bar](https://github.com/10up/debug-bar-elasticpress) plugin possible.
- Make `posts_per_page = -1` possible.
- Support term id and name tax queries.
- raw/sortable to property to term mapping. Props [@sc0ttkclark](https://github.com/sc0ttkclark)
- raw/sortable property to meta mapping. Props [@sc0ttkclark](https://github.com/sc0ttkclark)
- raw/sortable to author display name and login

### Fixed
- Post deletion. Props [@lukaspawlik](https://github.com/lukaspawlik).
- Properly flush cache with `wp_cache_flush`. Props [@jstensved](https://github.com/jstensved)
- When directly comparing meta values in a meta query, use the `raw` property instead of `value`.
- Support arbitrary document paths in orderby. Props [@sc0ttkclark](https://github.com/sc0ttkclark).

## [1.7] (Mapping change, requires reindex) - 2015-12-11
### Overview
- ElasticPress 1.7 restructures meta mapping for posts for much more flexible meta queries. The `post_meta` Elasticsearch post property has been left for backwards compatibility. As of this version, post meta will be stored in the `meta` Elasticsearch property. `meta` is structured as follows:
- When querying posts, you will get back `meta.value`. However, if you plan to mess with the new post mapping, it's important to understand the intricacies.
- The real implications of this is in `meta_query`. You can now effectively search by meta types. See the new section in README.md for details on this.

### Added
- `meta.value` (string)
- `meta.raw` (unanalyzed string)
- `meta.long` (unanalyzed number)
- `meta.double` (unanalyzed number)
- `meta.boolean` (unanalyzed number)
- `meta.date` (unanalyzed yyyy-MM-dd date)
- `meta.datetime` (unanalyzed yyyy-MM-dd HH:mm:ss datetime)
- `time` (unanalyzed HH:mm:ss time)
- Index posts according to post type. Props [@sc0ttkclark](https://github.com/sc0ttkclark)

### Fixed
- Prevent missed post indexing when duplicate post dates. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Complex meta types are automatically serialized upon storage.

## [1.6.2] - 2015-11-12
### Notice
- Mapping change, requires reindex

### Overview
- ElasticPress 1.6.2 fixes ALL backwards compatibility issues with Elasticsearch 2.0

### Changed
- Uses string instead of array for post type term when there is only one term.

### Removed
- `fuzzy_like_this` query and uses `multi_match` instead.

## [1.6.1] - 2015-11-09
### Notice
- Mapping change, requires reindex

### Overview
- ElasticPress 1.6.1 fixes mapping backwards compatibility issues with Elasticsearch 2.0:

### Removed
- Fields field type from object typed fields as they should be called properties.
- Path from object field types.

## [1.6] - 2015-08-31
### Overview
- ElasticPress 1.6 contains a number of important enhancements and bug fixes. Most notably, we now support Elasticsearch fallback hosts and the indexing of attachments.

### Added
- Blog id to `ep_index_name` filter. Props [@kovshenin](https://github.com/kovshenin)
- Support post caching in search
- Recursive term indexing for heirarchal taxonomies. Props [@tuanmh](https://github.com/tuanmh)
- Enable indexing of attachments
- Support fallback hosts in case main EP host is unavailable. Props [@chriswiegman](https://github.com/chriswiegman)
- `ep_retrieve_the_post` filter to support relevancy score manipulation. Props [@matthewspencer](https://github.com/matthewspencer)
- Make search results filterable. Props [@chriswiegman](https://github.com/chriswiegman)

### Fixed
- Clean up PHP Code Sniffer errors. Props [@chriswiegman](https://github.com/chriswiegman)
- Properly document Elasticsearch version
- Abide by `exclude_from_search` instead of `public` when indexing post types. Props [@allan23](https://github.com/allan23) and [@ghosttoast](https://github.com/ghosttoast).
- Allow posts to be indexed with invalid date values. Props [@tuanmh](https://github.com/tuanmh)
- Support `ep_post_sync_kill` filter in bulk indexing. Props [@Stayallive](https://github.com/Stayallive)

## [1.5.1] - 2015-07-06
### Fixed
- Prevent notices from being thrown when non-existent index properties are accessed. This was happening for people how upgraded to 1.5 without doing a re-index. Props [@allan23](https://github.com/allan23)

## [1.5] - 2015-06-25
### Added
- Support for category_name WP_Query parameter. Props [@ocean90](https://github.com/ocean90)
- Support limiting sites in network wide commands. Props [@bordoni](https://github.com/bordoni)
- Support for method to un-integrate WP_Query. Props [@kingkool68](https://github.com/kingkool68)
- Support `cache_results` in WP_Query
- Action prior to starting WP-CLI index command
- Missing headers to WP_CLI commands. Props [@chriswiegman](https://github.com/chriswiegman)
- Improve error reporting in bulk indexing during bad ES requests.
- Filter to modify request headers. Props [@tuanmh](https://github.com/tuanmh)
- Prevent bulk index from sending useless error emails. Props [@cmmarslender](https://github.com/cmmarslender)
- --offset parameter to cli indexing command. [Stayallive](https://github.com/stayallive)
- Support like query in post meta. Props [@tuanmh](https://github.com/tuanmh)
- Sanitization fixes for PHPCS. Props [@mphillips](https://github.com/mphillips)
- Filter to set default sort order. Props [@HKandulla](https://github.com/HKandulla)
- MySQL DB completely removed from integrated ElasticPress WP Query. Props [@EduardMaghakyan](https://github.com/EduardMaghakyan) and [@crebacz](https://github.com/crebacz)

### Changed
- Syncing hook to play better with plugins. Props [@jonathanbardo](https://github.com/jonathanbardo)

### Fixed
- is_search check notice. Props [@allenmoore](https://github.com/allenmoore) and [@allan23](https://github.com/allan23)
- Prevent direct access to any PHP files. Props [@joelgarciajr84](https://github.com/joelgarciajr84)
- Fields not being loaded from ES. Props [@stayallive](https://github.com/stayallive)
- Inclusive check in date_query integration. Props [@EduardMaghakyan](https://github.com/EduardMaghakyan)

## [1.4] - 2015-05-18
### Added
- `date_query` and date parameters now supported in WP_Query. Props [@joeyblake](https://github.com/joeyblake) and [@eduardmaghakyan](https://github.com/eduardmaghakyan)
- Make all request headers filterable
- EP API key to all requests as a header if a constant is defined. Props [@zamoose](https://github.com/zamoose)
- Index exists function; remove indexes on blog deletion/deactivation. Props [@joeyblake](https://github.com/joeyblake)
- Refactor wp-cli stats for multisite. Props [@jaace](https://github.com/jaace)
- Index mappings array moved to separate file. Props [@mikaelmattsson](https://github.com/mikaelmattsson)
- Support meta inequality comparisons. Props [@psorensen](https://github.com/psorensen)

### Removed
- Default shard and indices configuration numbers but maintain backwards compatibility. Props [@zamoose](https://github.com/zamoose)

### Fixed
- Duplicate sync post hooks separated. Props [@superdummy](https://github.com/superdummy)
- Don't send empty index error emails. Props [@cmmarslender](https://github.com/cmmarslender)
- Wrong author ID in post data. Props [@eduardmaghakyan](https://github.com/eduardmaghakyan)

## [1.3.1] - 2015-04-09
### Added
- Support `date` in WP_Query `orderby`. Props [@psorensen](https://github.com/psorensen)

## [1.3] - 2015-02-03
### Added
- Support `meta_query` in WP_Query integration
- Improved documentation. Each WP-CLI command has been documented
- `elasticsearch` property to global post object to assist in debugging
- `ep_integrate` param added to allow for WP_Query integration without search. (Formally called ep_match_all)
- Filter added for post statuses (defaults to `publish`). Change the sync mechanism to make sure it takes all post statuses into account. Props [@jonathanbardo](https://github.com/jonathanbardo)

### Fixed
- Check if failed post exists in indexing. Props [@elliot-stocks](https://github.com/elliott-stocks)
- Properly check if setup is defined in indexing. Props [@elliot-stocks](https://github.com/elliott-stocks)
- Add WP_Query integration on init rather than plugins loaded. Props [@adamsilverstein](https://github.com/adamsilverstein)
- Properly set global post object post type in loop. Props [@tott](https://github.com/tott)
- Do not check if index exists on every page load. Refactor so we can revert to MySQL after failed ES ping.
- Make sure we check `is_multisite()` if `--network-wide` is provided. Props [@ivankruchkoff](https://github.com/ivankruchkoff)
- Abide by the `exclude_from_search` flag from post type when running search queries. Props [@ryanboswell](https://github.com/ryanboswell)
- Correct mapping of `post_status` to `not_analyzed` to allow for filtering of the search query (will require a re-index). Props [@jonathanbardo](https://github.com/jonathanbardo)

## [1.2] - 2014-12-05
### Added
- Allow number of shards and replicas to be configurable.
- Filter and disable query integration on a per query basis.
- Support orderby` parameter in `WP_Query

### Changed
- Improved searching algorithm. Favor exact matches over fuzzy matches.
- Query stack implementation to allow for query nesting.
- Delete action to action_delete_post instead of action_trash_post
- Improve unit testing for query ordering.

### Removed
- _boost from mapping. _boost is deprecated by Elasticsearch.

### Fixed
- We don't want to add the like_text query unless we have a non empty search string. This mimcs the behavior of MySQL or WP which will return everything if s is empty.

## [1.1] - 2014-10-27
### Added
- Add support for post_title and post_name orderby parameters in `WP_Query` integration. Add support for order parameters.

### Changed
- Refactored `is_alive`, `is_activated`, and `is_activated_and_alive`. We now have functions `is_activated`, `elasticsearch_alive`, `index_exists`, and `is_activated`. This refactoring helped us fix #150.

## [1.0] - 2014-10-20
### Added
- Support `search_fields` parameter. Support author, title, excerpt, content, taxonomy, and meta within this parameter.
- Check for valid blog ids in index names
- `sites` WP_Query parameter to allow for search only on specific blogs

### Changed
- Move all management functionality to WP-CLI commands
- Disable sync during import
- Improved bulk error handling
- Improved unit test coverage

### Removed
- Remove ES_Query and support everything through WP_Query
- `ep_last_synced` meta
- Syncing taxonomy

## [0.9.3] - 2014-09-26
### Added
- Better documentation surrounding `WP_Query` parameters (props @tlovett1)
- Option to allow for using `match_all` (props @colegeissinger for suggestion)
- Better tests for some `WP_Query` parameters (props @tlovett1)
- Allow for manual control over search integration
- Support for passing an array of sites to search against (props @tlovett1)
- Filter for controlling whether or not ElasticPress is enabled during a `wp_query` request
- Filter to allow adjusting which fields are searched (`ep_search_fields`)

### Changed
- Prevented filtering `WP_Query` in admin (props @cmmarslender)
- Updated tests to better conform to WordPress repo 5.2 compatibility (props @tlovett1)
- Made running re-indexing commands simpler and easier by adding support for a new `--setup` flag on the `index` command
- Disable search integration during syncing

### Fixed
- Bug that would cause a post to stay in the index when a post was unpublished
- Bug that would cause site to be improperly switched after a `wp_reset_postdata` while not in the loop
- Bug that would cause EP to individually sync each post during an import - disabled syncing during import - requires a full re-index after import

## [0.9.2] - 2014-09-11
### Added
- Wrapper method for wp_get_sites, added filter
- Ability to change scope of search to other sites in network
- tax_query support.

### Changed
- Aggregation filter update

## [0.9.1] - 2014-09-05
### Added
- Action to allow for retrieval of raw response
- Filter to retrieve aggregations
- Pagination tests
- ep_min_similarity and ep_formatted_args filters
- ep_search_fields filter for adding custom search fields
- Filter to allow for specific site selection on multisite indexing

### Changed
- Adjust default fuzziness to .75 instead of .5

### Removed
- Main query check on ep wp query integration

## [0.9] - 2014-09-03
### Added
- Make labels clickable in admin
- Setup plugin textdomain; POT file for translation; localize stray string in cron
- Tests for is_alive function
- search_meta key param support to ES_Query
- Test WP Query integration on multisite setup
- Flush and re-put mapping on admin sync request
- WP Query integration

### Changed
- Simplify sync
- do_scheduled_syncs into do_syncs
- Make config files static

### Removed
- EP hidden taxonomy

### Fixed
- Cron stuff
- Statii
- Type coercion in equality checks

## [0.1.2] - 2014-06-27
### Added
- Support ES_Query parameter that designates post meta entries to be searched
- Escape post ID and site ID in API calls
- Additional tests
- Translation support
- is_alive function for checking health status of Elasticsearch server

### Changed
- Only index public taxonomies
- Renamed `statii` to `status`

### Fixed
- Escaping issues

## 0.1.0 - Unknown
- Initial plugin release

[Unreleased]: https://github.com/10up/ElasticPress/compare/master...develop
[3.6.3]: https://github.com/10up/ElasticPress/compare/3.6.2...3.6.3
[3.6.2]: https://github.com/10up/ElasticPress/compare/3.6.1...3.6.2
[3.6.1]: https://github.com/10up/ElasticPress/compare/3.6.0...3.6.1
[3.6.0]: https://github.com/10up/ElasticPress/compare/3.5.6...3.6.0
[3.5.6]: https://github.com/10up/ElasticPress/compare/3.5.5...3.5.6
[3.5.5]: https://github.com/10up/ElasticPress/compare/3.5.4...3.5.5
[3.5.4]: https://github.com/10up/ElasticPress/compare/3.5.3...3.5.4
[3.5.3]: https://github.com/10up/ElasticPress/compare/3.5.2...3.5.3
[3.5.2]: https://github.com/10up/ElasticPress/compare/3.5.1...3.5.2
[3.5.1]: https://github.com/10up/ElasticPress/compare/3.5...3.5.1
[3.5.0]: https://github.com/10up/ElasticPress/compare/3.4.3...3.5
[3.4.3]: https://github.com/10up/ElasticPress/compare/3.4.2...3.4.3
[3.4.2]: https://github.com/10up/ElasticPress/compare/3.4.1...3.4.2
[3.4.1]: https://github.com/10up/ElasticPress/compare/3.4...3.4.1
[3.4.0]: https://github.com/10up/ElasticPress/compare/3.3...3.4
[3.3.0]: https://github.com/10up/ElasticPress/compare/3.2.6...3.3
[3.2.6]: https://github.com/10up/ElasticPress/compare/3.2.5...3.2.6
[3.2.5]: https://github.com/10up/ElasticPress/compare/3.2.4...3.2.5
[3.2.4]: https://github.com/10up/ElasticPress/compare/3.2.3...3.2.4
[3.2.3]: https://github.com/10up/ElasticPress/compare/3.2.2...3.2.3
[3.2.2]: https://github.com/10up/ElasticPress/compare/3.2.1...3.2.2
[3.2.1]: https://github.com/10up/ElasticPress/compare/3.2...3.2.1
[3.2.0]: https://github.com/10up/ElasticPress/compare/3.1.1...3.2
[3.1.1]: https://github.com/10up/ElasticPress/compare/3.1...3.1.1
[3.1.0]: https://github.com/10up/ElasticPress/compare/3.0.3...3.1
[3.0.3]: https://github.com/10up/ElasticPress/compare/3.0.2...3.0.3
[3.0.2]: https://github.com/10up/ElasticPress/compare/3.0.1...3.0.2
[3.0.1]: https://github.com/10up/ElasticPress/compare/3.0...3.0.1
[3.0]: https://github.com/10up/ElasticPress/compare/2.8.2...3.0
[2.8.2]: https://github.com/10up/ElasticPress/compare/2.8.1...2.8.2
[2.8.1]: https://github.com/10up/ElasticPress/compare/2.8.0...2.8.1
[2.8.0]: https://github.com/10up/ElasticPress/compare/2.7.0...2.8.0
[2.7.0]: https://github.com/10up/ElasticPress/releases/tag/2.7.0
[2.6.1]: https://plugins.trac.wordpress.org/changeset/1929875/elasticpress
[2.6]: https://github.com/10up/ElasticPress/compare/2.5.2...2.6
[2.5.2]: https://github.com/10up/ElasticPress/compare/2.5.1...2.5.2
[2.5.1]: https://github.com/10up/ElasticPress/compare/2.5...2.5.1
[2.5]: https://github.com/10up/ElasticPress/compare/2.4.2...2.5
[2.4.2]: https://github.com/10up/ElasticPress/compare/2.4.1...2.4.2
[2.4.1]: https://github.com/10up/ElasticPress/compare/2.4...2.4.1
[2.4]: https://github.com/10up/ElasticPress/compare/2.3.2...2.4
[2.3.2]: https://github.com/10up/ElasticPress/compare/2.3.1...2.3.2
[2.3.1]: https://github.com/10up/ElasticPress/compare/2.3...2.3.1
[2.3]: https://github.com/10up/ElasticPress/compare/2.2.1...2.3
[2.2.1]: https://github.com/10up/ElasticPress/compare/2.2...2.2.1
[2.2]: https://github.com/10up/ElasticPress/compare/2.1.2...2.2
[2.1.2]: https://github.com/10up/ElasticPress/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/10up/ElasticPress/compare/2.1...2.1.1
[2.1]: https://github.com/10up/ElasticPress/compare/2.0.1...2.1
[2.0.1]: https://github.com/10up/ElasticPress/compare/2.0...2.0.1
[2.0]: https://github.com/10up/ElasticPress/compare/1.9.1...2.0
[1.9.1]: https://github.com/10up/ElasticPress/compare/1.9...1.9.1
[1.9]: https://github.com/10up/ElasticPress/compare/1.8...1.9
[1.8]: https://github.com/10up/ElasticPress/compare/1.7...1.8
[1.7]: https://github.com/10up/ElasticPress/compare/1.6.2...1.7
[1.6.2]: https://github.com/10up/ElasticPress/compare/1.6.1...1.6.2
[1.6.1]: https://github.com/10up/ElasticPress/compare/1.6...1.6.1
[1.6]: https://github.com/10up/ElasticPress/compare/1.5...1.6
[1.5.1]: https://github.com/10up/ElasticPress/compare/1.5...1.5.1
[1.5]: https://github.com/10up/ElasticPress/compare/1.4...1.5
[1.4]: https://github.com/10up/ElasticPress/compare/v1.3.1...1.4
[1.3.1]: https://github.com/10up/ElasticPress/compare/v1.3...v1.3.1
[1.3]: https://github.com/10up/ElasticPress/compare/v1.2...v1.3
[1.2]: https://github.com/10up/ElasticPress/compare/v1.1...v1.2
[1.1]: https://github.com/10up/ElasticPress/compare/v1.0...v1.1
[1.0]: https://github.com/10up/ElasticPress/compare/v0.9.3...v1.0
[0.9.3]: https://github.com/10up/ElasticPress/compare/0.9.2...v0.9.3
[0.9.2]: https://github.com/10up/ElasticPress/compare/0.9.1...0.9.2
[0.9.1]: https://github.com/10up/ElasticPress/compare/0.9...0.9.1
[0.9]: https://github.com/10up/ElasticPress/compare/0.1.2...0.9
[0.1.2]: https://github.com/10up/ElasticPress/releases/tag/0.1.2
