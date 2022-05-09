/**
 * Clear sync parameter from the URL.
 *
 * @returns {void}
 */
export const clearSyncParam = () => {
	window.history.replaceState(
		{},
		document.title,
		document.location.pathname + document.location.search.replace(/&do_sync/, ''),
	);
};

/**
 * Get the total number of items from index meta.
 *
 * @param {object} indexMeta Index meta.
 * @returns {number} Number of items.
 */
export const getItemsTotalFromIndexMeta = (indexMeta) => {
	let itemsTotal = indexMeta.totals.total;

	if (indexMeta.current_sync_item) {
		itemsTotal += indexMeta.current_sync_item.found_items;
	}

	itemsTotal = indexMeta.sync_stack.reduce(
		(itemsTotal, sync) => itemsTotal + sync.found_items,
		itemsTotal,
	);

	return itemsTotal;
};

/**
 * Get the number of processed items from index meta.
 *
 * @param {object} indexMeta Index meta.
 * @returns {number} Number of processed items.
 */
export const getItemsProcessedFromIndexMeta = (indexMeta) => {
	let itemsProcessed = indexMeta.totals.total;

	if (indexMeta.current_sync_item) {
		itemsProcessed += indexMeta.current_sync_item.total;
	}

	return itemsProcessed;
};
