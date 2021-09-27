The following WP-CLI commands are supported by ElasticPress:

* `wp elasticpress index [--network-wide] [--setup] [--per-page] [--nobulk] [--show-errors] [--show-bulk-errors] [--show-nobulk-errors] [--offset] [--indexables] [--post-type] [--include] [--post-ids] [--upper-limit-object-id] [--lower-limit-object-id] [--ep-host] [--ep-prefix] [--yes]` 

	Index all posts for a site or network wide.

	* `[--network-wide]`: Force indexing on all the blogs in the network. `--network-wide` takes an optional argument to limit the number of blogs to be indexed across where 0 is no limit. For example, `--network-wide=5` would limit indexing to only 5 blogs on the network
	* `[--setup]`: Clear the index first and re-send the put mapping. Use `--yes` to skip the confirmation
	* `[--per-page]`: Determine the amount of posts to be indexed per bulk index (or cycle)
	* `[--nobulk]`: Disable bulk indexing
	* `[--show-errors]`: Show all errors
	* `[--show-bulk-errors]`: Display the error message returned from Elasticsearch when a post fails to index using the /_bulk endpoint
	* `[--show-nobulk-errors]`: Display the error message returned from Elasticsearch when a post fails to index while not using the /_bulk endpoint
	* `[--offset]`: Skip the first n posts (don't forget to remove the `--setup` flag when resuming or the index will be emptied before starting again).
	* `[--indexables]`: Specify the Indexable(s) which will be indexed
	* `[--post-type]`: Specify which post types will be indexed (by default: all indexable post types are indexed). For example, `--post-type="my_custom_post_type"` would limit indexing to only posts from the post type "my_custom_post_type". Accepts multiple post types separated by comma
	* `[--include]`: Choose which object IDs to include in the index
	* `[--post-ids]`: Choose which post_ids to include when indexing the Posts Indexable (deprecated)
	* `[--upper-limit-object-id]`: Upper limit of a range of IDs to be indexed. If indexing IDs from 30 to 45, this should be 45
	* `[--lower-limit-object-id]`: Lower limit of a range of IDs to be indexed. If indexing IDs from 30 to 45, this should be 30
	* `[--ep-host]`: Custom Elasticsearch host
	* `[--ep-prefix]`: Custom ElasticPress prefix
	* `[--yes]`: Skip confirmation needed by `--setup`

* `wp elasticpress activate-feature <feature-slug>` 

	Activate a feature. If a re-indexing is required, you will need to do it manually.

	* `<feature-slug>`: The feature slug

* `wp elasticpress deactivate-feature <feature-slug>` 

	Dectivate a feature.

	* `<feature-slug>`: The feature slug

* `wp elasticpress list-features [--all]` 

	List features (either active or all).

	* `[--all]`: Show all registered features

* `wp elasticpress get-algorithm-version` 

	Get the algorithm version.

	Get the value of the `ep_search_algorithm_version` option, or
`default` if empty.

* `wp elasticpress set-algorithm-version [--version=<version>] [--default]` 

	Set the algorithm version.

	Set the algorithm version through the `ep_search_algorithm_version` option,
that will be used by the filter with same name.
Delete the option if `--default` is passed.

	* `[--version=<version>]`: Version name
	* `[--default]`: Use to set the default version

* `wp elasticpress clear-index` 

	Clear a sync/index process.

	If an index was stopped prematurely and won't start again, this will clear this cached data such that a new index can start.

* `wp elasticpress delete-index [--index-name] [--network-wide] [--yes]` 

	Delete the index for each indexable. !!Warning!! This removes your elasticsearch index(s) for the entire site.

	* `[--index-name]`: The name of the index to be deleted. If not passed, all indexes will be deleted
	* `[--network-wide]`: Force every index on the network to be deleted.
	* `[--yes]`: Skip confirmation

* `wp elasticpress epio-set-autosuggest` 

	A WP-CLI wrapper to run `Autosuggest::epio_send_autosuggest_public_request()`.

* `wp elasticpress get-cluster-indexes` 

	Return all indexes from the cluster as a JSON object.

* `wp elasticpress get-indexes` 

	Return all index names as a JSON object.

* `wp elasticpress get-indexing-status` 

	Returns the status of an ongoing index operation in JSON array.

	Returns the status of an ongoing index operation in JSON array with the following fields:
indexing | boolean | True if index operation is ongoing or false
method | string | 'cli', 'web' or 'none'
items_indexed | integer | Total number of items indexed
total_items | integer | Total number of items indexed or -1 if not yet determined

* `wp elasticpress get-last-cli-index [--clear]` 

	Returns a JSON array with the results of the last CLI index (if present) of an empty array.

	* `[--clear]`: Clear the `ep_last_cli_index` option.

* `wp elasticpress put-mapping [--network-wide] [--indexables] [--ep-host] [--ep-prefix]` 

	Add document mappings for every indexable.

	Sends plugin put mapping to the current Indexables indices (this will delete the indices)

	* `[--network-wide]`: Force mappings to be sent for every index in the network.
	* `[--indexables]`: List of indexables
	* `[--ep-host]`: Custom Elasticsearch host
	* `[--ep-prefix]`: Custom ElasticPress prefix

* `wp elasticpress recreate-network-alias` 

	Recreates the alias index which points to every index in the network.

	Map network alias to every index in the network for every non-global indexable

* `wp elasticpress stats` 

	Get stats on the current index.

* `wp elasticpress status` 

	Ping the Elasticsearch server and retrieve a status.

* `wp elasticpress stop-indexing` 

	Stop the indexing operation started from the dashboard.

