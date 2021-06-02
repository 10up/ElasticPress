/* eslint-disable camelcase, no-use-before-define */
const { ajaxurl, epDash, history } = window;

const $errorOverlay = jQuery(document.getElementsByClassName('error-overlay'));
const $progressBar = jQuery(document.getElementsByClassName('progress-bar'));
const $syncStatusText = jQuery(document.getElementsByClassName('sync-status'));
const $startSyncButton = jQuery(document.getElementsByClassName('start-sync'));
const $resumeSyncButton = jQuery(document.getElementsByClassName('resume-sync'));
const $pauseSyncButton = jQuery(document.getElementsByClassName('pause-sync'));
const $cancelSyncButton = jQuery(document.getElementsByClassName('cancel-sync'));
const epSyncOutput = document.getElementById('ep-sync-output');

let syncStatus = 'sync';
let currentSyncItem;
let syncStack;
let processed = 0;
let toProcess = 0;

if (epDash.index_meta) {
	if (epDash.index_meta.wpcli_sync) {
		syncStatus = 'wpcli';
		processed = epDash.index_meta.items_indexed;
		toProcess = epDash.index_meta.total_items;

		updateSyncDash();
		cliSync();
	} else {
		processed = epDash.index_meta.offset;
		toProcess = epDash.index_meta.found_items;

		if (epDash.index_meta.current_sync_item) {
			currentSyncItem = epDash.index_meta.current_sync_item;
		}

		if (epDash.index_meta.site_stack) {
			syncStack = epDash.index_meta.sync_stack;
		}

		if (syncStack && syncStack.length) {
			// We are mid sync
			if (epDash.auto_start_index) {
				syncStatus = 'sync';

				history.pushState(
					{},
					document.title,
					document.location.pathname + document.location.search.replace(/&do_sync/, ''),
				);

				updateSyncDash();
				sync();
			} else {
				syncStatus = 'pause';
				updateSyncDash();
			}
		} else if (toProcess === 0 && !epDash.index_meta.start) {
			// Sync finished
			syncStatus = 'finished';
			updateSyncDash();
		} else {
			// We are mid sync
			if (epDash.auto_start_index) {
				syncStatus = 'sync';

				history.pushState(
					{},
					document.title,
					document.location.pathname + document.location.search.replace(/&do_sync/, ''),
				);

				updateSyncDash();
				sync();
			} else {
				syncStatus = 'pause';
				updateSyncDash();
			}
		}
	}
} else {
	// Start a new sync automatically
	if (epDash.auto_start_index) {
		syncStatus = 'initialsync';

		updateSyncDash();

		syncStatus = 'sync';

		history.pushState(
			{},
			document.title,
			document.location.pathname + document.location.search.replace(/&do_sync/, ''),
		);

		sync();
	}
}

/**
 * Update dashboard with syncing information
 */
function updateSyncDash() {
	let text;

	if (processed === 0) {
		$progressBar.css({ width: '1%' });
	} else {
		const width = (parseInt(processed, 10) / parseInt(toProcess, 10)) * 100;
		$progressBar.css({ width: `${width}%` });
	}

	if (syncStatus === 'initialsync') {
		text = epDash.sync_initial;

		$syncStatusText.text(text);

		$syncStatusText.show();
		$progressBar.show();
		$pauseSyncButton.show();
		$errorOverlay.addClass('syncing');

		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.hide();
	} else if (syncStatus === 'sync') {
		text = epDash.sync_syncing;

		if (currentSyncItem) {
			if (currentSyncItem.indexable) {
				text += ` ${epDash.sync_indexable_labels[
					currentSyncItem.indexable
				].plural.toLowerCase()} ${parseInt(processed, 10)}/${parseInt(toProcess, 10)}`;
			}

			if (currentSyncItem.url) {
				text += ` (${currentSyncItem.url})`;
			}
		}

		$syncStatusText.text(text);

		$syncStatusText.show();
		$progressBar.show();
		$pauseSyncButton.show();
		$errorOverlay.addClass('syncing');

		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.hide();
	} else if (syncStatus === 'pause') {
		text = epDash.sync_paused;

		if (toProcess && toProcess !== 0) {
			text += `, ${parseInt(processed, 10)}/${parseInt(
				toProcess,
				10,
			)} ${epDash.sync_indexable_labels[currentSyncItem.indexable].plural.toLowerCase()}`;
		}

		if (currentSyncItem && currentSyncItem.url) {
			text += ` (${currentSyncItem.url})`;
		}

		$syncStatusText.text(text);

		$syncStatusText.show();
		$progressBar.show();
		$pauseSyncButton.hide();
		$errorOverlay.addClass('syncing');

		$cancelSyncButton.show();
		$resumeSyncButton.show();
		$startSyncButton.hide();
	} else if (syncStatus === 'wpcli') {
		text = epDash.sync_wpcli;

		if (currentSyncItem?.indexable) {
			text += ` ${parseInt(processed, 10)}/${parseInt(
				toProcess,
				10,
			)} ${epDash.sync_indexable_labels[currentSyncItem.indexable].plural.toLowerCase()}`;
		}

		if (currentSyncItem?.url) {
			text += ` (${currentSyncItem.url})`;
		}

		$syncStatusText.text(text);

		$syncStatusText.show();
		$progressBar.show();
		$pauseSyncButton.hide();
		$errorOverlay.addClass('syncing');

		$cancelSyncButton.show();
		$resumeSyncButton.hide();
		$startSyncButton.hide();
	} else if (syncStatus === 'error') {
		$syncStatusText.text(epDash.sync_error);
		$syncStatusText.show();
		$startSyncButton.show();
		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$pauseSyncButton.hide();
		$errorOverlay.removeClass('syncing');
		$progressBar.hide();

		setTimeout(() => {
			$syncStatusText.hide();
		}, 7000);
	} else if (syncStatus === 'cancel') {
		$syncStatusText.hide();
		$progressBar.hide();
		$pauseSyncButton.hide();
		$errorOverlay.removeClass('syncing');

		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.show();
	} else if (syncStatus === 'finished') {
		$syncStatusText.text(epDash.sync_complete);

		$syncStatusText.show();
		$progressBar.hide();
		$pauseSyncButton.hide();
		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.show();
		$errorOverlay.removeClass('syncing');

		setTimeout(() => {
			$syncStatusText.hide();
		}, 7000);
	} else if (syncStatus === 'interrupt') {
		$syncStatusText.text(epDash.sync_interrupted);

		$syncStatusText.show();
		$progressBar.hide();
		$pauseSyncButton.hide();
		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.show();
		$errorOverlay.removeClass('syncing');

		setTimeout(() => {
			$syncStatusText.hide();
		}, 7000);
	}
}

