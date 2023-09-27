/**
 * External dependencies.
 */
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
import { Icon } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { update } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';

/**
 * Sync button component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { isCli, isComplete, isFailed, isPaused, itemsProcessed, itemsTotal, syncStartDateTime } =
		useSync();

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
			if (isFailed) {
				return __('Sync failed', 'elasticpress');
			}

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
		[isCli, isComplete, isFailed, isPaused],
	);

	const now = itemsTotal ? Math.floor((itemsProcessed / itemsTotal) * 100) : 100;

	return (
		<div
			className={classnames('ep-sync-progress', {
				'ep-sync-progress--syncing': !isPaused && !isComplete && !isFailed,
			})}
		>
			<Icon icon={update} />
			<div className="ep-sync-progress__details">
				<strong>{label}</strong>
				{syncStartDateTime ? (
					<>
						{__('Started on', 'elasticpress')}{' '}
						<time dateTime={dateI18n('c', syncStartDateTime)}>
							{dateI18n('D, F d, Y H:i', syncStartDateTime)}
						</time>
					</>
				) : null}
			</div>
			<div className="ep-sync-progress__progress-bar">
				<div
					aria-valuemax={100}
					aria-valuemin={0}
					aria-valuenow={now}
					className={classnames('ep-sync-progress-bar', {
						'ep-sync-progress-bar--complete': isComplete,
						'ep-sync-progress-bar--failed': isFailed,
					})}
					role="progressbar"
				>
					<div
						className="ep-sync-progress-bar__progress"
						style={{ minWidth: `${now}%` }}
					/>
				</div>
			</div>
		</div>
	);
};
