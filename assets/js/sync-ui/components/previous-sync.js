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
import { __, _n, sprintf } from '@wordpress/i18n';

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
 * @param {string} props.status Sync status.
 * @param {string} props.trigger Sync trigger.
 * @returns {WPElement} Sync page component.
 */
export default ({ failures, method, stateDatetime, status, trigger }) => {
	/**
	 * When the sync was started.
	 */
	const when = useMemo(() => dateI18n('l F j, Y g:ia', stateDatetime), [stateDatetime]);

	/**
	 * How the sync completed.
	 */
	const how = useMemo(() => {
		switch (status) {
			case 'failed':
				return __('Failed.', 'elasticpress');
			case 'with_errors':
				return failures
					? sprintf(
							_n(
								'Completed with %d error.',
								'Completed with %d errors.',
								failures,
								'elasticpress',
							),
							failures,
					  )
					: __('Completed with errors.', 'elasticpress');
			case 'aborted':
				return __('Stopped.', 'elasticpress');
			case 'success':
				return __('Completed successfully.', 'elasticpress');
			default:
				return __('Completed.', 'elasticpress');
		}
	}, [failures, status]);

	/**
	 * Why the sync was started.
	 */
	const why = useMemo(() => {
		if (method === 'cli') {
			return __('Manual sync from WP CLI.', 'elasticpress');
		}

		switch (trigger) {
			case 'manual':
			default: {
				return __('Manual sync from Sync Settings.', 'elasticpress');
			}
		}
	}, [method, trigger]);

	/**
	 * Whether the sync has errors.
	 */
	const isError = useMemo(() => {
		return status === 'failed' || status === 'with_errors' || status === 'aborted';
	}, [status]);

	/**
	 * Whether the sync was a success.
	 */
	const isSuccess = useMemo(() => {
		return status === 'success';
	}, [status]);

	return (
		<div
			className={classnames('ep-previous-sync', {
				'is-error': isError,
				'is-success': isSuccess,
			})}
		>
			<Icon icon={isError ? error : success} />
			<div className="ep-previous-sync__title">
				{when} &mdash; {why}
			</div>
			<div className="ep-previous-sync__help">{how}</div>
		</div>
	);
};
