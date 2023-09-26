/**
 * Window dependencies.
 */
const {
	auto_start_index: autoIndex,
	api_url: apiUrl,
	index_meta: indexMeta = null,
	indexables,
	is_epio: isEpio,
	ep_last_sync_date: lastSyncDateTime = null,
	ep_last_sync_failed: lastSyncFailed = false,
	nonce,
	post_types: postTypes,
} = window.epDash;

export {
	autoIndex,
	apiUrl,
	indexables,
	indexMeta,
	isEpio,
	lastSyncDateTime,
	lastSyncFailed,
	nonce,
	postTypes,
};
