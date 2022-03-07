import apiFetch from '@wordpress/api-fetch';
import { dateI18n } from '@wordpress/date';

/* eslint-disable camelcase, no-use-before-define */
const { epDash, history } = window;
const { __, sprintf } = wp.i18n;

const { ajax_url: ajaxurl = '', is_epio } = epDash;

// Main elements of sync page
const syncBox = document.querySelector('.ep-sync-data');
const deleteAndSyncBox = document.querySelector('.ep-delete-data-and-sync');

// It could be the syncBox or deleteAndSyncBox
let activeBox;

// Buttons to start a sync or delete data
const syncButton = syncBox.querySelector('.ep-sync-box__button-sync');
const deleteAndSyncButton = deleteAndSyncBox.querySelector(
	'.ep-delete-data-and-sync__button-delete',
);

// Log elements
const syncBoxFulllogTab = document.querySelector('.ep-sync-data .ep-sync-box__output-tab-fulllog');
const syncBoxOutputFulllog = document.querySelector('.ep-sync-data .ep-sync-box__output-fulllog');
const syncBoxErrorTab = document.querySelector('.ep-sync-data .ep-sync-box__output-tab-error');
const syncBoxOutputError = document.querySelector('.ep-sync-data .ep-sync-box__output-error');

const deleteBoxFulllogTab = document.querySelector(
	'.ep-delete-data-and-sync .ep-sync-box__output-tab-fulllog',
);
const deleteBoxErrorTab = document.querySelector(
	'.ep-delete-data-and-sync .ep-sync-box__output-tab-error',
);
const deleteBoxOutputFulllog = document.querySelector(
	'.ep-delete-data-and-sync .ep-sync-box__output-fulllog',
);
const deleteBoxOutputError = document.querySelector(
	'.ep-delete-data-and-sync .ep-sync-box__output-error',
);

syncButton.addEventListener('click', function () {
	activeBox = syncBox;

	disableButtonsInDeleteBox();

	syncButton.style.display = 'none';
	updateDisabledAttribute(syncButton, true);

	const learnMoreLink = activeBox.querySelector('.ep-sync-box__learn-more-link');
	learnMoreLink.style.display = 'none';

	showPauseStopButtons();
	showProgress();
	addLineToOutput(__('Indexing data…', 'elasticpress'));

	const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');
	const progressBar = activeBox.querySelector('.ep-sync-box__progressbar_animated');
	const startDateTime = activeBox.querySelector('.ep-sync-box__start-time-date');

	progressInfoElement.innerText = __('Sync in progress', 'elasticpress');

	progressBar.style.width = `0`;
	progressBar.innerText = ``;

	startDateTime.innerText = '';

	startSyncProcess();
});

deleteAndSyncButton.addEventListener('click', deleteAndSync);

function deleteAndSync() {
	activeBox = deleteAndSyncBox;

	disableButtonsInSyncBox();
	updateDisabledAttribute(deleteAndSyncButton, true);
	showPauseStopButtons();
	showProgress();

	addLineToOutput(__('Deleting data…', 'elasticpress'));

	const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');
	const progressBar = activeBox.querySelector('.ep-sync-box__progressbar_animated');
	const startDateTime = activeBox.querySelector('.ep-sync-box__start-time-date');

	progressInfoElement.innerText = __('Deleting in progress', 'elasticpress');

	progressBar.style.width = `0`;
	progressBar.innerText = ``;

	startDateTime.innerText = '';

	startSyncProcess(true);
}

/**
 * Show Pause and Stop buttons on the active box
 */
function showPauseStopButtons() {
	if (activeBox) {
		showStopButton();
		showPauseButton();
	}
}

/**
 * Hide Pause and Stop buttons on the active box
 */
function hidePauseStopButtons() {
	hideStopButton();
	hidePauseButton();
}

/**
 * Show Pause button on the active box
 */
function showPauseButton() {
	if (activeBox) {
		const pauseButton = activeBox.querySelector('.ep-sync-box__button-pause');

		updateDisabledAttribute(pauseButton, false);

		pauseButton.style.display = 'flex';
	}
}

/**
 * Hide Pause button on the active box
 */
function hidePauseButton() {
	if (activeBox) {
		const pauseButton = activeBox.querySelector('.ep-sync-box__button-pause');

		pauseButton.style.display = 'none';
	}
}

/**
 * Show Resume button on the active box
 */
function showResumeButton() {
	if (activeBox) {
		const resumeButton = activeBox.querySelector('.ep-sync-box__button-resume');

		updateDisabledAttribute(resumeButton, false);

		resumeButton.style.display = 'flex';
	}
}

