ElasticPress [![Build Status](https://travis-ci.org/10up/ElasticPress.svg?branch=develop)](https://travis-ci.org/10up/ElasticPress)
=============

A fast and flexible search and query engine for WordPress.

**Please note:** master is the stable branch

**Upgrade Notice:** Versions 1.6.1, 1.6.2, 1.7, 1.8, 2.1, 2.1.2, 2.2 require re-syncing.

ElasticPress, a fast and flexible search and query engine for WordPress, enables WordPress to find or “query” relevant content extremely fast through a variety of highly customizable features. WordPress out-of-the-box struggles to analyze content relevancy and can be very slow. ElasticPress supercharges your WordPress website making for happier users and administrators. The plugin even contains features for popular plugins.

<p align="center">
<a href="http://10up.com/contact/"><img src="https://10updotcom-wpengine.s3.amazonaws.com/uploads/2016/10/10up-Github-Banner.png" width="850"></a>
</p>

## How Does it Work

ElasticPress integrates with the [WP_Query](http://codex.wordpress.org/Class_Reference/WP_Query) object returning results from Elasticsearch instead of MySQL.

## Requirements

* [Elasticsearch](https://www.elastic.co) 1.7 - 5.2 (2.0+ highly recommended)
* [WordPress](http://wordpress.org) 3.7.1+

## Installation

1. First, you will need to properly [install and configure](https://www.elastic.co/guide/en/elasticsearch/reference/current/setup.html) Elasticsearch.
2. Install the plugin in WordPress. You can download a [zip via Github](https://github.com/10up/ElasticPress/archive/master.zip) and upload it using the WordPress plugin uploader.
3. Activate the plugin (network activate for multisite). Navigate to the settings page. You should see an ElasticPress icon in your admin menu.
4. Input your Elasticsearch host. Your host must begin with a protocol specifier (`http` or `https`). URLs without a protocol prefix will not be parsed correctly and will cause ElasticPress to error out.
5. Sync your content by clicking the sync icon.

Once syncing finishes, your site is officially supercharged. You also have access to ElasticPress's powerful WP_Query integration API.

## Features

### Search

Instantly find the content you’re looking for. The first time.

### WooCommerce

“I want a cotton, woman’s t-shirt, for under $15 that’s in stock.” Faceted product browsing strains servers and increases load times. Your buyers can find the perfect product quickly, and buy it quickly.

### Related Posts

ElasticPress understands data in real time, so it can instantly deliver engaging and precise related content with no impact on site performance.

Available API functions:

* `ep_find_related( $post_id, $return = 5 )`

  Get related posts for a given `$post_id`. Use this in a theme or plugin to get related content.

### Protected Content

Optionally index all of your content, including private and unpublished content, to speed up searches and queries in places like the administrative dashboard.

### Documents (requires [Ingest Attachment plugin](https://www.elastic.co/guide/en/elasticsearch/plugins/master/ingest-attachment.html))

Indexes text inside of popular file types, and adds those files types to search results.

## `WP_Query` and the ElasticPress Query Integration

ElasticPress integrates with `WP_Query` if the `ep_integrate` parameter is passed (see below) to the query object. If the search feature is activated (which it is by default), all queries with the `s` parameter will be integrated with as well. ElasticPress converts `WP_Query` arguments to Elasticsearch readable queries. Supported `WP_Query` parameters are listed and explained below. ElasticPress also adds some extra `WP_query` arguments for extra functionality.

### Supported WP_Query Parameters

* ```ep_integrate``` (*bool*)

    Allows you to run queries through Elasticsearch instead of MySQL. This parameter is the meat of the plugin. For example:

    Get 20 of the latest posts
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

    Setting `ep_integrate` to `false` will override the `s` parameter if provided.

* ```s``` (*string*)

    Search keyword. By default used to search against ```post_title```, ```post_content```, and ```post_excerpt```. (Requires search feature)

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
    ```terms``` (string|array) parameters. ```field``` must be set to `slug`, `name`, or `term_id`. The default value for `field` is `term_id`. ```terms``` must be a string or an array of term slug(s), name(s), or id(s).

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

    Filter posts by post meta conditions. Meta arrays and objects are serialized due to limitations of Elasticsearch. Takes an array of form:

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

    ```meta_query``` accepts an array of arrays where each inner array *only* supports ```key``` (string), 
    ```type``` (string), ```value``` (string|array|int), and ```compare``` (string) parameters. ```compare``` supports the following:
    
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

    ```type``` supports the following values:  'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 
    'DECIMAL', 'SIGNED', 'TIME', and 'UNSIGNED'. By default WordPress casts meta values to these types 
    in MySQL so some of these don't make sense in the context of Elasticsearch. ElasticPress does no "runtime" 
    casting but instead compares the value to a different type compiled during indexing

    * `NUMERIC` - Compares query `value` to integer version of stored meta value.
    * `SIGNED` - Compares query `value` to integer version of stored meta value.
    * `UNSIGNED` - Compares query `value` to integer version of stored meta value.
    * `BINARY` - Compares query `value` to raw, unanalyzed version of stored meta value. For actual attachment searches, check out [this](https://github.com/elastic/elasticsearch-mapper-attachments).
    * `CHAR` - Compares query `value` to raw, unanalyzed version of stored meta value.
    * `DECIMAL` - Compares query `value` to float version of stored meta value.
    * `DATE` - Compares query `value` to date version of stored meta value. Query `value` must be formatted like `2015-11-14`
    * `DATETIME` - Compares query `value` to date/time version of stored meta value. Query `value` must be formatted like `2012-01-02 05:00:00` or `yyyy:mm:dd hh:mm:ss`.
    * `TIME` - Compares query `value` to time version of stored meta value. Query `value` must be formatted like `17:00:00` or `hh:mm:ss`.

    If no type is specified, ElasticPress will just deduce the type from the comparator used. ```type``` 
    is very rarely needed to be used.

* ```meta_key``` (*string*)

    Allows you to query meta with the defined key. Requires `meta_value` or `meta_value_num` be used as well.

* ```meta_value``` (*string*)

    This value will be queried against the key defined in `meta_key`.

* ```meta_value_num``` (*string*)

    This value will be queried against the key defined in `meta_key`.

* ```post_type``` (*string*/*array*)

    Filter posts by post type. ```any``` will search all public post types. `WP_Query` defaults to either `post` or `any` if no `post_type` is provided depending on the context of the query. This is confusing. ElasticPress will ALWAYS default to `any` if no `post_type` is provided. If you want to search for `post` posts, you MUST specify `post` as the `post_type`.

* ```post__in``` (*array*)

    Specify post IDs to retrieve.

* ```post__not_in``` (*array*)

    Specify post IDs to exclude.

* ```offset``` (*int*)

    Number of posts to skip in ascending order.

* ```paged``` (*int*)

    Page number of posts to be used with ```posts_per_page```.

* ```author``` (*int*)

    Show posts associated with certain author ID.
    
* ```author_name``` (*string*)

    Show posts associated with certain author. Use ```user_nicename``` (NOT name).
    
* ```orderby``` (*string*)

    Order results by field name instead of relevance. Supports: ```title```, ```modified```, `meta_value`, `meta_value_num`, ```type```, ```name```, ```date```, and ```relevance```; anything else will be interpretted as a document path i.e. `meta.my_key.long` or `meta.my_key.raw`. You can sort by multiple fields as well i.e. `title meta.my_key.raw`

* ```order``` (*string*)

    Which direction to order results in. Accepts ```ASC``` and ```DESC```. Default is ```DESC```.
  
* ```post_parent``` (*int*)

    Show posts that have the specified post parent.
  

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

## WP-CLI Commands

The following commands are supported by ElasticPress:

* `wp elasticpress index [--setup] [--network-wide] [--posts-per-page] [--nobulk] [--offset] [--show-bulk-errors] [--post-type]`

    Index all posts in the current blog.

    * `--network-wide` will force indexing on all the blogs in the network. `--network-wide` takes an optional argument to limit the number of blogs to be indexed across where 0 is no limit. For example, `--network-wide=5` would limit indexing to only 5 blogs on the network.
    * `--setup` will clear the index first and re-send the put mapping.
    * `--posts-per-page` let's you determine the amount of posts to be indexed per bulk index (or cycle).
    * `--nobulk` let's you disable bulk indexing.
    * `--offset` let's you skip the first n posts (don't forget to remove the `--setup` flag when resuming or the index will be emptied before starting again).
    * `--show-bulk-errors` displays the error message returned from Elasticsearch when a post fails to index (as opposed to just the title and ID of the post).
    * `--post-type` let's you specify which post types will be indexed (by default: all indexable post types are indexed). For example, `--post-type="my_custom_post_type"` would limit indexing to only posts from the post type "my_custom_post_type". Accepts multiple post types separated by comma.

* `wp elasticpress delete-index [--network-wide]`

  Deletes the current blog index. `--network-wide` will force every index on the network to be deleted.

* `wp elasticpress put-mapping [--network-wide]`

  Sends plugin put mapping to the current blog index. `--network-wide` will force mappings to be sent for every index in the network.

* `wp elasticpress recreate-network-alias`

  Recreates the alias index which points to every index in the network.

* `wp elasticpress activate-feature <feature-slug> [--network-wide]`

  Activate a feature. If a re-indexing is required, you will need to do it manually. `--network-wide` will affect network activated ElasticPress.

* `wp elasticpress deactivate-feature <feature-slug> [--network-wide]`

  Deactivate a feature. `--network-wide` will affect network activated ElasticPress.

* `wp elasticpress list-features [--all] [--network-wide]`

  Lists active features. `--all` will show all registered features. `--network-wide` will force checking network options as opposed to a single sites options.

* `wp elasticpress stats`

  Returns basic stats on Elasticsearch instance i.e. number of documents in current index as well as disk space used.

* `wp elasticpress status`

## Security

* ElasticPress can be used with the [Elasticsearch Shield](https://www.elastic.co/products/shield) plugin

    * Define the constant ```ES_SHIELD``` in your ```wp-config.php``` file with the username and password of your Elasticsearch Shield user. For example:

```php
define( 'ES_SHIELD', 'username:password' );
```

## Disable Dashboard Sync

Dashboard sync can be disabled by defining the constant `EP_DASHBOARD_SYNC` as `false` in your wp-config.php file.

```php
define( 'EP_DASHBOARD_SYNC', false );
```

This can be helpful for managed sites where users initiating a sync from the dashboard could potentially cause issues such as deleting the index and limiting this control to WP-CLI is preferred. When disabled, features that would require reindexing are also prevented from being enabled/disabled from the dashboard.

## Custom Features

ElasticPress has a robust API for registering your own features. Refer to the code within each feature for detailed examples. To register a feature, you will need to call the `ep_register_feature()` function like so:

```php
add_action( 'plugins_loaded', function() {
    ep_register_feature( 'slug', array(
        'title' => 'Pretty Title',
        'setup_cb' => 'setup_callback_function',
        'feature_box_summary_cb' => 'summary_callback_function',
        'feature_box_long_cb' => 'long_summary_callback_function',
        'requires_install_reindex' => true,
        'requirements_status_cb' => 'requirements_status_callback_function',
        'post_activation_cb' => 'post_activation_callback_function',
    ) );
} );
```

The only arguments that are really required are the `slug` and `title` of the associative arguments array. Here are descriptions of each of the associative arguments:

* `title` (string) - Pretty title for feature
* `requires_install_reindex` (boolean) - Setting to true will force a reindex after the feature is activated.
* `setup_cb` (callback) - Callback to a function to be called on each page load when the feature is activated.
* `post_activation_cb` (callback) - Callback to a function to be called after a feature is first activated.
* `feature_box_summary_cb` (callback) - Callback to a function that outputs HTML feature box summary (short description of feature).
* `feature_box_long_cb` (callback) - Callback to a function that outputs HTML feature box full description.
* `requirements_status_cb` (callback) - Callback to a function that determines if the features requirements are met. This function needs to return a `EP_Feature_Requirements_Status` object. `EP_Feature_Requirements_Status` is a simple class with a `code` and a `message` property. Code 0 means there are no requirement issues; code 1 means there are issues with warnings; code 2 means the feature does not have it's requirements met and cannot be used. The message property of the object can be used to store warnings.

If you build an open source custom feature, let us know! We'd be happy to list the feature within ElasticPress documentation.

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

### Debugging

We have a [Debug Bar Plugin](https://github.com/10up/debug-bar-elasticpress) available for ElasticPress. This tool allows you to examine all the ElasticPress queries on each page load.

### Issues

If you identify any errors or have an idea for improving the plugin, please [open an issue](https://github.com/10up/ElasticPress/issues?state=open). We're excited to see what the community thinks of this project, and we would love your input!


## License

ElasticPress is free software; you can redistribute it and/or modify it under the terms of the [GNU General Public License](http://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