/**
 * Cancel a sync
 */
function cancelSync() {
	jQuery.ajax({
		method: 'post',
		url: ajaxurl,
		data: {
			action: 'ep_cancel_index',
			nonce: epDash.nonce,
		},
	});
}

function cliSync() {
	jQuery
		.ajax({
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_cli_index',
				nonce: epDash.nonce,
			},
		})
		.done((response) => {
			if (syncStatus === 'wpcli') {
				toProcess = response.data?.total_items;
				processed = response.data?.items_indexed;

				currentSyncItem = {
					indexable: response.data?.slug,
					url: response.data?.url,
				};

				if (response.data?.indexing) {
					updateSyncDash();

					cliSync();
					return;
				}

				syncStatus = '';
				updateSyncDash();
			} else if (syncStatus === 'interrupt') {
				return;
			}

			syncStatus = 'finished';
			updateSyncDash();
		});
}

/**
 * Perform an elasticpress sync
 *
 * @param {boolean} putMapping Whetever mapping should be sent or not.
 */
function sync(putMapping = false) {
	jQuery
		.ajax({
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_index',
				put_mapping: putMapping ? 1 : 0,
				nonce: epDash.nonce,
			},
		})
		.done((response) => {
			epSyncOutput.innerHTML += `${response.data.message}\n`;
			epSyncOutput.scrollTop = epSyncOutput.scrollHeight;
			epSyncOutput.style.display = 'block';

			if (response.data?.should_interrupt_sync) {
				syncStatus = 'interrupt';
				updateSyncDash();
				cancelSync();
			}

			if (response.data?.method === 'cli') {
				syncStatus = 'wpcli';
				cliSync();
				return;
			}

			if (syncStatus !== 'sync') {
				return;
			}

			if (!response.data.index_meta) {
				syncStatus = 'finished';
				updateSyncDash();

				if (epDash.install_sync) {
					document.location.replace(epDash.install_complete_url);
				}
			}

			toProcess = response.data.index_meta.found_items;
			processed = response.data.index_meta.offset;

			if (response.data.sync_stack) {
				syncStack = response.data.index_meta.sync_stack;
			}

			if (response.data.index_meta.current_sync_item) {
				currentSyncItem = response.data.index_meta.current_sync_item;
			}

			updateSyncDash();
			sync(putMapping);
		})
		.error((response) => {
			if (
				response &&
				response.status &&
				parseInt(response.status, 10) >= 400 &&
				parseInt(response.status, 10) < 600
			) {
				syncStatus = 'error';
				updateSyncDash();

				cancelSync();
			}
		});
}

$startSyncButton.on('click', (event) => {
	syncStatus = 'initialsync';

	updateSyncDash();

	// On initial sync, remove dashboard warnings that dont make sense
	jQuery(
		'[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]',
	).remove();

	syncStatus = 'sync';

	const putMapping = event.target.classList.contains('start-sync-put-mapping');
	sync(putMapping);
});

$pauseSyncButton.on('click', () => {
	syncStatus = 'pause';

	updateSyncDash();
});

$resumeSyncButton.on('click', () => {
	syncStatus = 'sync';

	updateSyncDash();

	sync();
});

$cancelSyncButton.on('click', () => {
	syncStatus = syncStatus === 'wpcli' ? 'interrupt' : 'cancel';

	updateSyncDash();

	cancelSync();
});
