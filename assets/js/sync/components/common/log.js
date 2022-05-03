/**
 * WordPress dependencies.
 */
import { TabPanel } from '@wordpress/components';
import { useMemo, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Log component.
 *
 * @param {object} props Component props.
 * @param {object[]} props.messages Log messages.
 * @returns {WPElement} Component.
 */
export default ({ messages }) => {
	const errorMessages = useMemo(() => messages.filter((m) => m.status === 'error'), [messages]);

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

	return (
		<TabPanel tabs={tabs}>
			{({ messages }) => (
				<div className="ep-sync-log">
					{messages.map((m, i) => (
						<div className="ep-sync-log__line-number" role="presentation">
							{i + 1}
						</div>
					))}
					{messages.map((m) => (
						<div className={`ep-sync-log__message ep-sync-log__message--${m.status}`}>
							{m.message}
						</div>
					))}
				</div>
			)}
		</TabPanel>
	);
};
