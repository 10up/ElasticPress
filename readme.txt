=== ElasticPress ===
Contributors: aaronholbrook, tlovett1, 10up
Author URI: http://10up.com
Plugin URI: https://github.com/10up/ElasticPress
Tags: search, elasticsearch, fuzzy, facet, searching, autosuggest, suggest, elastic, advanced search
Requires at least: 3.7.1
Tested up to: 4.0
Stable tag: 1.0
License: MIT
License URI: http://opensource.org/licenses/MIT

Integrate [Elasticsearch](http://www.elasticsearch.org/) with WordPress.

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