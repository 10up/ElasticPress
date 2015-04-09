=== ElasticPress ===
Contributors: aaronholbrook, tlovett1, 10up
Author URI: http://10up.com
Plugin URI: https://github.com/10up/ElasticPress
Tags: search, elasticsearch, fuzzy, facet, searching, autosuggest, suggest, elastic, advanced search
Requires at least: 3.7.1
Tested up to: 4.2
Stable tag: 1.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate Elasticsearch with WordPress.

== Description ==
ElasticPress is a WordPress-Elasticsearch integration that overrides default `WP_Query` behavior to give you search results from Elasticsearch instead of MySQL. The plugin is built to be managed entirely via the command line. ElasticPress supports cross-site search in multi-site WordPress installs.

Out of the box, WordPress search is rudimentary at best: Poor performance, inflexible and rigid matching algorithms, inability to search metadata and taxonomy information, no way to determine categories of your results, and most importantly overall poor result relevancy.

Elasticsearch is a search server based on [Lucene](http://lucene.apache.org/). It provides a distributed, multitenant-capable full-text search engine with a [REST](http://en.wikipedia.org/wiki/Representational_state_transfer)ful web interface and schema-free [JSON](http://json.org/) documents.

Coupling WordPress with Elasticsearch allows us to do amazing things with search including:

* Relevant results
* Autosuggest
* Fuzzy matching (catch misspellings as well as 'close' queries)
* Proximity and geographic queries
* Search metadata
* Search taxonomies
* Facets
* Search all sites on a multisite install
* [The list goes on...](http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search.html)

_Note:_ Requires [WP-CLI](http://wp-cli.org/) and [Elasticsearch](http://www.elasticsearch.org/).

Please refer to [Github](https://github.com/10up/ElasticPress) for detailed usage instructions and documentation.

== Installation ==
1. First, you will need to properly [install and configure](http://www.elasticsearch.org/guide/en/elasticsearch/guide/current/_installing_elasticsearch.html) Elasticsearch.
2. Install [WP-CLI](http://wp-cli.org/).
3. Install the plugin in WordPress.

= Configuration =

First, make sure you have Elasticsearch configured properly and WP-CLI setup.

Before configuring the WordPress plugin, you need to decide how you want to run the plugin. The processes for
configuring single site and multi-site cross-site search are slightly different.

= Single Site =
1. Activate the plugin.
2. Define the constant `EP_HOST` in your wp-config.php file with the connection (and port) of your Elasticsearch application.
3. Using WP-CLI, do an initial sync (with mapping) with your ES server by running: `wp elasticpress index --setup`.

= Multi-site Cross-site Search =
1. Network activate the plugin
2. Define the constant `EP_HOST` in your wp-config.php file with the connection (and port) of your Elasticsearch application.
3. Using WP-CLI, do an initial sync (with mapping) with your ES server by running: `wp elasticpress index --setup --network-wide`.

== Changelog ==

= 1.3.1 =
* Support `date` in WP_Query `orderby`.

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
