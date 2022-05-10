/**
 * WordPress dependencies.
 */
import { Icon } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import DateTime from '../common/date-time';
import ProgressBar from '../common/progress-bar';
import sync from '../icons/sync';

/**
 * Sync button component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isCli If progress is for a CLI sync.
 * @param {boolean} props.isComplete If sync is complete.
 * @param {boolean} props.isPaused If sync is paused.
 * @param {number} props.itemsProcessed Number of items processed.
 * @param {number} props.itemsTotal Total number of items.
 * @param {string} props.dateTime Start date and time.
 * @returns {WPElement} Component.
 */
export default ({ isCli, isComplete, isPaused, itemsProcessed, itemsTotal, dateTime }) => {
	/**
	 * Sync progress label.
	 */
	const label = useMemo(
		/**
		 * Determine appropriate sync status label.
		 *
		 * @returns {string} Sync progress label.
		 */
		() => {
			if (isComplete) {
				return __('Sync complete', 'elasticpress');
			}

			if (isPaused) {
				return __('Sync paused', 'elasticpress');
			}

			if (isCli) {
				return __('WP CLI sync in progress', 'elasticpress');
			}

			return __('Sync in progress', 'elasticpress');
		},
		[isCli, isComplete, isPaused],
	);

	return (
		<div
			className={`ep-sync-progress ${
				!isPaused && !isComplete ? 'ep-sync-progress--syncing' : ''
			}`}
		>
			<Icon icon={sync} />

			<div className="ep-sync-progress__details">
				<strong>{label}</strong>
				{dateTime ? (
					<>
						{__('Started on', 'elasticpress')}{' '}
						<DateTime dateTime={dateI18n('c', dateTime)} />
					</>
				) : null}
			</div>

			<div className="ep-sync-progress__progress-bar">
				<ProgressBar current={itemsProcessed} isComplete={isComplete} total={itemsTotal} />
			</div>
		</div>
	);
};
