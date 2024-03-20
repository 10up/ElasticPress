/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, TabPanel } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useMemo, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { useSync } from '../../sync';
import Errors from './errors';
import Messages from './messages';

/**
 * Sync logs component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { clearLog, errorCounts, log } = useSync();
	const { createNotice } = useSettingsScreen();

	/**
	 * The number of errors in the log.
	 *
	 * @type {number}
	 */
	const errorCount = useMemo(
		() => errorCounts.reduce((errorCount, e) => errorCount + e.count, 0),
		[errorCounts],
	);

	/**
	 * The log as plain text.
	 *
	 * @type {string}
	 */
	const plainTextLog = useMemo(() => {
		return log.map((m) => `${m.dateTime} ${m.message}`).join('\n');
	}, [log]);

	/**
	 * Handle clicking the clear log button.
	 *
	 * @returns {void}
	 */
	const onClear = () => {
		clearLog();
	};

	/**
	 * Copy to clipboard button ref.
	 *
	 * @type {object}
	 */
	const ref = useCopyToClipboard(plainTextLog, () => {
		createNotice('info', __('Copied log to clipboard.', 'elasticpress'));
	});

	/**
	 * Log tabs.
	 *
	 * @type {Array}
	 */
	const tabs = [
		{
			name: 'full',
			title: __('Log', 'elasticpress'),
		},
		{
			name: 'error',
			title: sprintf(
				/* translators: %d: Error message count. */
				__('Errors (%d)', 'elasticpress'),
				errorCount,
			),
		},
	];

	return (
		<>
			<TabPanel className="ep-sync-log" tabs={tabs}>
				{({ name }) => {
					switch (name) {
						case 'full':
							return <Messages />;
						case 'error':
							return <Errors />;
						default:
							return null;
					}
				}}
			</TabPanel>
			<Flex justify="start">
				<FlexItem>
					<Button disabled={!log.length} onClick={onClear} variant="secondary">
						{__('Clear log', 'elasticpress')}
					</Button>
				</FlexItem>
				<FlexItem>
					<Button disabled={!log.length} ref={ref} variant="secondary">
						{__('Copy log to clipboard', 'elasticpress')}
					</Button>
				</FlexItem>
			</Flex>
		</>
	);
};
