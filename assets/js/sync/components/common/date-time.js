/**
 * WordPress dependencies.
 */
import { dateI18n } from '@wordpress/date';
import { WPElement } from '@wordpress/element';

/**
 * Log component.
 *
 * @param {object} props Component props.
 * @param {string} props.dateTime Date and time.
 * @returns {WPElement} Component.
 */
export default ({ dateTime, ...props }) => {
	return (
		<time dateTime={dateI18n('c', dateTime)} {...props}>
			{dateI18n('D, F d, Y H:i', dateTime)}
		</time>
	);
};
