/* eslint-disable camelcase, no-use-before-define */
const { ajaxurl, epDash, history } = window;

const progressBar = document.querySelectorAll('.ep-sync-data .ep-sync-box__progressbar_animated');
const allButtons = {
	start: document.querySelectorAll('.ep-start-sync'),
	resume: document.querySelectorAll('.ep-sync-box__button-resume'),
	pause: document.querySelectorAll('.ep-sync-box__button-pause'),
	stop: document.querySelectorAll('.ep-sync-box__button-stop'),
	learnMore: document.querySelectorAll('.ep-sync-box__learn-more-link'),
};
const deleteButon = document.querySelector('.ep-delete-data-and-sync__button');
const syncStatusText = document.querySelectorAll('.sync-status');
const $startSyncButton = jQuery(document.getElementsByClassName('ep-start-sync'));
const $resumeSyncButton = jQuery(document.getElementsByClassName('ep-sync-box__button-resume'));
const $pauseSyncButton = jQuery(document.getElementsByClassName('ep-sync-box__button-pause'));
const $stopSyncButton = jQuery(document.getElementsByClassName('ep-sync-box__button-stop'));
const epSyncOutput = document.getElementById('ep-sync-output');
const epDeleteOutput = document.getElementById('ep-delete-output');

let syncStatus = 'sync';
let currentSyncItem;
let syncStack;
let processed = 0;
let toProcess = 0;

