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
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import error from './icons/error';
import success from './icons/success';

/**
 * Delete checkbox component.
 *
 * @param {object} props Component props.
 * @param {number} props.failures Number of failed items.
 * @param {string} props.method Sync method.
 * @param {string} props.stateDatetime Sync end date and time.
 * @param {string} props.trigger Sync trigger.
 * @returns {WPElement} Sync page component.
 */
export default ({ failures, method, stateDatetime, trigger }) => {
	/**
	 * Whether the sync failed.
	 */
	const isFailed = useMemo(() => !!failures, [failures]);

	/**
	 * When the sync was started.
	 */
	const when = useMemo(() => dateI18n('l F j, Y g:ia', stateDatetime), [stateDatetime]);

	/**
	 * How the sync completed.
	 */
	const how = useMemo(
		() =>
			isFailed
				? sprintf(__('Completed with %d errors.', 'elasticpress'), failures)
				: __('Completed successfully.', 'elasticpress'),
		[isFailed, failures],
	);

	/**
	 * Why the sync was started.
	 */
	const why = useMemo(() => {
		if (method === 'cli') {
			return __('Manual sync from WP CLI', 'elasticpress');
		}

		switch (trigger) {
			case 'manual':
			default: {
				return __('Manual sync from Sync Settings', 'elasticpress');
			}
		}
	}, [method, trigger]);

	return (
		<div
			className={classnames('ep-previous-sync', {
				'is-success': !isFailed,
				'is-error': isFailed,
			})}
		>
			<Icon icon={isFailed ? error : success} />
			<div className="ep-previous-sync__title">
				{when} &mdash; {why}
			</div>
			<div className="ep-previous-sync__help">{how}</div>
		</div>
	);
};
