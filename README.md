ElasticPress
=============

A plugin to integrate WordPress with Elasticsearch

## Purpose

Anyone who has tried to anything fancy with WordPress search such as filtering by multiple taxonomies or post meta keys
knows that it is lacking. Elasticsearch is a powerful open-source search engine powered by Java. Coupling WordPress and
Elasticsearch allows us to do amazing things with search at lightning speed such as filtering by multiple taxonomies,
fuzzy matching, auto complete, etc.


## Installation

1. First, you will need to properly [install and configure](http://www.elasticsearch.org/guide/en/elasticsearch/guide/current/_installing_elasticsearch.html) Elasticsearch.
2. Install the plugin in WordPress, you can download a zip via Github and upload it using the WP plugin uploader.

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
* '' is the MySQL user password
* localhost is the MySQL server host
* latest is the WordPress version; could also be 3.7, 3.6.2 etc.

Run the plugin tests:
```
phpunit
```

#### Issues
If you identify any errors in this plugin, or have an idea for an improvement, please open an issue. We're excited to
see what the community thinks of this project, and we would love your input.