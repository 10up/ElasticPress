## Requirements

* [Elasticsearch](https://www.elastic.co) 5.2+ **ElasticSearch max version supported: 7.10**
* [WordPress](http://wordpress.org) 5.6+
* [PHP](https://php.net/) 7.0+
* A properly configured web server with object caching is highly recommended.

## Install Steps

1. First, you will need to sign up for an [ElasticPress.io account](https://elasticpress.io), or if you prefer, you can [install and configure](https://www.elastic.co/guide/en/elasticsearch/reference/current/setup.html) Elasticsearch.
2. Install the plugin in WordPress. You can install in the Dashboard, via WP-CLI, download a [zip of the latest release in Github](https://github.com/10up/ElasticPress/releases) and upload it using the WordPress plugin uploader, or [build it from the a branch](https://github.com/10up/ElasticPress#building-assets).
3. Follow the prompts to add your ElasticPress.io or Elasticsearch server. <img src="https://github.com/10up/ElasticPress/raw/develop/images/setup-screenshot.png" width="850">
4. Sync your content by clicking the sync icon.

Once syncing finishes, your site is officially supercharged. You also have access to ElasticPress's powerful Indexables class, which enables you to index and query custom content objects, as well as built-in Indexables for Posts and Users.
