# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/).

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
* Facets widget: Move <div> outside ob_start(). Props [kallehauge](https://github.com/kallehauge).
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

## [3.4]

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

## [3.3] - 2018-12-18

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

## [3.2] - 2019-10-08

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

## [0.1.0]
- Initial plugin release

[Unreleased]: https://github.com/10up/ElasticPress/compare/3.0.3...develop
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
[0.1.0]: https://plugins.trac.wordpress.org/changeset/1010633/elasticpress/
