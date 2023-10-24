/**
 * WordPress dependencies.
 */
import { Panel, PanelBody } from '@wordpress/components';
import { useEffect, WPElement } from '@wordpress/element';
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
	const { isComplete, isEpio, isSyncing, logMessage, startSync, syncHistory, syncTrigger } =
		useSync();
	const { autoIndex } = useSyncSettings();

	/**
	 * Handle a completed sync.
	 */
	const onComplete = () => {
		if (isComplete) {
			createNotice('success', __('Sync completed.', 'elasticpress'));
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

	useEffect(onComplete, [createNotice, isComplete]);
	useEffect(onInit, [autoIndex, logMessage, startSync, syncTrigger]);

	return (
		<>
			<p>
				{syncHistory.length
					? __(
							'If you are missing data in your search results or have recently added custom content types to your site, you should run a sync to reflect these changes.',
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
				<PanelBody initialOpen={false} title="Log">
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
		</>
	);
};
