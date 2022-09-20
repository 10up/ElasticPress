/**
 * Window dependencies.
 */
const {
	auto_start_index: autoIndex,
	ajax_url: ajaxUrl,
	index_meta: indexMeta = null,
	is_epio: isEpio,
	ep_last_sync_date: lastSyncDateTime = null,
	ep_last_sync_failed: lastSyncFailed = false,
	indices_data: indices = 0,
	index_stats: indexStats = null,
	nonce,
} = window.epDash;

export {
	autoIndex,
	ajaxUrl,
	indexMeta,
	isEpio,
	lastSyncDateTime,
	lastSyncFailed,
	indices,
	indexStats,
	nonce,
};
