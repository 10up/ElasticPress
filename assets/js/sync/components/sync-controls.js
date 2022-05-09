/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { update } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import pause from './icons/pause';
import play from './icons/play';
import stop from './icons/stop';

/**
 * Sync button component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.disabled If controls are disabled.
 * @param {boolean} props.isPaused If syncing is paused.
 * @param {boolean} props.isSyncing If syncing is in progress.
 * @param {Function} props.onPause Pause button click callback.
 * @param {Function} props.onResume Play button click callback.
 * @param {Function} props.onStop Stop button click callback.
 * @param {Function} props.onSync Sync button click callback.
 * @param {boolean} props.showSync If sync button is shown.
 * @returns {WPElement} Component.
 */
export default ({ disabled, isPaused, isSyncing, onPause, onResume, onStop, onSync, showSync }) => {
	/**
	 * Render.
	 */
	return (
		<div className="ep-sync-controls">
			{showSync && !isSyncing ? (
				<div className="ep-sync-controls__sync">
					<Button
						className="ep-sync-button ep-sync-button--sync"
						disabled={disabled}
						icon={update}
						isPrimary
						onClick={onSync}
					>
						{__('Sync Now', 'elasticpress')}
					</Button>
				</div>
			) : null}

			{isSyncing ? (
				<>
					<div className="ep-sync-controls__resume">
						<Button
							className="ep-sync-button ep-sync-button--resume"
							disabled={disabled}
							icon={isPaused ? play : pause}
							isPrimary
							onClick={isPaused ? onResume : onPause}
						>
							{isPaused ? __('Resume', 'elasticpress') : __('Pause', 'elasticpress')}
						</Button>
					</div>

					<div className="ep-sync-controls__stop">
						<Button
							className="ep-sync-button ep-sync-button--stop"
							disabled={disabled}
							icon={stop}
							isSecondary
							onClick={onStop}
						>
							{__('Stop', 'elasticpress')}
						</Button>
					</div>
				</>
			) : null}

			{showSync ? (
				<div className="ep-sync-controls__learn-more">
					<Button
						href="https://elasticpress.zendesk.com/hc/en-us/articles/5205632443533"
						variant="link"
					>
						{__('Learn more', 'elasticpress')}
					</Button>
				</div>
			) : null}
		</div>
	);
};
