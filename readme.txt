=== ElasticPress ===
Contributors: tlovett1, aaronholbrook, ChrisWiegman, sc0ttkclark, collinsinternet, dkotter, 10up
Author URI: http://10up.com
Plugin URI: https://github.com/10up/ElasticPress
Tags: performance, slow, search, elasticsearch, fuzzy, facet, aggregation, searching, autosuggest, suggest, elastic, advanced search, woocommerce, related posts
Requires at least: 3.7.1
Tested up to: 4.8
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A fast and flexible search and query engine for WordPress.

== Description ==
ElasticPress, a fast and flexible search and query engine for WordPress, enables WordPress to find or “query” relevant content extremely fast through a variety of highly customizable features. WordPress out-of-the-box struggles to analyze content relevancy and can be very slow. ElasticPress supercharges your WordPress website making for happier users and administrators.

Here is a list of the amazing ElasticPress features included in the plugin:

__Search__: Instantly find the content you’re looking for. The first time.

__WooCommerce__: “I want a cotton, woman’s t-shirt, for under $15 that’s in stock.” Faceted product browsing strains servers and increases load times. Your buyers can find the perfect product quickly, and buy it quickly.

__Related Posts__: ElasticPress understands data in real time, so it can instantly deliver engaging and precise related content with no impact on site performance.

__Protected Content__: Optionally index all of your content, including private and unpublished content, to speed up searches and queries in places like the administrative dashboard.

__Documents__: Indexes text inside of popular file types, and adds those files types to search results.

