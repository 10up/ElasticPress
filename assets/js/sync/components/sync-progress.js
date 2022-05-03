/**
 * WordPress dependencies.
 */
import { useContext, useMemo, WPElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SyncContext } from '../context';
import ProgressBar from './common/progress-bar';

/**
 * Sync button component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { isCli, isComplete, itemsProcessed, itemsTotal, syncStartDateTime } =
		useContext(SyncContext);

	/**
	 * Sync progress message.
	 */
	const message = useMemo(
		/**
		 * Determine appropriate sync status message.
		 *
		 * @returns {string} Sync progress message.
		 */
		() => {
			if (isComplete) {
				return __('Sync complete', 'elasticpress');
			}

			if (isCli) {
				return __('WP CLI sync in progress', 'elasticpress');
			}

			if (syncStartDateTime) {
				return __('Sync in progress', 'elasticpress');
			}

			return __('Starting sync', 'elasticpress');
		},
		[isCli, isComplete, syncStartDateTime],
	);

	return (
		<div>
			{message}
			{syncStartDateTime ? (
				<>
					<em>{__('Start time:', 'elasticpress')}</em>{' '}
					{dateI18n('D, F d, Y H:i', syncStartDateTime)}
				</>
			) : null}
			<ProgressBar current={itemsProcessed} isComplete={isComplete} total={itemsTotal} />
		</div>
	);
};
