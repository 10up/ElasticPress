The following WP-CLI commands are supported by ElasticPress:

* `wp elasticpress index [--setup] [--network-wide] [--per-page] [--nobulk] [--offset] [--indexables] [--show-bulk-errors] [--post-type] [--include]`

    Index all posts in the current blog.

    * `--network-wide` will force indexing on all the blogs in the network. `--network-wide` takes an optional argument to limit the number of blogs to be indexed across where 0 is no limit. For example, `--network-wide=5` would limit indexing to only 5 blogs on the network.
    * `--setup` will clear the index first and re-send the put mapping.
    * `--per-page` let's you determine the amount of posts to be indexed per bulk index (or cycle).
    * `--nobulk` let's you disable bulk indexing.
    * `--offset` let's you skip the first n posts (don't forget to remove the `--setup` flag when resuming or the index will be emptied before starting again).
    * `--indexables` lets you specify the Indexable(s) which will be indexed.
    * `--show-bulk-errors` displays the error message returned from Elasticsearch when a post fails to index (as opposed to just the title and ID of the post).
    * `--post-type` let's you specify which post types will be indexed (by default: all indexable post types are indexed). For example, `--post-type="my_custom_post_type"` would limit indexing to only posts from the post type "my_custom_post_type". Accepts multiple post types separated by comma.
    * `--include` Choose which object IDs to include in the index.
    * `--post-ids` Choose which post_ids to include when indexing the Posts Indexable (deprecated).

* `wp elasticpress delete-index [--network-wide]`

  Deletes the current Indexables indices. `--network-wide` will force every index on the network to be deleted.

* `wp elasticpress put-mapping [--network-wide] [--indexables]`

  Sends plugin put mapping to the current Indexables indices (this will delete the indices). `--network-wide` will force mappings to be sent for every index in the network.

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

* `wp elasticpress get_indexes`

  Get all index names as json.

* `wp elasticpress get_cluster_indexes`

  Return all indexes from the cluster as json.