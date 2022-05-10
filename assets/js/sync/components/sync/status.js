/**
 * WordPress dependencies.
 */
import { Icon } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import DateTime from '../common/date-time';
import thumbsDown from '../icons/thumbs-down';
import thumbsUp from '../icons/thumbs-up';

/**
 * Sync button component.
 *
 * @param {object} props Component props.
 * @param {string} props.dateTime Sync date and time.
 * @param {boolean} props.isSuccess If sync was a success.
 * @returns {WPElement} Component.
 */
export default ({ dateTime, isSuccess }) => {
	return (
		<p
			className={`ep-sync-status ${
				isSuccess ? `ep-sync-status--success` : `ep-sync-status--error`
			}`}
		>
			<Icon icon={isSuccess ? thumbsUp : thumbsDown} />
			<span className="ep-sync-status__label">
				{isSuccess
					? __('Sync success on', 'elasticpress')
					: __('Sync unsuccessful on', 'elasticpress')}{' '}
				<DateTime dateTime={dateI18n('c', dateTime)} className="ep-sync-status__time" />
			</span>
		</p>
	);
};
