/**
 * External dependencies.
 */
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
import { Icon } from '@wordpress/components';
import { createInterpolateElement, useMemo, WPElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
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
	const {
		isCli,
		isComplete,
		isFailed,
		isPaused,
		itemsProcessed,
		itemsTotal,
		syncStartDateTime,
		syncTrigger,
	} = useSync();

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

	const why = useMemo(() => {
		if (isCli) {
			/* translators: %1$s Sync start date and time. */
			return __('Started manually from WP CLI at <time>%s</time>.', 'elasticpress');
		}

		switch (syncTrigger) {
			case 'features':
				/* translators: %1$s Sync start date and time. */
				return __(
					'Started automatically after a change to feature settings at <time>%s</time>.',
					'elasticpress',
				);
			case 'install':
				/* translators: %1$s Sync start date and time. */
				return __(
					'Started automatically after installing the ElasticPress plugin at <time>%s</time>.',
					'elasticpress',
				);
			case 'manual':
				/* translators: %1$s Sync start date and time. */
				return __(
					'Started manually from the Sync page at <time>%s</time>.',
					'elasticpress',
				);
			case 'upgrade':
				/* translators: %1$s Sync start date and time. */
				return __(
					'Started automatically after updating the ElasticPress plugin at <time>%s</time>.',
					'elasticpress',
				);
			default:
				/* translators: %1$s Sync start date and time. */
				return __('Started on <time>%s</time>.', 'elasticpress');
		}
	}, [isCli, syncTrigger]);

	return (
		<div
			className={classnames('ep-sync-progress', {
				'ep-sync-progress--syncing': !isPaused && !isComplete && !isFailed,
			})}
		>
			<Icon icon={update} />
			<div className="ep-sync-progress__details">
				<strong>{label}</strong>
				{syncStartDateTime
					? createInterpolateElement(
							sprintf(why, dateI18n('g:ia l F jS, Y', syncStartDateTime)),
							{
								time: <time dateTime={dateI18n('c', syncStartDateTime)} />,
							},
					  )
					: null}
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