/**
 * Hide Pause button on the active box
 */
function hideResumeButton() {
	if (activeBox) {
		const resumeButton = activeBox.querySelector('.ep-sync-box__button-resume');

		resumeButton.style.display = 'none';
	}
}

/**
 * Show Stop button on the active box
 */
function showStopButton() {
	if (activeBox) {
		const stopButton = activeBox.querySelector('.ep-sync-box__button-stop');

		updateDisabledAttribute(stopButton, false);

		stopButton.style.display = 'flex';
	}
}

/**
 * Hide Stop button on the active box
 */
function hideStopButton() {
	if (activeBox) {
		const stopButton = activeBox.querySelector('.ep-sync-box__button-stop');

		stopButton.style.display = 'none';
	}
}

function showProgress() {
	const progressWrapper = activeBox?.querySelector('.ep-sync-box__progress-wrapper');

	if (progressWrapper?.style) {
		progressWrapper.style.display = 'block';
	}
}

let syncStatus = 'sync';
let syncStack;
let processed = 0;
let toProcess = 0;
let totalProcessed = 0;

updateLastSyncDateTime(epDash?.ep_last_sync_date);

if (epDash.index_meta) {
	if (epDash.index_meta.method === 'cli') {
		syncStatus = 'wpcli';
		processed = epDash?.index_meta?.items_indexed;
		toProcess = epDash?.index_meta?.total_items;

		activeBox = epDash.index_meta.put_mapping ? deleteAndSyncBox : syncBox;

		const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');

		progressInfoElement.innerText = __('WP-CLI sync in progress', 'elasticpress');

		updateStartDateTime(epDash?.index_meta?.start_date_time);

		updateDisabledAttribute(syncButton, true);
		updateDisabledAttribute(deleteAndSyncButton, true);

		showProgress();

		updateSyncDash();
		cliSync();
	} else {
		processed = epDash.index_meta.offset;
		toProcess = epDash.index_meta.found_items;

		if (epDash.index_meta.sync_stack) {
			syncStack = epDash.index_meta.sync_stack;
		}

		if ((!syncStack || !syncStack.length) && toProcess === 0 && !epDash.index_meta.start) {
			// Sync finished
			syncStatus = 'finished';
		} else {
			syncStatus = 'pause';
		}
		activeBox = epDash.index_meta?.put_mapping ? deleteAndSyncBox : syncBox;

		disableButtonsInSyncBox();
		disableButtonsInDeleteBox();

		if (activeBox === syncBox) {
			syncButton.style.display = 'none';

			const learnMoreLink = activeBox.querySelector('.ep-sync-box__learn-more-link');

			learnMoreLink.style.display = 'none';
		}

		showResumeButton();
		showStopButton();

		showProgress();

		updateSyncDash();
	}
} else if (epDash.auto_start_index) {
	deleteAndSync();

	history.pushState(
		{},
		document.title,
		document.location.pathname + document.location.search.replace(/&do_sync/, ''),
	);
}

/**
 * Change the disabled attribute of an element
 *
 * @param {HTMLElement} element Element to be updated
 * @param {boolean}     value   The value used in disabled attribute
 */
function updateDisabledAttribute(element, value) {
	element.disabled = value;
}

/**
 * Update dashboard with syncing information
 */
function updateSyncDash() {
	const progressBar = activeBox.querySelector('.ep-sync-box__progressbar_animated');

	const isSyncing = ['initialsync', 'sync', 'pause', 'wpcli'].includes(syncStatus);

	let progressBarWidth;
	if (isSyncing) {
		progressBarWidth =
			toProcess === 0 ? 0 : (parseInt(processed, 10) / parseInt(toProcess, 10)) * 100;
	} else {
		progressBarWidth = 100;
	}

	if (
		typeof progressBarWidth === 'number' &&
		!Number.isNaN(progressBarWidth) &&
		Number.isFinite(progressBarWidth)
	) {
		const width = Math.min(100, progressBarWidth);
		progressBar.style.width = `${width}%`;
		progressBar.innerText = `${Math.trunc(width)}%`;
	}

	if (isSyncing) {
		progressBar.classList.remove('ep-sync-box__progressbar_complete');
	} else if (syncStatus === 'interrupt') {
		const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');

		progressInfoElement.innerText = __('Sync interrupted', 'elasticpress');

		updateDisabledAttribute(deleteAndSyncButton, false);
		updateDisabledAttribute(syncButton, false);

		hidePauseStopButtons();
		hideResumeButton();

		syncButton.style.display = 'flex';

		const learnMoreLink = activeBox.querySelector('.ep-sync-box__learn-more-link');

		if (learnMoreLink?.style) {
			learnMoreLink.style.display = 'block';
		}
	} else {
		const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');

		progressInfoElement.innerText = __('Sync completed', 'elasticpress');

		progressBar.classList.add('ep-sync-box__progressbar_complete');

		updateDisabledAttribute(deleteAndSyncButton, false);
		updateDisabledAttribute(syncButton, false);

		hidePauseStopButtons();
		hideResumeButton();

		syncButton.style.display = 'flex';

		const learnMoreLink = activeBox.querySelector('.ep-sync-box__learn-more-link');

		if (learnMoreLink?.style) {
			learnMoreLink.style.display = 'block';
		}
	}
}