if (epDash.index_meta) {
	if (epDash.index_meta.method === 'cli') {
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
} else if (epDash.auto_start_index) {
	// Start a new sync automatically
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

/**
 * Show and hide buttons.
 *
 * @param {Array} visibleButtons Buttons that should be visible.
 */
function makeButtonsVisible(visibleButtons) {
	Object.keys(allButtons).forEach((key) => {
		allButtons[key].forEach((button) => {
			button.style.display = visibleButtons.includes(key) ? 'flex' : 'none';
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
 * Get the indexable label from the global object. If not set, default to the indexable slug.
 *
 * @param {string} indexableSlug The indexable slug
 * @param {string} type          Plural or singular. Defaults to plural.
 * @return {string} The indexable label
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

	const progressBarWidth = (parseInt(processed, 10) / parseInt(toProcess, 10)) * 100;
	progressBar.forEach((bar) => {
		if (typeof progressBarWidth === 'number' && !Number.isNaN(progressBarWidth)) {
			bar.style.width = `${progressBarWidth}%`;
			bar.innerText = `${Math.trunc(progressBarWidth)}%`;
		}
	});

	const isSyncing = ['initialsync', 'sync', 'pause', 'wpcli'].includes(syncStatus);
	if (isSyncing) {
		showProgressBar();
		progressBar.forEach((bar) => {
			bar.classList.remove('ep-sync-box__progressbar_complete');
		});
	} else {
		const progressInfoElement = document.querySelector('.ep-sync-box__progress-info');

		progressInfoElement.innerText = 'Sync completed';

		progressBar.forEach((bar) => {
			bar.classList.add('ep-sync-box__progressbar_complete');
		});

		setTimeout(() => {
			updateSyncText('');
		}, 7000);

		makeButtonsVisible(['start', 'learnMore']);
	}

	if (syncStatus === 'initialsync') {
		updateSyncText(epDash.sync_initial);
		makeButtonsVisible(['pause', 'stop']);
	} else if (syncStatus === 'sync') {
		makeButtonsVisible(['pause', 'stop']);
	} else if (syncStatus === 'pause') {
		text = epDash.sync_paused;

		updateSyncText(text);
		makeButtonsVisible(['resume', 'stop']);
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
		makeButtonsVisible(['stop']);
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

function addLineToOutput(text, outputElement) {
	const lastLineNumberElement = document.querySelector(
		'.ep-sync-box__output-line:last-child .ep-sync-box__output-line-number',
	);
	const lastLineNumber = Number(lastLineNumberElement?.innerText);

	const lineNumber = document.createElement('div');
	lineNumber.className = 'ep-sync-box__output-line-number';
	lineNumber.innerText =
		typeof lastLineNumber === 'number' && !Number.isNaN(lastLineNumber)
			? lastLineNumber + 1
			: 1;

	const lineText = document.createElement('div');
	lineText.className = 'ep-sync-box__output-line-text';
	lineText.innerText = text;

	const line = document.createElement('div');
	line.className = 'ep-sync-box__output-line';
	line.append(lineNumber);
	line.append(lineText);

	outputElement.append(line);

	const epSyncWrapper = document.querySelector('.ep-sync-box__output');
	epSyncWrapper.scrollTo(0, outputElement.scrollHeight);
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
			addLineToOutput(response.data.message, epSyncOutput);

			if (response.data?.index_meta?.should_interrupt_sync) {
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

				addLineToOutput('===============================', epSyncOutput);

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
			if (syncStatus === 'sync') {
				updateSyncText(response.data.message);
			}
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

function startSyncProcess(putMapping) {
	syncStatus = 'initialsync';

	const progressWrapperElement = document.querySelector('.ep-sync-data .ep-sync-box__progress-wrapper');

	progressWrapperElement.style.display = 'block';

	const progressInfoElement = document.querySelector('.ep-sync-box__progress-info');

	progressInfoElement.innerText = 'Sync in progress';

	progressBar.forEach((bar) => {
		bar.style.width = `0`;
		bar.innerText = ``;
	});

	updateSyncDash();

	// On initial sync, remove dashboard warnings that dont make sense
	jQuery(
		'[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]',
	).remove();

	syncStatus = 'sync';

	sync(putMapping);
}

$startSyncButton.on('click', () => { startSyncProcess() });

$pauseSyncButton.on('click', () => {
	syncStatus = 'pause';

	const progressInfoElement = document.querySelector('.ep-sync-box__progress-info');

	progressInfoElement.innerText = 'Sync paused';

	updateSyncDash();
});

$resumeSyncButton.on('click', () => {
	syncStatus = 'sync';

	const progressWrapperElement = document.querySelector('.ep-sync-data .ep-sync-box__progress-wrapper');

	progressWrapperElement.style.display = 'block';

	const progressInfoElement = document.querySelector('.ep-sync-box__progress-info');

	progressInfoElement.innerText = 'Sync in progress';

	updateSyncDash();

	sync();
});

$stopSyncButton.on('click', () => {
	syncStatus = syncStatus === 'wpcli' ? 'interrupt' : 'cancel';

	const progressInfoElement = document.querySelector('.ep-sync-box__progress-info');

	updateSyncDash();

	cancelSync();

	progressInfoElement.innerText = 'Sync stopped';

	progressBar.forEach((bar) => {
		bar.style.width = `0`;
		bar.innerText = ``;
	});

	addLineToOutput('Sync stopped', epSyncOutput);
});

deleteButon.addEventListener('click', function() {
	addLineToOutput('Deleting all data...', epDeleteOutput);

	deleteButon.style.display = 'none';

	const cancelButton = document.querySelector('.ep-delete-data-and-sync__button-cancel');
	cancelButton.style.display = 'block';

	const progressWrapperElement = document.querySelector('.ep-delete-data-and-sync .ep-sync-box__progress-wrapper');

	progressWrapperElement.style.display = 'block';

	const progressInfoElement = document.querySelector('.ep-delete-data-and-sync .ep-sync-box__progress-info');

	progressInfoElement.innerText = 'Deleting indexed data...';

	const progressBar = document.querySelector('.ep-delete-data-and-sync .ep-sync-box__progressbar_animated');

	progressBar.style.width = `25%`;
	progressBar.innerText = `25%`;

	setTimeout(() => {
		progressBar.style.width = `100%`;
		progressBar.innerText = `100%`;
		progressBar.classList.add('ep-sync-box__progressbar_complete');

		addLineToOutput('Deletion complete', epDeleteOutput);

		cancelButton.style.display = 'none';
		deleteButon.style.display = 'block';
	}, 5000);

	setTimeout(() => {
		startSyncProcess(true);
		progressWrapperElement.style.display = 'none';
		console.log(progressBar);
		progressBar.classList.remove('ep-sync-box__progressbar_complete');
		progressBar.style.width = `0`;
		progressBar.innerText = ``;
	}, 7000)

});