Please refer to [Github](https://github.com/10up/ElasticPress) for detailed usage instructions and documentation.

== Installation ==
1. First, you will need to properly [install and configure](https://www.elastic.co/guide/en/elasticsearch/reference/current/setup.html) Elasticsearch.
2. Activate the plugin in WordPress.
3. In the ElasticPress settings page, input your Elasticsearch host.
4. Enjoy!

== Changelog ==

= 2.3 =

Version 2.3 introduces the Documents feature which indexes text inside of popular file types, and adds those files types to search results. We've also officially added support for Elasticsearch 5.3.

### Enhancements
* Documents feature
* Enable multiple feature status messages
* Disable dashboard sync via constant: `define( 'EP_DASHBOARD_SYNC', false );`. Props [rveitch](https://github.com/rveitch).
* Add filter for custom WooCommerce taxonomies. Props [kallehauge](https://github.com/kallehauge).
* Support WooCommerce `product_type` taxonomy. Props [kallehauge](https://github.com/kallehauge).

### Bug Fixes
* Fix WP-CLI `--no-bulk` number of posts indexed message. Props [ivankristianto](https://github.com/ivankristianto).
* Honor `ep_integrate` in WooCommerce queries. Props [ivankristianto](https://github.com/ivankristianto).
* Properly check when ES results are empty. Props [lukaspawlik](https://github.com/lukaspawlik)
* Incorrect `found_posts` set in query when ES is unavailable. Props [lukaspawlik](https://github.com/lukaspawlik)

= 2.2.1 =

Version 2.2.1 is a bug fix release. Here are a listed of issues that have been resolved:

* Fix dashboard syncing delayed start issues.
* If plugins endpoint errors, try root endpoint to get the ES version.
* Make sure orderby is correct for default WooCommerce sorting. Props [ivankristianto](https://github.com/ivankristianto).
* Remove operator=>AND unneed execution code.
* Stop dashboard sync if error occurs in the middle. Props [ivankristianto](https://github.com/ivankristianto).
* Add `EP_INDEX_PREFIX` constant. If set, index names will be prefixed with the constant. Props [allan23](https://github.com/allan23).
* Increase total field limit to 5000 and add filter. Props [ssorathia](https://github.com/ssorathia).
* Prevent EP from auto-activating a feature that was force deactivated
* Prevent massive field Elasticsearch error when indexing large strings
* Increase max result window size to 1000000 and add filter.

= 2.2 (Requires re-index) =

Version 2.2 rethinks the module process to make ElasticPress a more complete query engine solution. Modules are now auto-on and really just features. Why would anyone want to not use amazing functionality that improves speed and relevancy on their website? Features (previously modules) can of course be overriden and disabled. Features that don't have their minimum requirements met, such as a missing plugin dependency, are auto-disabled.

We've bumped the minimum Elasticsearch version to 1.7 (although we strongly recommend 2+). The maximum tested version of Elasticsearch is version 5.2. If you are running Elasticsearch outside this version range, you will see a warning in the dashboard.

### Enhancements

* __(Breaking change)__ Module registration API changed. See `register_module` in `classes/class-ep-modules.php`.
* __(Breaking change)__ Related posts are now in a widget instead of automatically being appending to content.
* __(Breaking change)__ Admin module renamed to Protected Content.
* Admin warning if current Elasticsearch version is not between the min/max supported version. Version 2.2 supports versions 1.3 - 5.1.
* Auto-reindex on versions requiring reindex.
* User friendly admin notifications for ElasticPress not set up, first sync needed, and feature auto activation.
* Protected Content feature applies to all features. This means if Protected Content isn't active, search or WooCommerce integration won't happen in the admin.
* Add support for post_mime_type. Props [Ritesh-patel](https://github.com/Ritesh-patel)

### Bug Fixes

* Back compat with old `ep_search` function.
* Respect indexable post types in WooCommerce feature
* New product drafts not showing in WooCommerce admin list
* WooCommerce feature breaking image search in media library. Props [Ritesh-patel](https://github.com/Ritesh-patel)
* WooCommerce order search broken
* Stop the insansity made private. Props [sc0ttclark](https://github.com/sc0ttclark)
* Fix multidimensional meta querys. Props [Ritesh-patel](https://github.com/Ritesh-patel).
* Properly show bulk index errors in WP-CLI
* Update ep_delete_post, include $post_type argument. Props [Ritesh-patel](https://github.com/Ritesh-patel)
* Fix post_type product getting set in any WP_Query if tax_query is provided in WooCommerce feature. Props [Ritesh-patel](https://github.com/Ritesh-patel)
* Adds 'number' param to satisfy WP v4.6+ fixing get_sites call. Props [rveitch](https://github.com/rveitch)
* Order by proper relevancy in WooCommerce product search. Props [ivankristianto](https://github.com/ivankristianto)
* Fix recursion fatal error due to oembed discovery during syncing. Props [ivankristianto](https://github.com/ivankristianto)

= 2.1.2 (Requires re-index) =

* Separate mapping for ES 5.0+
* Fix some unit tests

= 2.1.1 =

* Fix PHP 5.3 errors
* Properly show syncing button module placeholder during sync

= 2.1 =

* Redo UI
* Make plugin modular
* Remove unnecessary back up hosts code
* Bundle existing modules into plugin
* Support `meta_key` and `meta_value`
* Order by `meta_value_num`
* Properly support `post_parent = 0`. Props [tuanmh](https://github.com/tuanmh)
* Add search scope file. Props [rveitch](https://github.com/rveitch)
* Support WP_Query `post_status`. Props [sc0ttclark](https://github.com/sc0ttkclark)

### Backward compat breaks:

* Move ep_admin_wp_query_integration to search integration only. EP integration by default is available everywhere.
* Remove `keep alive` setting
* Remove setting to integrate with search (just activate the module instead)
* Back up hosts code removed
* Remove active/inactive state. Rather just check if an index is going on our not.

### Bug fixes
* Fix `post__in` support
* Fix `paged` overwriting `offset`
* Fix integer and comma separated string `sites` WP_Query processing. Props [jaisgit](https://github.com/jaisgit).

= 2.0.1 =

### Bug fixes
* Don't load settings on front end. This fixes a critical bug causing ElasticPress to check the Elasticsearch connection on the front end.

= 2.0 =

10up ships ElasticPress 2.0 with __radical search algorithm improvements__ and a __more comprehensive integration of WP_Query__. ElasticPress is now even closer to supporting the complete WP_Query API. This version also improves upon post syncing ensuring that post meta updates are synced to Elasticsearch, adds a number of important hooks, and, of course, fixes some pesky bugs.

### Enhancements
* Radical search algorithm improvements for more relevant results (see [#508](https://github.com/10up/ElasticPress/pull/508) for details)
* Support meta `BETWEEN` queries.
* Improve GUI by disabling index status meta box text and improving instructions. Props [ivanlopez](https://github.com/ivanlopez)
* Support `OR` relation for tax queries.
* Sync post to Elasticsearch when meta is added/updated.
* Support all taxonomies as root WP_Query arguments. Props [tuanmh](https://github.com/tuanmh)
* Add `ID` field to Elasticsearch mapping
* Support `post_parent` WP_Query arguments. Props [tuanmh](https://github.com/tuanmh)
* Add filter to disable printing of post index status. Props [tuanmh](https://github.com/tuanmh)
* Add useful CLI hooks
* Add a filter to bypass permission checking on sync (critical for front end updates)

### Bugs
* Consider all remote request 20x responses as successful. Props [tuanmh](https://github.com/tuanmh)
* Fix plugin localization. Props [mustafauysal](https://github.com/mustafauysal)
* Do query logging by default. Props [lukaspawlik](https://github.com/lukaspawlik)
* Fix cannot redeclare class issue. Props [tuanmh](https://github.com/tuanmh)
* Fix double querying Elasticsearch by ignoring `category_name` when `tax_query` is present.
* Fix post deletion endpoint URL. Props [lukaspawlik](https://github.com/lukaspawlik)

A special thanks goes out to [Tuan Minh Huynh](https://github.com/tuanmh) and everyone else for contributions to version 2.0.


= 1.9.1 =

Quick bug fix version to address the GUI not working properly when plugin is not network enabled within multisite. Props to [Ivan Lopez](https://github.com/ivanlopez)

= 1.9 =

ElasticPress 1.9 adds in an admin UI, where you can set your Elasticsearch Host and run your index command, without needing to us WP-CLI. Version 1.9 also adds in some performance improvements to reduce memory consumption during indexing. Full list of enhancements and bug fixes:

### Enhancements:
* Add in an Admin GUI to handle indexing. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* Add option to not disable ElasticPress while indexing. Props [lukaspawlik](https://github.com/lukaspawlik).
* Allow filtering of which post types we want to search for. Props [rossluebe](https://github.com/rossluebe).
* Remove composer.lock from the repo. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* Ensure both PHPUnit and WP-CLI are available in the development environment. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* User lower-case for our composer name, so packagist can find us. Props [johnpbloch](https://github.com/johnpbloch).
* Check query_vars, not query to determine status. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* Improve memory usage during indexing and fix unnecessary cache flushes. Props [cmmarslender](https://github.com/cmmarslender).
* Further reduce memory usage during indexing. Props [lukaspawlik](https://github.com/lukaspawlik).
* Add post__in and post__not_in documentation. Props [mgibbs189](https://github.com/mgibbs189).
* Add Elasticsearch Shield authentication headers if constant is set. Props [rveitch](https://github.com/rveitch).

### Bugs:
* Fix the --no-bulk indexing option. Props [lukaspawlik](https://github.com/lukaspawlik).
* Fixed an error that occurs if no Elasticsearch host is running. Props [petenelson](https://github.com/petenelson).
* Fixed an exception error. Props [dkotter](https://github.com/dkotter).
* Fixed the WP-CLI status command. Props [dkotter](https://github.com/dkotter).

= 1.8 (Mapping change, requires reindex) =

ElasticPress 1.8 adds a bunch of mapping changes for accomplishing more complex WP_Query functions such as filtering by term id and sorting by any Elasticsearch property. Version 1.8 also speeds up post syncing dramatically through non-blocking queries. Full list of enhancements and bug fixes:

### Enhancements:

* Add a filter around the search fuzziness argument. Props [dkotter](https://github.com/dkotter).
* Make post indexing a non-blocking query. Props [cmmarslender](https://github.com/cmmarslender).
* Log queries for debugging. Makes [ElasticPress Debug Bar](https://github.com/10up/debug-bar-elasticpress) plugin possible.
* Make `posts_per_page = -1` possible.
* Support term id and name tax queries.
* Add raw/sortable to property to term mapping. Props [sc0ttkclark](https://github.com/sc0ttkclark)
* Add raw/sortable property to meta mapping. Props [sc0ttkclark](https://github.com/sc0ttkclark)
* Add raw/sortable to author display name and login

### Bugs:

* Fix post deletion. Props [lukaspawlik](https://github.com/lukaspawlik).
* Properly flush cache with `wp_cache_flush`. Props [jstensved](https://github.com/jstensved)
* When directly comparing meta values in a meta query, use the `raw` property instead of `value`.
* Support arbitrary document paths in orderby. Props [sc0ttkclark](https://github.com/sc0ttkclark).

= 1.7 (Mapping change, requires reindex) =

ElasticPress 1.7 restructures meta mapping for posts for much more flexible meta queries. The `post_meta` Elasticsearch post property has been left for backwards compatibility. As of this version, post meta will be stored in the `meta` Elasticsearch property. `meta` is structured as follows:

* `meta.value` (string)
* `meta.raw` (unanalyzed string)
* `meta.long` (unanalyzed number)
* `meta.double` (unanalyzed number)
* `meta.boolean` (unanalyzed number)
* `meta.date` (unanalyzed yyyy-MM-dd date)
* `meta.datetime` (unanalyzed yyyy-MM-dd HH:mm:ss datetime)
* `time` (unanalyzed HH:mm:ss time)

When querying posts, you will get back `meta.value`. However, if you plan to mess with the new post mapping, it's important to understand the intricacies.

The real implications of this is in `meta_query`. You can now effectively search by meta types. See the new section in README.md for details on this.

1.7 also contains the following bugs/enhancements:

* (Bug) Prevent missed post indexing when duplicate post dates. Props [lukaspawlik](https://github.com/lukaspawlik)
* (Bug) Complex meta types are automatically serialized upon storage.
* (Enhancement) Index posts according to post type. Props [sc0ttkclark](https://github.com/sc0ttkclark)

= 1.6.2 (Mapping change, requires reindex) =

ElasticPress 1.6.2 fixes ALL backwards compatibility issues with Elasticsearch 2.0:

* Removes `fuzzy_like_this` query and uses `multi_match` instead.
* Uses string instead of array for post type term when there is only one term.

= 1.6.1 (Mapping change, requires reindex) =

ElasticPress 1.6.1 fixes mapping backwards compatibility issues with Elasticsearch 2.0:

* Removes the fields field type from object typed fields as they should be called properties.
* Remove path from object field types.

= 1.6 =

ElasticPress 1.6 contains a number of important enhancements and bug fixes. Most notably, we now support Elasticsearch fallback hosts and the indexing of attachments.

### Bug Fixes:

* Clean up PHP Code Sniffer errors. Props [chriswiegman](https://github.com/chriswiegman)
* Properly document Elasticsearch version
* Abide by `exclude_from_search` instead of `public` when indexing post types. Props [allan23](https://github.com/allan23) and [ghosttoast](https://github.com/ghosttoast).
* Allow posts to be indexed with invalid date values. Props [tuanmh](https://github.com/tuanmh)
* Support `ep_post_sync_kill` filter in bulk indexing. Props [Stayallive](https://github.com/Stayallive)

### Enhancements:

* Add blog id to `ep_index_name` filter. Props [kovshenin](https://github.com/kovshenin)
* Support post caching in search
* Add recursive term indexing for heirarchal taxonomies. Props [tuanmh](https://github.com/tuanmh)
* Enable indexing of attachments
* Support fallback hosts in case main EP host is unavailable. Props [chriswiegman](https://github.com/chriswiegman)
* Add `ep_retrieve_the_post` filter to support relevancy score manipulation. Props [matthewspencer](https://github.com/matthewspencer)
* Make search results filterable. Props [chriswiegman](https://github.com/chriswiegman)

= 1.5.1 =

### Bug Fixes:

* Prevent notices from being thrown when non-existent index properties are accessed. This was happening for people how upgraded to 1.5 without doing a re-index. Props [allan23](https://github.com/allan23)

= 1.5 =

### Bug Fixes:

* Prevent direct access to any PHP files. Props [joelgarciajr84](https://github.com/joelgarciajr84)
* Fixed fields not being loaded from ES. Props [stayallive](https://github.com/stayallive)
* Fixed inclusive check in date_query integration. Props [EduardMaghakyan](https://github.com/EduardMaghakyan)

### Enhancements:

* Add support for category_name WP_Query parameter. Props [ocean90](https://github.com/ocean90)
* Support limiting sites in network wide commands. Props [bordoni](https://github.com/bordoni)
* Add support for method to un-integrate WP_Query. Props [kingkool68](https://github.com/kingkool68)
* Support `cache_results` in WP_Query
* Add action prior to starting WP-CLI index command
* Add missing headers to WP_CLI commands. Props [chriswiegman](https://github.com/chriswiegman)
* Improve error reporting in bulk indexing during bad ES requests.
* Fix is_search check notice. Props [allenmoore](https://github.com/allenmoore) and [allan23](https://github.com/allan23)
* Added a filter to modify request headers. Props [tuanmh](https://github.com/tuanmh)
* Prevent bulk index from sending useless error emails. Props [cmmarslender](https://github.com/cmmarslender)
* Add --offset parameter to cli indexing command. [Stayallive](https://github.com/stayallive)
* Change the syncing hook to play better with plugins. Props [jonathanbardo](https://github.com/jonathanbardo)
* Support like query in post meta. Props [tuanmh](https://github.com/tuanmh)
* Sanitization fixes for PHPCS. Props [mphillips](https://github.com/mphillips)
* Added filter to set default sort order. Props [HKandulla](https://github.com/HKandulla)
* MySQL DB completely removed from integrated ElasticPress WP Query. Props [EduardMaghakyan](https://github.com/EduardMaghakyan) and [crebacz](https://github.com/crebacz)

= 1.4 =

### Bug Fixes:

* Duplicate sync post hooks separated. Props [superdummy](https://github.com/superdummy)
* Don't send empty index error emails. Props [cmmarslender](https://github.com/cmmarslender)
* Remove default shard and indices configuration numbers but maintain backwards compatibility. Props [zamoose](https://github.com/zamoose)
* Fix wrong author ID in post data. Props [eduardmaghakyan](https://github.com/eduardmaghakyan)

### Enhancements:

* `date_query` and date parameters now supported in WP_Query. Props [joeyblake](https://github.com/joeyblake) and [eduardmaghakyan](https://github.com/eduardmaghakyan)
* Make all request headers filterable
* Add EP API key to all requests as a header if a constant is defined. Props [zamoose](https://github.com/zamoose)
* Add index exists function; remove indexes on blog deletion/deactivation. Props [joeyblake](https://github.com/joeyblake)
* Refactor wp-cli stats for multisite. Props [jaace](https://github.com/jaace)
* Index mappings array moved to separate file. Props [mikaelmattsson](https://github.com/mikaelmattsson)
* Support meta inequality comparisons. Props [psorensen](https://github.com/psorensen)

= 1.3.1 =
* Support `date` in WP_Query `orderby`. Props [psorensen](https://github.com/psorensen)

= 1.3 =
* Support `meta_query` in WP_Query integration
* Improved documentation. Each WP-CLI command has been documented
* Add `elasticsearch` property to global post object to assist in debugging
* `ep_integrate` param added to allow for WP_Query integration without search. (Formally called ep_match_all)
* Filter added for post statuses (defaults to `publish`). Change the sync mechanism to make sure it takes all post statuses into account. Props [jonathanbardo](https://github.com/jonathanbardo)
* Bug fix: check if failed post exists in indexing. Props [elliot-stocks](https://github.com/elliott-stocks)
* Bug fix: properly check if setup is defined in indexing. Props [elliot-stocks](https://github.com/elliott-stocks)
* Bug fix: add WP_Query integration on init rather than plugins loaded. Props [adamsilverstein](https://github.com/adamsilverstein)
* Bug fix: Properly set global post object post type in loop. Props [tott](https://github.com/tott)
* Bug fix: Do not check if index exists on every page load. Refactor so we can revert to MySQL after failed ES ping.
* Bug fix: Make sure we check `is_multisite()` if `--network-wide` is provided. Props [ivankruchkoff](https://github.com/ivankruchkoff)
* Bug fix: Abide by the `exclude_from_search` flag from post type when running search queries. Props [ryanboswell](https://github.com/ryanboswell)
* Bug fix: Correct mapping of `post_status` to `not_analyzed` to allow for filtering of the search query (will require a re-index). Props [jonathanbardo](https://github.com/jonathanbardo)

= 1.2 =
* Allow number of shards and replicas to be configurable.
* Improved searching algorithm. Favor exact matches over fuzzy matches.
* Query stack implementation to allow for query nesting.
* Filter and disable query integration on a per query basis.
* Support orderby` parameter in `WP_Query
* (Bug) We don't want to add the like_text query unless we have a non empty search string. This mimcs the behavior of MySQL or WP which will return everything if s is empty.
* (Bug) Change delete action to action_delete_post instead of action_trash_post
* (Bug) Remove _boost from mapping. _boost is deprecated by Elasticsearch.
* Improve unit testing for query ordering.

= 1.1 =
* Refactored `is_alive`, `is_activated`, and `is_activated_and_alive`. We now have functions `is_activated`, `elasticsearch_alive`, `index_exists`, and `is_activated`. This refactoring helped us fix #150.
* Add support for post_title and post_name orderby parameters in `WP_Query` integration. Add support for order parameters.

= 1.0 =
* Support `search_fields` parameter. Support author, title, excerpt, content, taxonomy, and meta within this parameter.
* Move all management functionality to WP-CLI commands
* Remove ES_Query and support everything through WP_Query
* Disable sync during import
* Check for valid blog ids in index names
* Improved bulk error handling
* No need for `ep_last_synced` meta
* No need for syncing taxonomy
* Improved unit test coverage
* `sites` WP_Query parameter to allow for search only on specific blogs

= 0.1.2 =
* Only index public taxonomies
* Support ES_Query parameter that designates post meta entries to be searched
* Escape post ID and site ID in API calls
* Fix escaping issues
* Additional tests
* Translation support
* Added is_alive function for checking health status of Elasticsearch server
* Renamed `statii` to `status`

= 0.1.0 =
* Initial plugin