/**
 * Cancel a sync
 */
function cancelSync() {
	toProcess = 0;
	processed = 0;
	totalProcessed = 0;

	apiFetch({
		url: ajaxurl,
		method: 'POST',
		body: new URLSearchParams({
			action: 'ep_cancel_index',
			nonce: epDash.nonce,
		}),
	});
}

function cliSync() {
	const requestSettings = {
		url: ajaxurl,
		method: 'POST',
		body: new URLSearchParams({
			action: 'ep_cli_index',
			nonce: epDash.nonce,
		}),
	};

	apiFetch(requestSettings).then((response) => {
		if (syncStatus === 'interrupt') {
			return;
		}

		if (syncStatus === 'wpcli') {
			toProcess = response.data?.index_meta?.total_items;
			processed = response.data?.index_meta?.items_indexed;

			if (response.data.index_meta?.current_sync_item?.failed) {
				const message = response.data?.message;
				if (Array.isArray(message)) {
					message.forEach((item) => {
						addErrorToOutput(item);
						addLineToOutput(item);
					});
				} else if (typeof message === 'string') {
					addErrorToOutput(message);
					addLineToOutput(message);
				}
			} else {
				addLineToOutput(response.data.message);
			}

			updateSyncDash();

			if (response.data?.index_meta?.indexing) {
				cliSync();
				return;
			}
		}

		syncStatus = 'finished';
		addLineToOutput('===============================');
		addLineToOutput(__('WP-CLI sync is finished', 'elasticpress'));
		updateSyncDash();
	});
}

/**
 * Add a line to the active output
 *
 * @param {string} text Message to show on output
 */
