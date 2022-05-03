/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { useContext, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SyncContext } from '../context';
import pause from './icons/pause';
import play from './icons/play';
import stop from './icons/stop';

/**
 * Sync button component.
 *
 * @param {object} props Component props.
 * @param {Function} props.onPlayPause Play/pause button click callback.
 * @param {Function} props.onStop Stop button click callback.
 * @returns {WPElement} Component.
 */
export default ({ onPlayPause, onStop }) => {
	const { isPaused } = useContext(SyncContext);

	/**
	 * Render.
	 */
	return (
		<>
			<Button
				aria-label={
					isPaused ? __('Resume sync', 'elasticpress') : __('Pause sync', 'elasticpress')
				}
				icon={isPaused ? play : pause}
				onClick={onPlayPause}
				variant="primary"
			/>
			<Button
				aria-label={__('Cancel sync', 'elasticpress')}
				icon={stop}
				isDestructive
				onClick={onStop}
				variant="primary"
			/>
		</>
	);
};
