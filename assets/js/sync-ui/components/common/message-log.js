/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Log component.
 *
 * @param {object} props Component props.
 * @param {object[]} props.messages Log messages.
 * @returns {WPElement} Component.
 */
export default ({ messages }) => {
	return (
		<div className="ep-sync-messages">
			{messages.map((m, i) => (
				<div
					className="ep-sync-messages__line-number"
					key={`line-${m.id}`}
					role="presentation"
				>
					{i + 1}
				</div>
			))}
			{messages.map((m) => (
				<div
					className={`ep-sync-messages__message ep-sync-messages__message--${m.status}`}
					key={`message-${m.id}`}
				>
					{m.message}
				</div>
			))}
		</div>
	);
};