function addLineToOutput(text) {
	if (activeBox && text) {
		const wrapperElement = activeBox.querySelector('.ep-sync-box__output-wrapper');

		const lastLineNumberElement = activeBox.querySelector(
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

		wrapperElement.append(line);

		const outputElement = activeBox.querySelector('.ep-sync-box__output_active');
		outputElement.scrollTo(0, wrapperElement.scrollHeight);
	}
}

function addErrorToOutput(text) {
	if (activeBox) {
		const wrapperElement = activeBox.querySelector(
			'.ep-sync-box__output-error .ep-sync-box__output-wrapper',
		);

		const lastLineNumberElement = activeBox.querySelector(
			'.ep-sync-box__output-error .ep-sync-box__output-line:last-child .ep-sync-box__output-line-number',
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

		wrapperElement.append(line);

		const errorTab = activeBox.querySelector('.ep-sync-box__output-tab-error');

		errorTab.innerText = sprintf(
			// translators: Number of errors
			__('Errors (%d)', 'elasticpress'),
			lineNumber.innerText,
		);

		const outputElement = activeBox.querySelector('.ep-sync-box__output-error');
		outputElement.scrollTo(0, wrapperElement.scrollHeight);
	}
}

/**
 * Update the start datetime on active box
 *
 * @param {Date | string} dateValue The datetime value
 */
function updateStartDateTime(dateValue) {
	if (dateValue) {
		const startDateTime = activeBox.querySelector('.ep-sync-box__start-time-date');

		if (startDateTime) {
			startDateTime.innerText = dateI18n(
				// translators: index start date format, see https://wordpress.org/support/article/formatting-date-and-time/
				__('D, F d, Y H:i', 'elasticpress'),
				dateValue,
			);
		}
	}
}

/**
 * Update the last sync datetime
 *
 * @param {Date | string} dateValue Date object or string, parsable by moment.js.
 */
function updateLastSyncDateTime(dateValue) {
	if (dateValue) {
		const lastSyncDate = document.querySelector('.ep-last-sync__date');

		if (lastSyncDate) {
			lastSyncDate.innerText = dateI18n(
				// translators: last sync datetime format, see https://wordpress.org/support/article/formatting-date-and-time/
				__('D, F d, Y H:i', 'elasticpress'),
				dateValue,
			);
		}
	}
}

/**
 * Check if a destructive index is running
 *
 * @returns {boolean} Wheter or not is a destructive index
 */
function isDestructiveIndex() {
	return activeBox === deleteAndSyncBox;
}

/**
 * Interrupt the sync process
 *
 * @param {boolean} value True to interrupt the sync process
 */
function shouldInterruptSync(value) {
	if (!value) {
		return;
	}

	syncStatus = 'interrupt';

	let logMessage = __('Sync interrupted by WP-CLI command', 'elasticpress');
	if (isDestructiveIndex()) {
		logMessage = sprintf(
			// translators: ElasticPress.io or Elasticsearch
			__(
				'Your indexing process has been stopped by WP-CLI and your %s index could be missing content. To restart indexing, please click the Start button or use WP-CLI commands to perform the reindex. Please note that search results could be incorrect or incomplete until the reindex finishes.',
				'elasticpress',
			),
			is_epio ? 'ElasticPress.io' : 'Elasticsearch',
		);
	}
	stopIndex(__('Sync interrupted', 'elasticpress'), logMessage);
}

/**
 * Perform an elasticpress sync
 *
 * @param {boolean} putMapping Whetever mapping should be sent or not.
 */
function sync(putMapping = false) {
	const requestSettings = {
		url: ajaxurl,
		method: 'POST',
		body: new URLSearchParams({
			action: 'ep_index',
			put_mapping: putMapping ? 1 : 0,
			nonce: epDash.nonce,
		}),
	};

	apiFetch(requestSettings)
		.then((response) => {
			if (response.data.index_meta?.current_sync_item?.failed) {
				const message = response.data?.message;
				if (Array.isArray(message)) {
					message.forEach((item) => {
						addErrorToOutput(item);
						addLineToOutput(item);
					});
				} else if (typeof message === 'string') {
					addErrorToOutput(message);
					addLineToOutput(message);
				}
			} else {
				addLineToOutput(response.data.message);
			}
			updateStartDateTime(response?.data?.index_meta?.start_date_time);
			shouldInterruptSync(response.data?.index_meta?.should_interrupt_sync);

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

				const lastSyncStatusIcon = document.querySelector('.ep-last-sync__icon-status');
				const lastSyncStatus = document.querySelector('.ep-last-sync__status');

				lastSyncStatusIcon.src = response.data.totals.failed
					? lastSyncStatusIcon.src?.replace(/thumbsup/, 'thumbsdown')
					: lastSyncStatusIcon.src?.replace(/thumbsdown/, 'thumbsup');
				lastSyncStatus.innerText = response.data.totals.failed
					? __('Sync unsuccessful on ', 'elasticpress')
					: __('Sync success on ', 'elasticpress');

				updateLastSyncDateTime(response.data?.totals?.end_date_time);

				updateSyncDash();

				addLineToOutput('===============================');

				if (epDash.install_sync) {
					document.location.replace(epDash.install_complete_url);
				}

				activeBox = undefined;

				processed = 0;
				toProcess = 0;
				totalProcessed = 0;

				return;
			}

			if (!toProcess) {
				toProcess = response.data?.index_meta?.current_sync_item?.found_items;

				if (response.data?.index_meta?.sync_stack) {
					syncStack = response.data.index_meta.sync_stack;

					toProcess = syncStack?.reduce((previousValue, currentSync) => {
						return previousValue + currentSync.found_items;
					}, toProcess);
				}
			}

			if (response.data.index_meta.offset === 0 && processed > 0) {
				totalProcessed = processed;
			}

			processed = totalProcessed + response.data.index_meta.offset;

			updateSyncDash();
			sync(putMapping);
		})
		.catch((response) => {
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

			updateDisabledAttribute(syncButton, false);
			updateDisabledAttribute(deleteAndSyncButton, false);
		});
}

/**
 * Start sync process
 *
 * @param {boolean} putMapping Determines whether to send the mapping and delete all data before sync.
 */
function startSyncProcess(putMapping) {
	syncStatus = 'initialsync';

	const progressWrapperElement = activeBox.querySelector('.ep-sync-box__progress-wrapper');
	const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');
	const progressBar = activeBox.querySelector('.ep-sync-box__progressbar_animated');
	const startDateTime = activeBox.querySelector('.ep-sync-box__start-time-date');

	progressWrapperElement.style.display = 'block';
	progressInfoElement.innerText = __('Sync in progress', 'elasticpress');

	progressBar.style.width = `0`;
	progressBar.innerText = ``;

	startDateTime.innerText = '';

	updateSyncDash();

	syncStatus = 'sync';

	sync(putMapping);
}

/**
 * Disable buttons in the Sync box
 */
function disableButtonsInSyncBox() {
	const buttons = syncBox.querySelectorAll('.ep-sync-data button');

	buttons.forEach((button) => updateDisabledAttribute(button, true));
}

/**
 * Disable buttons in the Delete box
 */
function disableButtonsInDeleteBox() {
	const buttons = deleteAndSyncBox.querySelectorAll('.ep-delete-data-and-sync button');

	buttons.forEach((button) => updateDisabledAttribute(button, true));
}

document.querySelectorAll('.ep-sync-box__button-pause')?.forEach((button) => {
	button?.addEventListener('click', function () {
		syncStatus = 'pause';

		const progressInfoElement = activeBox?.querySelector('.ep-sync-box__progress-info');

		if (progressInfoElement?.innerText) {
			progressInfoElement.innerText = __('Sync paused', 'elasticpress');
		}

		updateSyncDash();

		hidePauseButton();
		showResumeButton();

		addLineToOutput(__('Sync paused', 'elasticpress'));
	});
});

document.querySelectorAll('.ep-sync-box__button-resume')?.forEach((button) => {
	button?.addEventListener('click', function () {
		syncStatus = 'sync';

		const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');

		progressInfoElement.innerText = __('Sync in progress', 'elasticpress');

		updateSyncDash();

		hideResumeButton();
		showPauseButton();

		sync();
	});
});

function stopIndex(syncMessage, logMessage) {
	syncStatus = syncStatus === 'wpcli' ? 'interrupt' : 'cancel';

	const progressInfoElement = activeBox.querySelector('.ep-sync-box__progress-info');
	const progressBar = activeBox.querySelector('.ep-sync-box__progressbar_animated');

	updateSyncDash();

	cancelSync();

	progressInfoElement.innerText = syncMessage;

	progressBar.style.width = `0`;
	progressBar.innerText = ``;

	addLineToOutput(logMessage);
}
document.querySelectorAll('.ep-sync-box__button-stop')?.forEach((button) => {
	button?.addEventListener('click', () => {
		stopIndex(__('Sync stopped', 'elasticpress'), __('Sync stopped', 'elasticpress'));
	});
});

document.querySelectorAll('.ep-sync-box__show-hide-log')?.forEach((element) => {
	element.addEventListener('click', function (event) {
		event.preventDefault();

		if (element.nextElementSibling?.classList?.toggle('ep-sync-box__output-tabs_hide')) {
			element.innerText = __('Show log', 'elasticpress');
		} else {
			element.innerText = __('Hide log', 'elasticpress');
		}
	});
});

syncBoxFulllogTab.addEventListener('click', function () {
	syncBoxFulllogTab.classList.add('ep-sync-box__output-tab_active');
	syncBoxOutputFulllog.classList.add('ep-sync-box__output_active');

	syncBoxErrorTab.classList.remove('ep-sync-box__output-tab_active');
	syncBoxOutputError.classList.remove('ep-sync-box__output_active');
});

syncBoxErrorTab.addEventListener('click', function () {
	syncBoxErrorTab.classList.add('ep-sync-box__output-tab_active');
	syncBoxOutputError.classList.add('ep-sync-box__output_active');

	syncBoxFulllogTab.classList.remove('ep-sync-box__output-tab_active');
	syncBoxOutputFulllog.classList.remove('ep-sync-box__output_active');
});

deleteBoxFulllogTab.addEventListener('click', function () {
	deleteBoxFulllogTab.classList.add('ep-sync-box__output-tab_active');
	deleteBoxOutputFulllog.classList.add('ep-sync-box__output_active');

	deleteBoxErrorTab.classList.remove('ep-sync-box__output-tab_active');
	deleteBoxOutputError.classList.remove('ep-sync-box__output_active');
});

deleteBoxErrorTab.addEventListener('click', function () {
	deleteBoxErrorTab.classList.add('ep-sync-box__output-tab_active');
	deleteBoxOutputError.classList.add('ep-sync-box__output_active');

	deleteBoxFulllogTab.classList.remove('ep-sync-box__output-tab_active');
	deleteBoxOutputFulllog.classList.remove('ep-sync-box__output_active');
});
