ElasticPress
=============
[![Build Status](https://travis-ci.org/10up/ElasticPress.svg?branch=master)](https://travis-ci.org/10up/ElasticPress)

Integrate [Elasticsearch](http://www.elasticsearch.org/) with [WordPress](http://wordpress.org/).

## Purpose

Let's face it, WordPress search is rudimentary at best. Poor performance, inflexible and rigid matching algorithms (which means no comprehension of 'close' queries), the inability to search metadata and taxonomy information, no way to determine categories of your results and most importantly the overall relevancy of results is poor.

Elasticsearch is a search server based on Lucene. It provides a distributed, multitenant-capable full-text search engine with a RESTful web interface and schema-free JSON documents.

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

## Installation

1. First, you will need to properly [install and configure](http://www.elasticsearch.org/guide/en/elasticsearch/guide/current/_installing_elasticsearch.html) Elasticsearch.
2. Install the plugin in WordPress, you can download a [zip via Github](https://github.com/10up/ElasticPress/archive/master.zip) and upload it using the WP plugin uploader.

## Configuration

First, make sure you have Elasticsearch configured properly.

Before configuring the WordPress plugin, you need to decide how you want to run the plugin. The processes for
configuring single site and multisite cross-site search are slightly different.

#### Single Site
1. Activate the plugin.
2. Within the admin panel, navigate to Settings > ElasticPress
3. Input the Elasticsearch host, Elasticsearch index name, and at least one post type you want to index.

#### Multisite Cross-site Search
1. Network activate the plugin
2. Within your network settings dashboard, go to Settings > ElasticPress
3. Check the box to activate cross-site search
4. Input the Elasticsearch host, Elasticsearch index name, and at least one post type on one site that you want to
index.

## Development

#### Setup
Follow the configuration instructions above to setup the plugin.

#### Testing
Within the terminal change directories to the plugin folder. Initialize your testing environment by running the
following command:
```
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```
where:

* wordpress_test is the name of the test database (all data will be deleted!)
* root is the MySQL user name
* root is the MySQL user password (if you're running VVV). Blank if you're running VIP Quickstart.
* localhost is the MySQL server host
* latest is the WordPress version; could also be 3.7, 3.6.2 etc.

Run the plugin tests:
```
phpunit
```

#### Issues
If you identify any errors or have an idea for improving the plugin, please open an issue. We're excited to
see what the community thinks of this project, and we would love your input.