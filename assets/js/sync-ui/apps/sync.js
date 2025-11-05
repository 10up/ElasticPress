/**
 * WordPress dependencies.
 */
import { Panel, PanelBody } from '@wordpress/components';
import { useEffect, useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */

import { useSettingsScreen } from '../../settings-screen';
import { useSync } from '../../sync';
import Controls from '../components/controls';
import Indexables from '../components/types';
import Log from '../components/log';
import Objects from '../components/objects';
import Progress from '../components/progress';
import PutMapping from '../components/put-mapping';
import SyncHistory from '../components/sync-history';
import { useSyncSettings } from '../provider';

/**
 * Sync page component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const { createNotice } = useSettingsScreen();
	const {
		errorCounts,
		isComplete,
		isEpio,
		isSyncing,
		logMessage,
		startSync,
		syncHistory,
		syncTrigger,
	} = useSync();
	const { args, autoIndex } = useSyncSettings();

	/**
	 * State.
	 */
	const [isLogOpen, setIsLogOpen] = useState(false);
	const [errorCount, setErrorCount] = useState(0);

	/**
	 * Handle toggling the log panel.
	 *
	 * @param {boolean} opened Whether the panel will be open.
	 */
	const onToggleLog = (opened) => {
		setIsLogOpen(opened);
	};

	/**
	 * Display a notice when a sync is complete.
	 */
	const onCompleteDisplayNotice = () => {
		if (isComplete) {
			createNotice('success', __('Sync completed.', 'elasticpress'));
		}
	};

	/**
	 * Handle logs and errors count when a sync is complete.
	 */
	const onComplete = () => {
		if (isComplete) {
			const newErrorCount = errorCounts.reduce((c, e) => c + e.count, 0);

			if (newErrorCount > errorCount) {
				setIsLogOpen(true);
			}

			setErrorCount(newErrorCount);
		}
	};

	/**
	 * Initialize.
	 *
	 * @returns {void}
	 */
	const onInit = () => {
		if (autoIndex) {
			startSync({ put_mapping: true, trigger: syncTrigger });
			logMessage(__('Starting delete and sync…', 'elasticpress'), 'info');
		}
	};

	/**
	 * Handle clicking sync button.
	 *
	 * @param {Event} event Submit event.
	 * @returns {void}
	 */
	const onSync = async (event) => {
		event.preventDefault();

		const { put_mapping } = args;

		const putMapping = syncHistory.length ? put_mapping : true;
		const syncArgs = { ...args, put_mapping: putMapping, trigger: 'manual' };

		startSync(syncArgs);
		logMessage(__('Starting sync…', 'elasticpress'), 'info');
	};

	useEffect(onCompleteDisplayNotice, [createNotice, isComplete]);
	useEffect(onComplete, [createNotice, errorCount, errorCounts, isComplete]);
	useEffect(onInit, [autoIndex, logMessage, startSync, syncTrigger]);

	return (
		<form onSubmit={onSync}>
			<p>
				{syncHistory.length
					? __(
							'ElasticPress automatically syncs your content every time you create, delete, or update it. After the initial sync, manual syncs are rarely needed unless you make changes to your content programmatically, enable certain features, or change your Elasticsearch mapping via code.',
							'elasticpress',
						)
					: sprintf(
							/* translators: %s: Index type. ElasticPress.io or Elasticsearch. */
							__(
								'Run a sync to index your existing content %s. Once syncing finishes, your site is officially supercharged.',
								'elasticpress',
							),
							isEpio
								? __('on ElasticPress.io', 'elasticpress')
								: __('in Elasticsearch', 'elasticpress'),
						)}
			</p>
			<Panel className="ep-sync-panel">
				<PanelBody className="ep-sync-panel__controls">
					{isSyncing || isComplete ? <Progress /> : null}
					<Controls />
					{syncHistory.length ? <PutMapping /> : null}
				</PanelBody>
				<PanelBody onToggle={onToggleLog} opened={isLogOpen} title="Log">
					<Log />
				</PanelBody>
				{syncHistory.length ? (
					<>
						<PanelBody
							className="ep-sync-panel__advanced"
							initialOpen={false}
							title={__('Advanced options', 'elasticpress')}
						>
							<Indexables />
							<Objects />
						</PanelBody>
						<PanelBody title={__('Sync history', 'elasticpress')}>
							<SyncHistory />
						</PanelBody>
					</>
				) : null}
			</Panel>
		</form>
	);
};
