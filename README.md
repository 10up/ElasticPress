ElasticPress [![Build Status](https://travis-ci.org/10up/ElasticPress.svg?branch=master)](https://travis-ci.org/10up/ElasticPress)
=============

Integrate [Elasticsearch](http://www.elasticsearch.org/) with [WordPress](http://wordpress.org/).

* **Version**: 0.9.2
* **Latest Stable**: [v0.9.2](https://github.com/10up/ElasticPress/releases/tag/0.9.2)
* **Contributors**: [@aaronholbrook](https://github.com/AaronHolbrook), [@tlovett1](https://github.com/tlovett1), [@mattonomics](https://github.com/mattonomics), [@ivanlopez](https://github.com/ivanlopez)

## Background

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

## Purpose

The goal of ElasticPress is to integrate WordPress with Elasticsearch. This plugin integrates with the [WP_Query](http://codex.wordpress.org/Class_Reference/WP_Query) object returning results from Elasticsearch instead of MySQL.

There are other Elasticsearch integration plugins available for WordPress. ElasticPress, unlike others, offers multi-site search. Elasticsearch is a complex topic and integrating with it results in complex problems. Rather than providing a limited, clunky UI, we elected to instead provide full control via [WP-CLI](http://wp-cli.org/).

## Installation

1. First, you will need to properly [install and configure](http://www.elasticsearch.org/guide/en/elasticsearch/guide/current/_installing_elasticsearch.html) Elasticsearch.
2. ElasticPress requires WP-CLI. Install it by following [these instructions](http://wp-cli.org).
3. Install the plugin in WordPress. You can download a [zip via Github](https://github.com/10up/ElasticPress/archive/master.zip) and upload it using the WP plugin uploader.

## Configuration

First, make sure you have Elasticsearch and WP-CLI configured properly.

1. Define the constant ```EP_HOST``` in your ```wp-config.php``` file with the connection (and port) of your Elasticsearch application. For example:

```php
define( 'EP_HOST', 'http://192.168.50.4:9200' );
```

The proceeding sets depend on whether you are configuring for single site or multi-site with cross-site search capabilities.

#### Single Site

2. Activate the plugin.
3. Using wp-cli, do an initial sync (with mapping) with your ES server by running the following commands:

```bash
wp elasticpress put-mapping
```

```bash
wp elasticpress index
```

#### Multisite Cross-site Search

2. Network activate the plugin
3. Using wp-cli, do an initial sync (with mapping) with your ES server by running the following commands:

```bash
wp elasticpress put-mapping --network-wide
```

```bash
wp elasticpress index --network-wide
```

After your index finishes, ```WP_Query``` will be integrated with Elasticsearch and support a few special parameters.

## Development

#### Setup

Follow the configuration instructions above to setup the plugin.

#### Testing

Within the terminal change directories to the plugin folder. Initialize your testing environment by running the
following command:

For VVV users:
```
bash bin/install-wp-tests.sh wordpress_test root root localhost latest
```

For VIP Quickstart users:
```
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

where:

* ```wordpress_test``` is the name of the test database (all data will be deleted!)
* ```root``` is the MySQL user name
* ```root``` is the MySQL user password (if you're running VVV). Blank if you're running VIP Quickstart.
* ```localhost``` is the MySQL server host
* ```latest``` is the WordPress version; could also be 3.7, 3.6.2 etc.


Our test suite depends on a running Elasticsearch server. You can supply a host to PHPUnit as an environmental variable like so:

```bash
EP_HOST="http://192.168.50.4:9200" phpunit
```

#### Issues

If you identify any errors or have an idea for improving the plugin, please [open an issue](https://github.com/10up/ElasticPress/issues?state=open). We're excited to see what the community thinks of this project, and we would love your input!
