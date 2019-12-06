Much of ElasticPress's functionality is bundled into features. Here are the features:

## Search

Instantly find the content you’re looking for. The first time.

## WooCommerce

“I want a cotton, woman’s t-shirt, for under $15 that’s in stock.” Faceted product browsing strains servers and increases load times. Your buyers can find the perfect product quickly, and buy it quickly.

## Related Posts

ElasticPress understands data in real time, so it can instantly deliver engaging and precise related content with no impact on site performance.

Available API functions:

* `ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id, $return = 5 )`

  Get related posts for a given `$post_id`. Use this in a theme or plugin to get related content.

## Protected Content

Optionally index all of your content, including private and unpublished content, to speed up searches and queries in places like the administrative dashboard.

## Documents (requires [Ingest Attachment plugin](https://www.elastic.co/guide/en/elasticsearch/plugins/master/ingest-attachment.html))

Indexes text inside of popular file types, and adds those files types to search results.

## Autosuggest

Suggest relevant content as text is entered into the search field.

## Facets

Add controls to your website to filter content by one or more taxonomies.

## Users (requires WordPress 5.1+)

Improve user search relevancy and query performance.