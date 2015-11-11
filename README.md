ElasticPress [![Build Status](https://travis-ci.org/10up/ElasticPress.svg?branch=master)](https://travis-ci.org/10up/ElasticPress) [![Code Climate](https://img.shields.io/codeclimate/coverage/github/10up/ElasticPress.svg?style=flat)](https://codeclimate.com/github/10up/ElasticPress) [![Dockunit Status](http://dockunit.io/svg/10up/ElasticPress?master)](http://dockunit.io/projects/10up/ElasticPress#master)
=============

Integrate [Elasticsearch](http://www.elasticsearch.org/) with [WordPress](http://wordpress.org/).

**Please note:** the master branch is the stable branch

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

There are other Elasticsearch integration plugins available for WordPress. ElasticPress, unlike others, offers multi-site search. Elasticsearch is a complex topic and integration results in complex problems. Rather than providing a limited, clunky UI, we elected to instead provide full control via [WP-CLI](http://wp-cli.org/).

## Requirements

* [Elasticsearch](https://www.elastic.co) 1.3+
* [WordPress](http://wordpress.org) 3.7.1+
* [WP-CLI](http://wp-cli.org/) 0.13+

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

**Note:** The URL for `EP_HOST` *must* begin with a protocol specifier (`http` or `https`). URLs without a protocol prefix will not be parsed correctly and will cause ElasticPress to error out.

The proceeding sets depend on whether you are configuring for single site or multi-site with cross-site search capabilities.

### Single Site

2. Activate the plugin.
3. Using WP-CLI, do an initial sync (with mapping) with your ES server by running the following commands:

```bash
wp elasticpress index --setup
```

### Multisite Cross-site Search

2. Network activate the plugin
3. Using WP-CLI, do an initial sync (with mapping) with your ES server by running the following commands:

```bash
wp elasticpress index --setup --network-wide
```

After your index finishes, ```WP_Query``` will be integrated with Elasticsearch and support a few special parameters.

### Creating Elasticsearch Indices

Creating indices is handled automatically by ElasticPress. Index names are automatically generated based on site URL.

## Usage

After running an index, ElasticPress integrates with `WP_Query` if and only if the query is a search or the `ep_integrate` parameter is passed (see below). The end goal is to support all the parameters available to WP_Query so the transition is completely transparent. Right now, our WP_Query integration supports *many* of the relevant WP_Query parameters and adds a couple special ones.

### Supported WP_Query Parameters

* ```s``` (*string*)

    Search keyword. By default used to search against ```post_title```, ```post_content```, and ```post_excerpt```.

* ```posts_per_page``` (*int*)

    Number of posts to show per page. Use -1 to show all posts (the ```offset``` parameter is ignored with a -1 value). Set the ```paged``` parameter to paginate based on ```posts_per_page```.

* ```tax_query``` (*array*)

    Filter posts by terms in taxonomies. Takes an array of form:

    ```php
    new WP_Query( array(
        's'         => 'search phrase',
        'tax_query' => array(
            array(
                'taxonomy' => 'taxonomy-name',
                'field'    => 'slug',
                'terms'    => array( 'term-slug-1', 'term-slug-2', ... ),
            ),
        ),
    ) );
    ```

    ```tax_query``` accepts an array of arrays where each inner array *only* supports ```taxonomy``` (string), ```field``` (string), and
    ```terms``` (string|array) parameters. ```field``` must be set to ```slug``` and ```terms``` must be a string or array of term slug(s).

* The following shorthand parameters can be used for querying posts by specific dates:

    * ```year``` (int) - 4 digit year (e.g. 2011).
    * ```month``` or ```monthnum``` (int) - Month number (from 1 to 12).
    * ```week``` (int) - Week of the year (from 0 to 53).
    * ```day``` (int) - Day of the month (from 1 to 31).
    * ```dayofyear``` (int) - Day of the month (from 1 to 365 or 366 for leap year).
    * ```hour``` (int) - Hour (from 0 to 23).
    * ```minute``` (int) - Minute (from 0 to 59).
    * ```second``` (int) - Second (0 to 59).
    * ```dayofweek``` (int|array) - Weekday number, when week starts at Sunday (1 to 7).
    * ```dayofweek_iso```  (int|array) - Weekday number, when week starts at Monday (1 to 7).

    This is a simple example which will return posts which are created on January 1st of 2012 from all sites:

    ```php
    new WP_Query( array(
        's' => 'search phrase',
        'sites' => 'all',
        'year'  => 2012,
        'monthnum' => 1,
        'day'   => 1,
    ) );
    ```

* ```date_query``` (*array*)

    ```date_query``` accepts an array of keys and values (array|string|int) to find posts created on
    specific dates/times as well as an array of arrays with keys and values (array|string|int|boolean)
    containing the following parameters ```after```, ```before```, ```inclusive```, ```compare```, ```column```, and
    ```relation```. ```column``` is used to query specific columns from the ```wp_posts``` table. This will return posts
    which are created after January 1st 2012 and January 3rd 2012 8AM GMT:
    
    ```php
    new WP_Query( array(
        's' => 'search phrase',
        'date_query' => array(
            array(
                'column' => 'post_date',
                'after' => 'January 1st 2012',
            ),
            array(
                'column' => 'post_date_gmt',
                'after'  => 'January 3rd 2012 8AM',
            ),
        ),
    ) );
    ```
    
    Currently only the ```AND``` value is supported for the ```relation``` parameter.

    ```inclusive``` is used on after/before options to determine whether exact value should be matched or not. If inclusive is used
    and you pass in sting without specific time, it will be converted to 00:00:00 on that date. In this case, even if
    inclusive was set to true, the date would not be included in the query. If you want to include that specific date,
    you need to pass the time as well. (e.g. 'before' => '2012-01-03 23:59:59')

    The example will return all posts which are created on January 5th 2012 after 10:00PM and 11:00PM inclusively,
    because the time is specified:

    ```php
    new WP_Query( array(
        's' => 'search phrase',
        'date_query' => array(
            array(
                'column' => 'post_date',
                'before' => 'January 5th 2012 11:00PM',
            ),
            array(
                'column' => 'post_date',
                'after'  => 'January 5th 2012 10:00PM',
            ),
            'inclusive' => true,
        ),
    ) );
    ```

    ```compare``` supports the following options:

    * ```=``` - Posts will be returned that are created on a specified date.
    * ```!=``` - Posts will be returned that are not created on a specified date.
    * ```>``` - Posts will be returned that are created after a specified date.
    * ```>=``` - Posts will be returned that are created on a specified date or after.
    * ```<``` - Posts will be returned that are created before a specified date.
    * ```<=``` - Posts will be returned that are created on a specified date or before that.
    * ```BETWEEN``` - Posts will be returned that are created between a specified range.
    * ```NOT BETWEEN``` - Posts will be returned that are created not in a specified range.
    * ```IN``` - Posts will be returned that are created on any of the specified dates.
    * ```NOT IN``` - Posts will be returned that are not created on any of the specified dates.

    ```compare``` can be combined with shorthand parameters as well as with ```after``` and ```before```. This example
    will return all posts which are created during Monday to Friday, between 9AM to 5PM:

    ```php
    new WP_Query( array(
        's' => 'search phrase',
        'date_query' => array(
            array(
                'hour'      => 9,
                'compare'   => '>=',
            ),
            array(
                'hour'      => 17,
                'compare'   => '<=',
            ),
            array(
                'dayofweek' => array( 2, 6 ),
                'compare'   => 'BETWEEN',
            ),
        ),
    ) );
    ```

* ```meta_query``` (*array*)

    Filter posts by post meta conditions. Takes an array of form:

    ```php
    new WP_Query( array(
        's'          => 'search phrase',
        'meta_query' => array(
            array(
                'key'   => 'key_name',
                'value' => 'meta value',
                'compare' => '=',
            ),
        ),
    ) );
    ```

    ```meta_query``` accepts an array of arrays where each inner array *only* supports ```key``` (string), ```value``` (string|array|int), and ```compare``` (string) parameters. ```compare``` supports the following:

      * ```=``` - Posts will be returned that have a post meta key corresponding to ```key``` and a value that equals the value passed to ```value```.
      * ```!=``` - Posts will be returned that have a post meta key corresponding to ```key``` and a value that does NOT equal the value passed to ```value```.
      * ```>``` - Posts will be returned that have a post meta key corresponding to ```key``` and a value that is greater than the value passed to ```value```.
      * ```>=``` - Posts will be returned that have a post meta key corresponding to ```key``` and a value that is greater than or equal to the value passed to ```value```.
      * ```<``` - Posts will be returned that have a post meta key corresponding to ```key``` and a value that is less than the value passed to ```value```.
      * ```<=``` - Posts will be returned that have a post meta key corresponding to ```key``` and a value that is less than or equal to the value passed to ```value```.
      * ```EXISTS``` - Posts will be returned that have a post meta key corresponding to ```key```.
      * ```NOT EXISTS``` - Posts will be returned that do not have a post meta key corresponding to ```key```.

    The outer array also supports a ```relation``` (string) parameter. By default ```relation``` is set to ```AND```:
    ```php
    new WP_Query( array(
        's'          => 'search phrase',
        'meta_query' => array(
            array(
                'key'   => 'key_name',
                'value' => 'meta value',
                'compare' => '=',
            ),
            array(
                'key'   => 'key_name2',
                'value' => 'meta value',
                'compare' => '!=',
            ),
            'relation' => 'AND',
        ),
    ) );
    ```

    Possible values for ```relation``` are ```OR``` and ```AND```. If ```relation``` is set to ```AND```, all inner queries must be true for a post to be returned. If ```relation``` is set to ```OR```, only one of the inner meta queries must be true for the post to be returned.

* ```post_type``` (*string*/*array*)

    Filter posts by post type. ```any``` wil search all public post types. `WP_Query` defaults to either `post` or `any` if no `post_type` is provided depending on the context of the query. This is confusing. ElasticPress will ALWAYS default to `any` if no `post_type` is provided. If you want to search for `post` posts, you MUST specify `post` as the `post_type`.

* ```offset``` (*int*)

    Number of posts to skip in ascending order.

* ```paged``` (*int*)

    Page number of posts to be used with ```posts_per_page```.

* ```author``` (*int*)

    Show posts associated with certain author ID.
    
* ```author_name``` (*string*)

    Show posts associated with certain author. Use ```user_nicename``` (NOT name).
    
* ```orderby``` (*string*)

    Order results by field name instead of relevance. Currently only supports: ```title```, ```name```, ```date```, and ```relevance``` (default).

* ```order``` (*string*)

    Which direction to order results in. Accepts ```ASC``` and ```DESC```. Default is ```DESC```.

The following are special parameters that are only supported by ElasticPress.

* ```search_fields``` (*array*)

    If not specified, defaults to ```array( 'post_title', 'post_excerpt', 'post_content' )```.

    * ```post_title``` (*string*)

        Applies current search to post titles.

    * ```post_content``` (*string*)

        Applies current search to post content.

    * ```post_excerpt``` (*string*)

        Applies current search to post excerpts.

    * ```taxonomies``` (*string* => *array*/*string*)

        Applies the current search to terms within a taxonomy or taxonomies. The following will fuzzy search across ```post_title```, ```post_excerpt```, ```post_content```, and terms within taxonomies ```category``` and ```post_tag```:

        ```php
        new WP_Query( array(
            's'             => 'term search phrase',
            'search_fields' => array(
                'post_title',
                'post_content',
                'post_excerpt',
                'taxonomies' => array( 'category', 'post_tag' ),
            ),
        ) );
        ```

    * ```meta``` (*string* => *array*/*string*)

        Applies the current search to post meta. The following will fuzzy search across ```post_title```, ```post_excerpt```, ```post_content```, and post meta keys ```meta_key_1``` and ```meta_key_2```:

        ```php
        new WP_Query( array(
            's'             => 'meta search phrase',
            'search_fields' => array(
                'post_title',
                'post_content',
                'post_excerpt',
                'meta' => array( 'meta_key_1', 'meta_key_2' ),
            ),
        ) );
        ```

    * ```author_name``` (*string*)

        Applies the current search to author login names. The following will fuzzy search across ```post_title```, ```post_excerpt```, ```post_content``` and author ```user_login```:

        ```php
        new WP_Query( array(
            's'             => 'username',
            'search_fields' => array(
                'post_title',
                'post_content',
                'post_excerpt',
                'author_name',
            ),
        ) );
        ```

* ```aggs``` (*array*)

    Add aggregation results to your search result. For example:
    
    ```php
    new WP_Query( array(
        's'    => 'search phrase',
        'aggs' => array(
            'name'       => 'name-of-aggregation', // (can be whatever you'd like)
            'use-filter' => true // (*bool*) used if you'd like to apply the other filters (i.e. post type, tax_query, author), to the aggregation
            'aggs'       => array(
                'name'  => 'name-of-sub-aggregation',
                'terms' => array(
                    'field' => 'terms.name-of-taxonomy.name-of-term',
                ),
            ),
        ),
    ) );
    ```

* ```cache_results``` (*boolean*)

    This is a built-in WordPress parameter that caches retrieved posts for later use. It also forces meta and terms to be pulled and cached for each cached post. It is extremely important to understand when you use this parameter with ElasticPress that terms and meta will be pulled from MySQL not Elasticsearch during caching. For this reason, ```cache_results``` defaults to false.

* ```sites``` (*int*/*string*/*array*)

    This parameter only applies in a multi-site environment. It lets you search for posts on specific sites or across the network.

    By default, ```sites``` defaults to ```current``` which searches the current site on the network:

    ```php
    new WP_Query( array(
        's'     => 'search phrase',
        'sites' => 'current',
    ) );
    ```

    You can search on all sites across the network:

    ```php
    new WP_Query( array(
        's'     => 'search phrase',
        'sites' => 'all',
    ) );
    ```

    You can also specify specific sites by id on the network:

    ```php
    new WP_Query( array(
        's'     => 'search phrase',
        'sites' => 3,
    ) );
    ```

    You can even specify a group of specific sites on the network:
    ```php
    new WP_Query( array(
        's'     => 'search phrase',
        'sites' => array( 2, 3 ),
    ) );
    ```

    _Note:_ Nesting cross-site `WP_Query` loops can result in unexpected behavior.

* ```ep_integrate``` (*bool*)

    Allows you to perform queries without passing a search parameter. This is pretty powerful as you can leverage Elasticsearch to retrieve queries that are too complex for MySQL (such as a 5-dimensional taxonomy query). For example:
    
    Get 20 of the lastest posts
    ```php
    new WP_Query( array(
        'ep_integrate'   => true,
        'post_type'      => 'post',
        'posts_per_page' => 20,
    ) );
    ```
    
    Get all posts with a specific category slug
    ```php
    new WP_Query( array(
        'ep_integrate'   => true,
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'category',
                'terms'    => array( 'term-slug' ),
                'field'    => 'slug',
            ),
        ),
    ) );
    ```

### Supported WP-CLI Commands

The following commands are supported by ElasticPress:

* `wp elasticpress index [--setup] [--network-wide] [--posts-per-page] [--no-bulk] [--offset]`

  Index all posts in the current blog. `--network-wide` will force indexing on all the blogs in the network. `--network-wide` takes an optional argument to limit the number of blogs to be indexed across where 0 is no limit. For example, `--network-wide=5` would limit indexing to only 5 blogs on the network. `--setup` will clear the index first and re-send the put mapping. `--posts-per-page` let's you determine the amount of posts to be indexed per bulk index (or cycle). `--no-bulk` let's you disable bulk indexing. `--offset` let's you skip the first n posts (don't forget to remove the `--setup` flag when resuming or the index will be emptied before starting again).

* `wp elasticpress activate`

  Turns on ElasticPress integration. Integration is automatically deactivated during indexing.

* `wp elasticpress deactivate`

  Turns off ElasticPress integration. Integration is automatically deactivated during indexing.

* `wp elasticpress delete-index [--network-wide]`

  Deletes the current blog index. `--network-wide` will force every index on the network to be deleted.

* `wp elasticpress is-active`

  Checks whether ElasticPress is currently integration active. This is different than whether the plugin is WordPress active or not. During indexing, integration will be deactivated automatically.

* `wp elasticpress put-mapping [--network-wide]`

  Sends plugin put mapping to the current blog index. `--network-wide` will force mappings to be sent for every index in the network.

* `wp elasticpress recreate-network-alias`

  Recreates the alias index which points to every index in the network.

* `wp elasticpress stats`

  Returns basic stats on Elasticsearch instance i.e. number of documents in current index as well as disk space used.

* `wp elasticpress status`


## Development

### Setup

Follow the configuration instructions above to setup the plugin.

### Testing

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

#### Dockunit

ElasticPress contains a valid [Dockunit](https://www.npmjs.com/package/dockunit) file for running unit tests across a variety of environments locally (PHP 5.2 and 5.6). You can use Dockunit by running:

```bash
dockunit
```

### Issues

If you identify any errors or have an idea for improving the plugin, please [open an issue](https://github.com/10up/ElasticPress/issues?state=open). We're excited to see what the community thinks of this project, and we would love your input!


## License

ElasticPress is free software; you can redistribute it and/or modify it under the terms of the [GNU General Public License](http://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
