/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import { useSyncSettings } from '../provider';

/**
 * Sync controls component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { isPaused, isSyncing, logMessage, pauseSync, resumeSync, stopSync } = useSync();

	const { args } = useSyncSettings();

	/**
	 * Handle clicking pause button.
	 *
	 * @returns {void}
	 */
	const onPause = () => {
		pauseSync();
		logMessage(__('Pausing sync…', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking play button.
	 *
	 * @returns {void}
	 */
	const onResume = () => {
		resumeSync(args);
		logMessage(__('Resuming sync…', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking stop button.
	 *
	 * @returns {void}
	 */
	const onStop = () => {
		stopSync();
		logMessage(__('Sync stopped', 'elasticpress'), 'info');
	};

	/**
	 * Render.
	 */
	return (
		<div className="ep-sync-controls">
			{isSyncing ? (
				<>
					<Button onClick={onStop} variant="primary">
						{__('Stop sync', 'elasticpress')}
					</Button>
					{isPaused ? (
						<Button onClick={onResume} variant="secondary">
							{__('Resume sync', 'elasticpress')}
						</Button>
					) : (
						<Button onClick={onPause} variant="secondary">
							{__('Pause sync', 'elasticpress')}
						</Button>
					)}
				</>
			) : (
				<Button variant="primary" type="submit">
					{__('Start sync', 'elasticpress')}
				</Button>
			)}
			<Button
				href="https://elasticpress.zendesk.com/hc/en-us/articles/5205632443533"
				target="_blank"
				variant="link"
			>
				{__('Learn more about Sync', 'elasticpress')}
			</Button>
		</div>
	);
};
