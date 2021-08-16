/* eslint-disable camelcase, no-use-before-define */
const { ajaxurl, epDash, history } = window;

const overlay = document.querySelectorAll('.error-overlay');
const progressBar = document.querySelectorAll('.progress-bar');
const allButtons = {
	start: document.querySelectorAll('.start-sync'),
	resume: document.querySelectorAll('.resume-sync'),
	pause: document.querySelectorAll('.pause-sync'),
	cancel: document.querySelectorAll('.cancel-sync'),
};
const syncStatusText = document.querySelectorAll('.sync-status');
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

		if ((!syncStack || !syncStack.length) && toProcess === 0 && !epDash.index_meta.start) {
			// Sync finished
			syncStatus = 'finished';
		} else {
			syncStatus = 'pause';
		}
		updateSyncDash();
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

		sync(true);
	}
}

/**
 * Show and hide buttons.
 *
 * @param {Array} visibleButtons Buttons that should be visible.
 */
function makeButtonsVisible(visibleButtons) {
	Object.keys(allButtons).forEach((key) => {
		allButtons[key].forEach((button) => {
			button.style.display = visibleButtons.includes(key) ? 'inline' : 'none';
		});
	});
}

/**
 * Change the sync status text and show/hide the element if needed.
 *
 * @param {string} newText New sync status text.
 */
function updateSyncText(newText) {
	syncStatusText.forEach((syncStatus) => {
		syncStatus.innerText = newText;
		syncStatus.style.display = newText ? 'inline' : 'none';
	});
}

/**
 * Show or hide the progress bar(s).
 *
 * @param {boolean} display Wheter the progress bar(s) should or should not be visible.
 */
function showProgressBar(display = true) {
	progressBar.forEach((bar) => {
		bar.style.display = display ? 'block' : 'none';
	});
}

/**
 * Show or hide the overlay(s).
 *
 * @param {boolean} display Wheter the overlay(s) should or should not be visible.
 */
function showOverlay(display = true) {
	overlay.forEach((overlay) => {
		if (display) {
			overlay.classList.add('syncing');
		} else {
			overlay.classList.remove('syncing');
		}
	});
}

/**
 * Get the indexable label from the global object. If not set, default to the indexable slug.
 *
 * @param {string} indexableSlug The indexable slug
 * @param {string} type          Plural or singular. Defaults to plural.
 * @returns {string} The indexable label
 */
function getIndexableLabel(indexableSlug, type = 'plural') {
	const labels = epDash.sync_indexable_labels[indexableSlug];

	return labels?.[type].toLowerCase() || `${indexableSlug}s`;
}

/**
 * Update dashboard with syncing information
 */
function updateSyncDash() {
	let text;

	const progressBarWidth =
		processed === 0 ? 1 : (parseInt(processed, 10) / parseInt(toProcess, 10)) * 100;
	progressBar.forEach((bar) => {
		bar.style.width = `${progressBarWidth}%`;
	});

	const isSyncing = ['initialsync', 'sync', 'pause', 'wpcli'].includes(syncStatus);
	if (isSyncing) {
		showProgressBar();
		showOverlay();
	} else {
		showProgressBar(false);
		showOverlay(false);

		setTimeout(() => {
			updateSyncText('');
		}, 7000);

		makeButtonsVisible(['start']);
	}

	if (syncStatus === 'initialsync') {
		updateSyncText(epDash.sync_initial);
		makeButtonsVisible(['pause']);
	} else if (syncStatus === 'sync') {
		text = epDash.sync_syncing;

		if (currentSyncItem) {
			if (currentSyncItem.indexable) {
				text += ` ${getIndexableLabel(currentSyncItem.indexable)} ${parseInt(
					processed,
					10,
				)}/${parseInt(toProcess, 10)}`;
			}

			if (currentSyncItem.url) {
				text += ` (${currentSyncItem.url})`;
			}
		}
		updateSyncText(text);
		makeButtonsVisible(['pause']);
	} else if (syncStatus === 'pause') {
		text = epDash.sync_paused;

		if (toProcess && toProcess !== 0) {
			text += `, ${parseInt(processed, 10)}/${parseInt(toProcess, 10)} ${getIndexableLabel(
				currentSyncItem.indexable,
			)}`;
		}

		if (currentSyncItem && currentSyncItem.url) {
			text += ` (${currentSyncItem.url})`;
		}

		updateSyncText(text);
		makeButtonsVisible(['cancel', 'resume']);
	} else if (syncStatus === 'wpcli') {
		text = epDash.sync_wpcli;

		if (currentSyncItem?.indexable) {
			text += ` ${parseInt(processed, 10)}/${parseInt(toProcess, 10)} ${getIndexableLabel(
				currentSyncItem.indexable,
			)}`;
		}

		if (currentSyncItem?.url) {
			text += ` (${currentSyncItem.url})`;
		}

		updateSyncText(text);
		makeButtonsVisible(['cancel']);
	} else if (syncStatus === 'error') {
		updateSyncText(epDash.sync_error);
	} else if (syncStatus === 'cancel') {
		updateSyncText('');
	} else if (syncStatus === 'finished') {
		updateSyncText(epDash.sync_complete);
	} else if (syncStatus === 'interrupt') {
		updateSyncText(epDash.sync_interrupted);
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
			if (syncStatus === 'interrupt') {
				return;
			}

			if (syncStatus === 'wpcli') {
				toProcess = response.data?.total_items;
				processed = response.data?.items_indexed;

				currentSyncItem = {
					indexable: response.data?.slug,
					url: response.data?.url,
				};

				syncStatus = '';
				updateSyncDash();

				if (response.data?.indexing) {
					cliSync();
					return;
				}
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

				epSyncOutput.innerHTML += `===============================\n`;
				epSyncOutput.scrollTop = epSyncOutput.scrollHeight;

				if (epDash.install_sync) {
					document.location.replace(epDash.install_complete_url);
				}

				return;
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
