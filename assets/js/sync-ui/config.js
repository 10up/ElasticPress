/**
 * Window dependencies.
 */
const {
	auto_start_index: autoIndex,
	api_url: apiUrl,
	index_meta: indexMeta = null,
	is_epio: isEpio,
	ep_last_sync_date: lastSyncDateTime = null,
	ep_last_sync_failed: lastSyncFailed = false,
	nonce,
} = window.epDash;

export { autoIndex, apiUrl, indexMeta, isEpio, lastSyncDateTime, lastSyncFailed, nonce };
