/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies.
 */
import thumbsDown from '../icons/thumbs-down';
import thumbsUp from '../icons/thumbs-up';

/**
 * Sync button component.
 *
 * @param {object} props Component props.
 * @param {string} props.dateTime Relevant date and time.
 * @param {boolean} props.isSuccess Is the status success.
 * @param {string} props.label Status label.
 * @returns {WPElement} Component.
 */
export default ({ dateTime, isSuccess, label }) => {
	return (
		<div
			className={`ep-sync-status ${
				isSuccess ? `ep-sync-status--success` : `ep-sync-status--error`
			}`}
		>
			<Icon icon={isSuccess ? thumbsUp : thumbsDown} /> {label}
			<time dateTime={dateI18n('c', dateTime)}>{dateI18n('D, F d, Y H:i', dateTime)}</time>
		</div>
	);
};
