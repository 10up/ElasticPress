/**
 * WordPress dependencies.
 */
import { TabPanel, ToggleControl } from '@wordpress/components';
import { useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import MessageLog from '../common/message-log';

/**
 * Sync logs component.
 *
 * @param {object} props Component props.
 * @param {object[]} props.messages Log messages.
 * @returns {WPElement} Component.
 */
export default ({ messages }) => {
	const [isOpen, setIsOpen] = useState(false);

	/**
	 * Messages with the error status.
	 */
	const errorMessages = messages.filter((m) => m.status === 'error' || m.status === 'warning');

	/**
	 * Log tabs.
	 */
	const tabs = [
		{
			messages,
			name: 'full',
			title: __('Full Log', 'elasticpress'),
		},
		{
			messages: errorMessages,
			name: 'error',
			title: sprintf(
				/* translators: %d: Error message count. */
				__('Errors (%d)', 'elasticpress'),
				errorMessages.length,
			),
		},
	];

	/**
	 * Handle clicking show log button.
	 *
	 * @param {boolean} checked If toggle is checked.
	 * @returns {void}
	 */
	const onToggle = (checked) => {
		setIsOpen(checked);
	};

	return (
		<>
			<ToggleControl
				checked={isOpen}
				onChange={onToggle}
				label={__('Show log', 'elasticpress')}
			/>
			{isOpen ? (
				<TabPanel tabs={tabs}>
					{({ messages }) => <MessageLog messages={messages} />}
				</TabPanel>
			) : null}
		</>
	);
};
