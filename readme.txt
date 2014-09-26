=== ElasticPress ===
Contributors: aaronholbrook, tlovett1, 10up
Author URI: http://10up.com
Plugin URI: https://github.com/10up/ElasticPress
Tags: search, elasticsearch, fuzzy, facet, searching, autosuggest, suggest, auto, elastic
Requires at least: 3.7.1
Tested up to: 4.0
Stable tag: 0.9.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate [Elasticsearch](http://www.elasticsearch.org/) with [WordPress](http://wordpress.org/).

== Description ==
Let's face it, WordPress search is rudimentary at best. Poor performance, inflexible and rigid matching algorithms (which means no comprehension of 'close' queries), the inability to search metadata and taxonomy information, no way to determine categories of your results and most importantly the overall relevancy of results is poor.

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

Requires WordPress 3.7.1, PHP 5.2 and Elasticsearch.

== Installation ==
1. First, you will need to properly [install and configure](http://www.elasticsearch.org/guide/en/elasticsearch/guide/current/_installing_elasticsearch.html) Elasticsearch.
2. Install the plugin in WordPress, you can download a [zip via Github](https://github.com/10up/ElasticPress/archive/master.zip) and upload it using the WP plugin uploader.

= Configuration =

First, make sure you have Elasticsearch configured properly.

Before configuring the WordPress plugin, you need to decide how you want to run the plugin. The processes for
configuring single site and multisite cross-site search are slightly different.

= Single Site =
1. Activate the plugin.
2. Define the constant EP_HOST in your wp-config file with the connection (and port) of your Elasticsearch application.
3. Using wp-cli, do an initial sync (with mapping) with your ES server by running: `wp elasticpress index --setup`.

= Multisite Cross-site Search =
1. Network activate the plugin
2. Define the constant EP_HOST in your wp-config file with the connection (and port) of your Elasticsearch application.
3. Using wp-cli, do an initial sync (with mapping) with your ES server by running: `wp elasticpress index --setup --network-wide`.

== Changelog ==

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